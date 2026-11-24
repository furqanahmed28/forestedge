import logging
import time

class LoRaRadio:
    """
    UART Template for LoRaWAN AT-Command based transceivers (e.g. RAK3172, RYLR998).
    This logic remains disabled in core defaults, but provides the robust template 
    for real physical multi-km propagation required for ForestEdge.
    """
    def __init__(self, port="/dev/ttyS0", baudrate=115200):
        self.port = port
        self.baudrate = baudrate
        self.serial = None
        
        try:
            import serial
            self.serial = serial.Serial(self.port, self.baudrate, timeout=1)
            logging.info(f"LoRaRadio initialized on {self.port}")
            self._init_radio()
        except ImportError:
            logging.warning("pyserial not found. LoRaRadio disabled.")
        except Exception as e:
            logging.warning(f"Could not open serial port {self.port}: {e}")

    def _init_radio(self):
        # Example AT commands to join network or set parameters
        if self.serial:
            self.serial.write(b"AT+JOIN=1\r\n")
            time.sleep(2)
            response = self.serial.read_all()
            logging.debug(f"LoRa Join Response: {response}")

    def send_packet(self, payload_dict):
        """
        Sends a json dictionary as a hex encoded or direct string payload over LoRaWAN.
        """
        if not self.serial:
            return False
        import json
        
        msg = json.dumps(payload_dict)
        # AT+SEND=<Port>:<Payload>
        # e.g., AT+SEND=2:{"type":"heartbeat"}
        cmd = f"AT+SEND=2:{msg}\r\n".encode('utf-8')
        try:
            self.serial.write(cmd)
            time.sleep(1)
            output = self.serial.read_all()
            logging.debug(f"LoRa Send Out: {output}")
            return True
        except Exception as e:
            logging.error(f"LoRa transmit failed: {e}")
            return False

    def receive_packet(self):
        """
        Listens for incoming LoRaWAN packets and decodes the JSON payload.
        """
        if not self.serial:
            return None
        
        try:
            if self.serial.in_waiting > 0:
                line = self.serial.readline().decode('utf-8').strip()
                # Assuming module spits out +RCV=Port:Length:Payload
                if line.startswith("+RCV="):
                    parts = line.split(":", 2)
                    if len(parts) == 3:
                        payload = parts[2]
                        import json
                        return json.loads(payload)
            return None
        except Exception as e:
            logging.error(f"LoRa receive error: {e}")
            return None

    def close(self):
        if self.serial:
            self.serial.close()
