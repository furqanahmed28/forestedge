import subprocess
import os
import re
import json
import logging
import random

SYS_PROMPT = """You are an expert arborist, forest ranger, and plant pathologist.

Analyze the image objectively to determine if there is an anomaly or if it is normal. 

GUIDELINES FOR UNBIASED IDENTIFICATION:
1. Treat "nothing" (a normal forest environment) and problem categories with equal probability.
2. Only classify as a problem if there is clear, visible evidence. Do not guess or assume.
3. If the environment is completely normal and healthy, classify as "nothing".
4. If a problem is present, categorize it precisely.

Output a valid JSON object with the following fields:
{
  "Problem": "<fire|disease|pest|human|logging|nothing>",
  "Criticality": "<none|low|medium|high>",
  "Description": "<a short description, max 10 words>"
}

Criticality Guidelines (based strictly on scale):
- If the problem is "nothing", Criticality is "none".
- If the problem is small (e.g., small flame/smoke, minor leaf spots, single stump), Criticality is "low".
- If the problem is medium (e.g., localized fire, notable infestation, multiple stumps), Criticality is "medium".
- If the problem is large (e.g., large forest fire, widespread canopy disease, active heavy logging), Criticality is "high".
"""

def generate_user_prompt():
    return """Analyze the tree image and objectively evaluate it.
Identify if it depicts a normal state ("nothing") or one of the active issues.

Output format:
{
  "Problem": "<fire|disease|pest|human|logging|nothing>",
  "Criticality": "<none|low|medium|high>",
  "Description": "<a short description, max 10 words>"
}
"""

class ProcessingEngine:
    def __init__(self, config_data):
        self.flags = config_data.get("llama_flags", {})
        self.bin_path = self.flags.get("bin_path", "llama-mtmd-cli")
        self.model_path = self.flags.get("model_path", "model.gguf")
        self.mmproj_path = self.flags.get("mmproj_path", "mmproj.gguf")
        self.threads = self.flags.get("threads", 4)
        self.n_predict = self.flags.get("n_predict", 128)
        self.hal_mode = config_data.get("hal_mode", "MOCK")

    def run_inference(self, image_path, temp, humidity):
        if not image_path:
            return {"Problem": "nothing", "Criticality": "none", "Description": "No image provided", "temperature": temp, "humidity": humidity}

        if not os.path.exists(self.bin_path):
            logging.warning(f"Binary {self.bin_path} not found. Skipping inference.")
            return {"Problem": "nothing", "Criticality": "none", "Description": "Binary missing", "temperature": temp, "humidity": humidity}

        user_prompt = generate_user_prompt()
        
        # Load parameters from configuration or use highly balanced defaults
        temp_val = self.flags.get("temp", 0.1)
        top_k_val = self.flags.get("top_k", 40)
        top_p_val = self.flags.get("top_p", 0.9)
        repeat_penalty_val = self.flags.get("repeat_penalty", 1.1)

        cmd = [
            self.bin_path,
            "-m", self.model_path,
            "--mmproj", self.mmproj_path,
            "--image", image_path,
            "-sys", SYS_PROMPT,
            "-p", user_prompt,
            "-n", str(self.n_predict),
            "--temp", str(temp_val),
            "--top-k", str(top_k_val),
            "--top-p", str(top_p_val),
            "--repeat-penalty", str(repeat_penalty_val),
            "-c", "2048",
            "-t", str(self.threads)
        ]

        logging.info(f"Running LLM Inference on {image_path} with temp={temp_val}, top_k={top_k_val}, top_p={top_p_val}...")
        
        try:
            out_f = f"node_out_{self.threads}.txt"
            err_f = f"node_err_{self.threads}.txt"
            
            with open(out_f, "w", encoding="utf-8") as fo, open(err_f, "w", encoding="utf-8") as fe:
                proc = subprocess.Popen(cmd, stdout=fo, stderr=fe)
                proc.wait()
                
            stdout_data = open(out_f, encoding="utf-8", errors="ignore").read().strip()
            stderr_data = open(err_f, encoding="utf-8", errors="ignore").read().strip()
            
            # Extract JSON
            cleaned = re.sub(r"```json|```", "", stdout_data, flags=re.IGNORECASE).strip()
            m = re.search(r"\{.*?\}", cleaned, re.DOTALL)
            if m:
                res = json.loads(m.group(0))
                # Embed temperature and humidity in the parsed JSON dict
                res["temperature"] = temp
                res["humidity"] = humidity
                return res
            else:
                logging.error(f"Failed to parse JSON. STDOUT: {stdout_data[:200]} | STDERR: {stderr_data[:500]}")
                return {"Problem": "nothing", "Criticality": "none", "Description": "Parse Failure", "temperature": temp, "humidity": humidity}
        except Exception as e:
            logging.error(f"Subprocess failed: {e}")
            return {"Problem": "nothing", "Criticality": "none", "Description": "Execution Failure", "temperature": temp, "humidity": humidity}
