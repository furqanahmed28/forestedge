import socket
import threading
import json
import logging

class UDPNetwork:
    def __init__(self, local_ip, local_port):
        self.local_ip = local_ip
        self.local_port = local_port
        self.sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        # Allow port reuse just in case
        self.sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.sock.bind((self.local_ip, self.local_port))
        
        self.running = False
        self.listener_thread = None
        self.inbox = []
        
        logging.info(f"Network bounded to UDP {self.local_ip}:{self.local_port}")

    def start_listening(self):
        self.running = True
        self.listener_thread = threading.Thread(target=self._listen, daemon=True)
        self.listener_thread.start()

    def _listen(self):
        while self.running:
            try:
                self.sock.settimeout(1.0) # non blocking completely
                data, addr = self.sock.recvfrom(4096)
                if data:
                    message = data.decode('utf-8')
                    try:
                        parsed = json.loads(message)
                        self.inbox.append(parsed)
                        logging.debug(f"Received data from {addr}: {parsed}")
                    except json.JSONDecodeError:
                        logging.error("Failed to decode incoming JSON")
            except socket.timeout:
                continue
            except ConnectionResetError:
                # Ignore ICMP port unreachable errors on Windows
                continue
            except Exception as e:
                if self.running:
                    logging.error(f"UDP Listen error: {e}")

    def get_messages(self):
        """Retrieve and clear the inbox."""
        msgs = self.inbox.copy()
        self.inbox.clear()
        return msgs

    def send(self, target_ip, target_port, payload_dict):
        try:
            msg = json.dumps(payload_dict)
            self.sock.sendto(msg.encode('utf-8'), (target_ip, target_port))
            logging.debug(f"Sent data to {target_ip}:{target_port}")
        except Exception as e:
            logging.error(f"Failed to send UDP packet: {e}")

    def close(self):
        self.running = False
        if self.listener_thread:
            self.listener_thread.join(timeout=2)
        self.sock.close()
