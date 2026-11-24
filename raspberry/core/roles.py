import time
import json
import logging
import urllib.request
import os

from drivers.hal_sensors import HalSensors
from drivers.hal_camera import HalCamera
from inference.engine import ProcessingEngine
from core.network import UDPNetwork

class Node:
    def __init__(self, config):
        self.config = config
        self.role = config.get("role", "LeafNode")
        self.node_id = config.get("node_id", "01")
        self.cluster_id = config.get("cluster_id", "01")
        self.local_ip = config.get("ip", {}).get("local", "127.0.0.1")
        self.local_port = config.get("ports", {}).get("local", 5002)
        
        self.target_ip = config.get("ip", {}).get("target", "127.0.0.1")
        self.target_port = config.get("ports", {}).get("target", 5001)

        self.sensors = HalSensors()
        self.camera = HalCamera(test_images_dir="test_images")
        self.engine = ProcessingEngine(config)
        
        self.standalone = config.get("standalone", False)
        if not self.standalone:
            self.network = UDPNetwork(self.local_ip, self.local_port)
        else:
            self.network = None
            
        
        self.last_heartbeat = 0
        self.heartbeat_interval = 30  # seconds

    def wakeUp(self):
        logging.info(f"{self.role} {self.cluster_id}-{self.node_id} Waking Up...")
        if self.network:
            self.network.start_listening()

    def runCycle(self):
        pass # implemented by subclasses
        
    def check_heartbeat(self):
        now = time.time()
        if now - self.last_heartbeat >= self.heartbeat_interval:
            self.send_heartbeat()
            self.last_heartbeat = now
            
    def send_heartbeat(self):
        payload = {
            "type": "heartbeat",
            "cluster_id": self.cluster_id,
            "node_id": self.node_id,
            "status": "Healthy",
            "timestamp": time.time()
        }
        self.network.send(self.target_ip, self.target_port, payload)

    def sleep(self, duration=2):
        logging.info(f"{self.role} going to sleep for {duration} seconds")
        time.sleep(duration)

    def shutdown(self):
        self.camera.release()
        if self.network:
            self.network.close()

class LeafNode(Node):
    def runCycle(self):
        self.check_heartbeat()
        
        # Sense
        env_data = self.sensors.read_all()
        logging.info(f"Leaf Sensing: {env_data}")
        
        # Capture Image
        img_path = self.camera.capture_image_path()
        
        # Infer
        inference_result = self.engine.run_inference(img_path, env_data["temperature"], env_data["humidity"])
        logging.info(f"Leaf Inference: {inference_result}")
        
        # Send Packet to Submaster if anomaly
        if inference_result.get("Problem", "nothing").lower() != "nothing":
            packet = {
                "type": "telemetry",
                "cluster_id": self.cluster_id,
                "node_id": self.node_id,
                "env_data": env_data,
                "inference": inference_result,
                "timestamp": time.time(),
                "generated_at": time.time()
            }
            self.network.send(self.target_ip, self.target_port, packet)


class SubMasterNode(Node):
    def runCycle(self):
        self.check_heartbeat()
        
        # Process inbox 
        messages = self.network.get_messages()
        for msg in messages:
            # Aggregate and forward
            logging.info(f"SubMaster Received: {msg}")
            
            # Submasters forward directly to Master
            self.network.send(self.target_ip, self.target_port, msg)
            
        # Optional: Submaster itself can sense and infer
        if time.time() % 10 < 2:  # Sense every 10s approximately
            env_data = self.sensors.read_all()
            img_path = self.camera.capture_image_path()
            inference_result = self.engine.run_inference(img_path, env_data.get("temperature"), env_data.get("humidity"))
            if inference_result.get("Problem", "nothing").lower() != "nothing":
                packet = {
                    "type": "telemetry",
                    "cluster_id": self.cluster_id,
                    "node_id": self.node_id,
                    "env_data": env_data,
                    "inference": inference_result,
                    "timestamp": time.time()
                }
                self.network.send(self.target_ip, self.target_port, packet)


class MasterNode(Node):
    def __init__(self, config):
        super().__init__(config)
        self.cloud_url = config.get("ip", {}).get("master_cloud_url", "http://localhost/web/api.php?key=FE_SECRET_2026")
        self.buffer_file = "alerts_buffer.json"

    def purge_old_images(self):
        """
        Scans the 'images' directory and deletes any images older than 3 days (259,200 seconds).
        """
        images_dir = "images"
        if not os.path.exists(images_dir):
            return
        
        now = time.time()
        three_days_sec = 3 * 24 * 60 * 60
        deleted_count = 0
        
        try:
            for filename in os.listdir(images_dir):
                file_path = os.path.join(images_dir, filename)
                if os.path.isfile(file_path):
                    # Check the last modified time
                    stat = os.stat(file_path)
                    age = now - stat.st_mtime
                    if age > three_days_sec:
                        os.remove(file_path)
                        deleted_count += 1
            if deleted_count > 0:
                logging.info(f"Purged {deleted_count} image(s) older than 3 days.")
        except Exception as e:
            logging.error(f"Error purging old images: {e}")

    def runCycle(self):
        self.check_heartbeat()
        
        # We process incoming UDP packets if networked
        if self.network:
            messages = self.network.get_messages()
            for msg in messages:
                logging.info(f"Master Received: {msg}")
                self.upload_to_cloud(msg)
                
        # If Standalone, Master itself acts as the sole sensor node
        if getattr(self, "standalone", False):
            # Run image storage purge
            self.purge_old_images()

            env_data = self.sensors.read_all()
            logging.info(f"Master Sensing: {env_data}")
            
            # Generate unique timestamp for both image and log
            timestamp_str = time.strftime("%Y%m%d_%H%M%S")
            img_path = f"images/img_{timestamp_str}.jpg"
            
            captured_path = self.camera.capture_image_path(output_path=img_path)
            
            try:
                inference_result = self.engine.run_inference(captured_path, env_data["temperature"], env_data["humidity"])
                logging.info(f"Master Inference: {inference_result}")
                
                # Write individual run log in logs folder
                log_path = f"logs/log_{timestamp_str}.json"
                try:
                    with open(log_path, 'w', encoding='utf-8') as lf:
                        json.dump({
                            "timestamp": time.time(),
                            "timestamp_str": timestamp_str,
                            "env_data": env_data,
                            "inference_result": inference_result,
                            "image_path": captured_path
                        }, lf, indent=4)
                    logging.info(f"Saved inference log to {log_path}")
                except Exception as le:
                    logging.error(f"Failed to save log to {log_path}: {le}")

                problem = inference_result.get("Problem", "nothing").lower()
                if problem != "nothing":
                    packet = {
                        "type": "telemetry",
                        "cluster_id": self.cluster_id,
                        "node_id": self.node_id,
                        "env_data": env_data,
                        "inference": inference_result,
                        "timestamp": time.time(),
                        "generated_at": time.time()
                    }
                    self.upload_to_cloud(packet)
            except Exception as e:
                logging.error(f"Master inference/upload failed: {e}")
            
        # Attempt to flush buffer
        self.flush_buffer()

    def send_heartbeat(self):
        # Master node itself is also alive, but primarily it relays.
        # Master doesn't send UDP heartbeat, it sends HTTP to cloud
        payload = {
            "type": "heartbeat",
            "cluster_id": self.cluster_id,
            "node_id": self.node_id,
            "status": "Healthy",
            "timestamp": time.time()
        }
        self.upload_to_cloud(payload)

    def upload_to_cloud(self, data):
        try:
            import ssl
            context = ssl._create_unverified_context()
            req = urllib.request.Request(self.cloud_url, method="POST")
            req.add_header('Content-Type', 'application/json')
            jsondata = json.dumps(data).encode('utf-8')
            with urllib.request.urlopen(req, data=jsondata, timeout=2.0, context=context) as response:
                if response.status not in (200, 201):
                    raise Exception(f"HTTP Error {response.status}")
        except Exception as e:
            logging.warning(f"Cloud API Unreachable: {e}. Buffering data locally.")
            self._buffer_data(data)

    def _buffer_data(self, data):
        buffer = []
        if os.path.exists(self.buffer_file):
            try:
                with open(self.buffer_file, 'r') as f:
                    buffer = json.load(f)
            except Exception:
                pass
        buffer.append(data)
        with open(self.buffer_file, 'w') as f:
            json.dump(buffer, f)

    def flush_buffer(self):
        if not os.path.exists(self.buffer_file):
            return
            
        try:
            with open(self.buffer_file, 'r') as f:
                buffer = json.load(f)
        except Exception:
            return
            
        if not buffer:
            return
            
        logging.info(f"Attempting to flush {len(buffer)} items from edge buffer.")
        remaining_buffer = []
        for data in buffer:
            try:
                import ssl
                context = ssl._create_unverified_context()
                req = urllib.request.Request(self.cloud_url, method="POST")
                req.add_header('Content-Type', 'application/json')
                jsondata = json.dumps(data).encode('utf-8')
                with urllib.request.urlopen(req, data=jsondata, timeout=2.0, context=context) as response:
                    pass # Success
            except Exception as e:
                remaining_buffer.append(data)
                
        with open(self.buffer_file, 'w') as f:
            json.dump(remaining_buffer, f)
