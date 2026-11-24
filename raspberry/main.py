import sys
import json
import logging
import time
import os

# Ensure logs and images directories exist
os.makedirs("logs", exist_ok=True)
os.makedirs("images", exist_ok=True)

# Configure logging to write to both console and logs/master.log
for handler in logging.root.handlers[:]:
    logging.root.removeHandler(handler)

log_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
root_logger = logging.getLogger()
root_logger.setLevel(logging.INFO)

# Console Logger
console_handler = logging.StreamHandler(sys.stdout)
console_handler.setFormatter(log_formatter)
root_logger.addHandler(console_handler)

# Master File Logger
file_handler = logging.FileHandler("logs/master.log", mode='a', encoding='utf-8')
file_handler.setFormatter(log_formatter)
root_logger.addHandler(file_handler)

def main():
    if len(sys.argv) > 1:
        config_path = sys.argv[1]
    else:
        config_path = "config.json"

    try:
        with open(config_path, 'r') as f:
            config = json.load(f)
    except FileNotFoundError:
        logging.error(f"Config file {config_path} not found.")
        sys.exit(1)

    role = config.get("role", "LeafNode")
    logging.info(f"Initializing ForestEdge OS as {role}")

    if role == "MasterNode":
        from core.roles import MasterNode
        node = MasterNode(config)
    elif role == "SubMasterNode":
        from core.roles import SubMasterNode
        node = SubMasterNode(config)
    else:
        from core.roles import LeafNode
        node = LeafNode(config)

    node.wakeUp()

    # Read inference interval from config.json, defaulting to 15 seconds
    interval = config.get("inference_interval_seconds", 15)
    logging.info(f"Scheduler active: running inference cycle every {interval} seconds.")

    try:
        while True:
            node.runCycle()
            node.sleep(duration=interval)
    except KeyboardInterrupt:
        logging.info("Shutting down...")
    finally:
        node.shutdown()

if __name__ == "__main__":
    main()

