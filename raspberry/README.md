# ForestEdge — Physical Edge Node Prototyping & Runtime (`raspberry/`)

This directory contains the operational runtime of **ForestEdge OS**, designed for physical edge hardware (Raspberry Pi 4 / 5 / CM4, Rockchip, or comparable embedded single-board computers). It handles hardware sensor acquisition, camera capture, local quantized Vision-Language Model (VLM) inference, hierarchical mesh routing (LoRaWAN / UDP), and store-and-forward telemetry to the central monitoring dashboard.

---

## Architecture Overview

In remote forest environments, continuous high-bandwidth cloud uplinks are unavailable, and power budgets are severely constrained. ForestEdge shifts the burden of visual analysis and threat detection directly to the edge through a **three-tier hierarchical node architecture**:

```
 [ Leaf Node 1 ]      [ Leaf Node 2 ]      [ Leaf Node 3 ]
(Sensors + Camera)   (Sensors + Camera)   (Sensors + Camera)
        \                   |                   /
         \ [LoRa / UDP]     | [LoRa / UDP]     / [LoRa / UDP]
          v                 v                 v
                 +-----------------------+
                 |    Sub-Master Node    |  <-- Cluster Aggregator / Verifier
                 +-----------------------+
                             |
                             | [Long-Range LoRaWAN / UDP Mesh]
                             v
                 +-----------------------+
                 |      Master Node      |  <-- Field Gateway & Uplink
                 |  (Buffering & Purge)  |
                 +-----------------------+
                             |
                             | [HTTPS POST / Cellular / Satellite Link]
                             v
                 +-----------------------+
                 |    Central Web API    |  <-- web/api.php
                 |  & Ranger Dashboard   |
                 +-----------------------+
```

### Supported Node Roles (`core/roles.py`)

1. **`LeafNode`**:
   * **Duty Cycle**: Periodically wakes up, reads environmental sensors (temperature, humidity), captures an image, and invokes local quantized VLM inference.
   * **Anomaly Filtering**: If the model classifies the environment as `"nothing"` (normal, healthy forest), no heavy telemetry is generated. If a threat is detected (`fire`, `disease`, `pest`, `logging`), an alert packet is transmitted to the parent Sub-Master or Master.
2. **`SubMasterNode`**:
   * **Aggregation**: Listens for incoming UDP or LoRa packets from leaf nodes in its geographical cluster.
   * **Relay & Verification**: Collates cluster health heartbeats, aggregates duplicate alarms, and forwards structured packets to the MasterNode.
3. **`MasterNode`**:
   * **Central Field Gateway**: Connects directly to the cloud/backend API (`web/api.php`).
   * **Standalone Mode**: Can operate as an autonomous all-in-one sensor + VLM inference node (`"standalone": true` in `config.json`).
   * **Offline Store-and-Forward Buffering**: If cloud connectivity drops, failed HTTP requests are appended to a persistent local cache (`alerts_buffer.json`) and automatically flushed once connectivity recovers.
   * **Storage Retention (`purge_old_images`)**: Automatically deletes captured camera images older than 3 days (259,200 seconds) to prevent SD card exhaustion.

---

## Directory & Component Breakdown

```
raspberry/
├── main.py                    # Process entrypoint & lifecycle supervisor
├── config.json.template       # Template configuration for node roles & VLM paths
├── forestedge.service         # Systemd service unit for boot persistence
├── test_images/               # Curated sample library for offline verification (a-z.jpeg)
├── core/
│   ├── roles.py               # Node role implementations (Leaf, SubMaster, Master)
│   ├── network.py             # UDP mesh network driver for lab & cluster routing
│   └── lorawan.py             # Serial AT-command driver for LoRaWAN transceivers
├── drivers/
│   ├── hal_camera.py          # Multi-backend camera capture & auto-compression
│   └── hal_sensors.py         # Hardware I2C sensor driver with simulation fallback
└── inference/
    └── engine.py              # llama.cpp multimodal subprocess wrapper & JSON parser
```

---

## Hardware Abstraction Layer (HAL)

### 1. Camera Subsystem (`drivers/hal_camera.py`)

To ensure portability across hardware revisions and desktop development environments, `HalCamera` implements a robust **cascading capture fallback**:

1. **USB / V4L2 Webcams**: Attempts capture via `cv2.VideoCapture(0)`.
2. **Raspberry Pi CSI Cameras (Bookworm / Bullseye)**: Invokes `libcamera-still` via subprocess with `--width 300 --height 300`.
3. **Legacy CSI Cameras**: Invokes `rpicam-still`.
4. **Mock / Simulation Fallback**: If no camera is detected, samples a pseudo-random image from `test_images/`.

**Dynamic Compression & Downscaling**:
Captured images are automatically resized keeping aspect ratio to under `300x300` pixels and compressed as JPEG quality 85. This ensures the payload size stays within **10–20 KB**, minimizing VLM token processing time and enabling transmission over low-bandwidth links.

### 2. Environmental Sensors (`drivers/hal_sensors.py`)

* **Hardware Mode**: Communicates over I2C bus 1 via `smbus2` targeting integrated temperature/humidity sensors (such as AHT20, BME280, or DHT22).
* **Mock Mode**: If `smbus2` or physical I2C devices are unavailable, automatically produces plausible environmental values (temperature 20–30°C, relative humidity 40–60%) without crashing the process.

---

## Inference Engine (`inference/engine.py`)

The inference engine interfaces with `llama-mtmd-cli` (or compatible `llama.cpp` builds) to execute quantized multimodal models (`Qwen2-VL-2B-Instruct`, `Qwen3-VL-2B-Instruct`, or `LLaVA-v1.6`).

### System Prompt & Classification Contract

The engine enforces an objective arborist/ranger persona to minimize hallucination:

```json
{
  "Problem": "<fire|disease|pest|human|logging|nothing>",
  "Criticality": "<none|low|medium|high>",
  "Description": "<a short description, max 10 words>"
}
```

* **Criticality Levels**:
  * `none`: Normal, healthy forest conditions (`Problem: nothing`).
  * `low`: Isolated, small-scale anomaly (e.g., minor leaf spot, single stump, small smoke plume).
  * `medium`: Localized spread (e.g., concentrated pest cluster, active branch flame).
  * `high`: Widespread disaster (e.g., large canopy wildfire, heavy illegal logging machinery).

### Balanced Sampling Parameters

Inference defaults are tuned for deterministic, low-hallucination classification on edge CPUs:
* `temp`: `0.1` (low temperature for deterministic classification)
* `top_k`: `40`
* `top_p`: `0.9`
* `repeat_penalty`: `1.1`
* `n_predict`: `128` (bounded output tokens to preserve latency)

---

## Communication & Networking Stack

### 1. LoRaWAN UART Driver (`core/lorawan.py`)

Provides an AT-command interface for external LoRaWAN transceivers (e.g., **RAK3172**, **RYLR998**) connected via UART (`/dev/ttyS0` or `/dev/ttyUSB0` at 115200 baud). Handles OTTA join sequences and compact JSON packet transmission across kilometer-scale off-grid forest canopies.

### 2. UDP Mesh (`core/network.py`)

Used for lab validation, local IP mesh networks, and high-speed bench testing. Runs a background listener thread on a configurable port with non-blocking inbox queues and socket reuse.

---

## Configuration & Deployment Guide

### 1. Setup Configuration File

Copy the template configuration and modify it according to the node's deployment role:

```bash
cp config.json.template config.json
```

Example `config.json` for a standalone Master Node:

```json
{
    "role": "MasterNode",
    "cluster_id": "01",
    "node_id": "N01M01",
    "standalone": true,
    "inference_interval_seconds": 15,
    "ip": {
        "master_cloud_url": "https://your-dashboard-domain.com/api.php?key=YOUR_API_KEY"
    },
    "llama_flags": {
        "threads": 4,
        "n_predict": 128,
        "temp": 0.1,
        "top_k": 40,
        "top_p": 0.9,
        "repeat_penalty": 1.1,
        "model_path": "/opt/models/Qwen3VL-2B-Instruct-Q4_K_M.gguf",
        "mmproj_path": "/opt/models/mmproj-Qwen3VL-2B-Instruct-Q8_0.gguf",
        "bin_path": "/opt/llama.cpp/build/bin/llama-mtmd-cli"
    }
}
```

### 2. Manual Execution

```bash
# Run with default config.json
python3 main.py

# Or specify an alternate configuration file
python3 main.py /etc/forestedge/leaf_node_02.json
```

### 3. Systemd Daemon Deployment

To run ForestEdge OS as a managed background service:

1. Copy `forestedge.service` to systemd:
   ```bash
   sudo cp forestedge.service /etc/systemd/system/
   ```
2. Update paths and usernames inside `/etc/systemd/system/forestedge.service` if needed.
3. Enable and start the service:
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable forestedge.service
   sudo systemctl start forestedge.service
   ```
4. Inspect real-time logs:
   ```bash
   journalctl -u forestedge.service -f
   # Or view application logs
   tail -f logs/master.log
   ```

---

## Coding Agent Notes & Best Practices

* **Hardware Decoupling**: Never hardcode physical GPIO pin numbers, I2C bus indices, or tty device paths directly into business logic. Keep them configurable in `config.json` or pass them through constructor options.
* **Non-Blocking Operation**: Ensure all camera captures and socket reads have explicit timeouts so inference cycles cannot deadlock the main loop.
* **Storage Protection**: Always write temporary captures to `images/` and allow the Master node's automatic retention policy (`purge_old_images`) to manage disk capacity.
