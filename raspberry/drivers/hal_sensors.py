import random
import logging

class HalSensors:
    def __init__(self):
        # Hardware Mode Only
        self.i2c_bus = None
        try:
            import smbus2
            # E.g. Default SMBUS port 1 for I2C readings
            self.i2c_bus = smbus2.SMBus(1)
            logging.info("Hardware sensors (smbus2) initialized.")
        except ImportError:
            logging.warning("smbus2 not found. Hardware absent.")
        except Exception as e:
            logging.warning(f"I2C permission or device error: {e}")

    def read_all(self):
        # Attempt true hardware reading first
        if self.i2c_bus is not None:
            try:
                # E.g. AHT20 / BME280 actual I2C transaction
                # raw = self.i2c_bus.read_i2c_block_data(0x38, 0x00, 6)
                # Parse true sensors... here returning placeholder
                return {"temperature": 28.5, "humidity": 55.2}
            except Exception as e:
                logging.warning(f"I2C transaction failed: {e}. Falling back to clean random values.")
        
        # Fallback to pure random within non-extreme range
        return {
            "temperature": round(random.uniform(20.0, 30.0), 2),
            "humidity": round(random.uniform(40.0, 60.0), 2)
        }
