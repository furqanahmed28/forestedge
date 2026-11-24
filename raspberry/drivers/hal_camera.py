import os
import random
import logging
import shutil

class HalCamera:
    def __init__(self, test_images_dir="test_images"):
        self.test_images_dir = test_images_dir
        self.cap = None
        self.cv2 = None
        
        try:
            # pyrefly: ignore [missing-import]
            import cv2
            self.cv2 = cv2
            # Check system default camera
            self.cap = self.cv2.VideoCapture(0)
            if self.cap.isOpened():
                logging.info("Hardware camera initialized (cv2.VideoCapture(0)).")
            else:
                logging.warning("Hardware cv2.VideoCapture(0) failed to open. Will fall back to test_images or CSI utilities.")
                self.cap = None
        except ImportError:
            logging.warning("cv2 module missing. Falling back to test_images directory or CSI utilities.")

    def _resize_and_compress_image(self, file_path):
        """
        Resizes the image at file_path to below 300x300 px keeping aspect ratio,
        and saves it in JPEG format to fit within 10-20kb.
        """
        if not os.path.exists(file_path):
            return
        
        # Try using PIL (Pillow) first
        try:
            # pyrefly: ignore [missing-import]
            from PIL import Image
            img = Image.open(file_path)
            w, h = img.size
            scale = min(300.0 / w, 300.0 / h)
            if scale < 1.0:
                new_w, new_h = int(w * scale), int(h * scale)
                img = img.resize((new_w, new_h), Image.Resampling.LANCZOS)
            img = img.convert("RGB")
            img.save(file_path, "JPEG", quality=85)
            logging.info(f"Resized image (PIL) saved to {file_path}. Size: {os.path.getsize(file_path)} bytes.")
            return
        except Exception as e:
            logging.warning(f"PIL resize failed: {e}. Trying cv2.")

        # Try using cv2 as fallback
        if self.cv2 is not None:
            try:
                frame = self.cv2.imread(file_path)
                if frame is not None:
                    h, w = frame.shape[:2]
                    scale = min(300.0 / w, 300.0 / h)
                    if scale < 1.0:
                        new_w, new_h = int(w * scale), int(h * scale)
                        frame = self.cv2.resize(frame, (new_w, new_h), interpolation=self.cv2.INTER_AREA)
                    self.cv2.imwrite(file_path, frame, [int(self.cv2.IMWRITE_JPEG_QUALITY), 85])
                    logging.info(f"Resized image (cv2) saved to {file_path}. Size: {os.path.getsize(file_path)} bytes.")
                    return
            except Exception as e2:
                logging.error(f"cv2 resize failed: {e2}")

        # Try using ImageMagick CLI 'mogrify' as third fallback
        try:
            import subprocess
            res = subprocess.run(["mogrify", "-resize", "300x300", file_path],
                                 stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=5)
            if res.returncode == 0:
                logging.info(f"Resized image (mogrify CLI) saved to {file_path}. Size: {os.path.getsize(file_path)} bytes.")
                return
        except Exception as e3:
            logging.debug(f"mogrify CLI fallback failed or not installed: {e3}")

    def capture_image_path(self, output_path="temp_capture.jpg"):
        """
        Attempts to capture from:
        1. VideoCapture(0) (USB/standard camera).
        2. libcamera-still (CSI ribbon camera standard utility, requesting 300x300).
        3. rpicam-still (CSI ribbon camera alternative utility, requesting 300x300).
        4. Fallback to test_images (copied to output_path).
        Then resizes the image to keep aspect ratio and be under 300x300 px (10-20kb).
        """
        captured = False

        # 1. Try OpenCV
        if self.cv2 is not None and self.cap is not None:
            try:
                for _ in range(5):
                    self.cap.grab()
                ret, frame = self.cap.read()
                if ret:
                    self.cv2.imwrite(output_path, frame)
                    captured = True
                    logging.info("Captured image via OpenCV VideoCapture.")
            except Exception as e:
                logging.error(f"OpenCV capture failed: {e}")

        # 2. Try libcamera-still (RPi modern camera utility, with built-in width/height constraints)
        if not captured:
            try:
                import subprocess
                logging.info("Attempting CSI camera capture via libcamera-still...")
                res = subprocess.run(["libcamera-still", "-n", "-t", "500", "--width", "300", "--height", "300", "-o", output_path],
                                     stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
                if res.returncode == 0 and os.path.exists(output_path):
                    captured = True
                    logging.info("Captured image via libcamera-still.")
            except Exception as e:
                logging.debug(f"libcamera-still failed or not available: {e}")

        # 3. Try rpicam-still (RPi camera utility alternative, with built-in width/height constraints)
        if not captured:
            try:
                import subprocess
                logging.info("Attempting CSI camera capture via rpicam-still...")
                res = subprocess.run(["rpicam-still", "-n", "-t", "500", "--width", "300", "--height", "300", "-o", output_path],
                                     stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
                if res.returncode == 0 and os.path.exists(output_path):
                    captured = True
                    logging.info("Captured image via rpicam-still.")
            except Exception as e:
                logging.debug(f"rpicam-still failed or not available: {e}")

        # 4. Fallback to test_images
        if not captured:
            logging.warning("All hardware camera captures failed. Using test_images fallback.")
            if os.path.exists(self.test_images_dir):
                images = [os.path.join(self.test_images_dir, f) for f in os.listdir(self.test_images_dir) 
                          if f.endswith(('.jpg', '.jpeg', '.png'))]
                if images:
                    fallback_img = random.choice(images)
                    shutil.copy(fallback_img, output_path)
                    captured = True
                    logging.info(f"Copied fallback image {fallback_img} to {output_path}.")
                else:
                    logging.error(f"No valid fallback images found in {self.test_images_dir}.")
            else:
                logging.error(f"Test images directory {self.test_images_dir} missing.")

        if captured:
            self._resize_and_compress_image(output_path)
            return output_path

        return None

    def release(self):
        if self.cap is not None:
            self.cap.release()

