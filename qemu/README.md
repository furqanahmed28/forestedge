# ForestEdge — Virtualized RISC-V Emulation & RVV 1.0 Acceleration (`qemu/`)

This directory houses the virtualized execution testbed and accelerated inference binaries for **ForestEdge**. It provides the infrastructure to evaluate quantized small language models (SLMs) and vision-language models (VLMs) on **RISC-V 64-bit architectures with Vector Extension 1.0 (RVV 1.0)** using QEMU emulation and Buildroot embedded Linux images, without requiring physical silicon.

---

## Important Notice: Binary-Only Distribution

> [!IMPORTANT]
> **No Source Code or Patch Scripts in this Subdirectory**:
> The contents of `llama.cpp_q8_build_rvv/` are distributed as a **precompiled binary build tree** compiled with a Buildroot toolchain (`riscv64-buildroot-linux-musl-gcc`, `-march=rv64gcv -mabi=lp64d`, Release build). The original C/C++ source code, git repository, and custom patch files are maintained in an external upstream workspace and are **not** bundled here.
> 
> **Coding Agent Guidance**: Do not attempt to recompile or modify source code in this directory, nor search for missing patch scripts. All testing, benchmarking, and execution should be performed using the precompiled binaries and the provided QEMU runner script (`qemu_run_Script.sh`).

### Precompiled Binaries in `llama.cpp_q8_build_rvv/bin/`

| Binary | Description | Primary Use Case in ForestEdge |
| :--- | :--- | :--- |
| `llama-bench` | Microkernel and end-to-end benchmark utility | Benchmarking `MUL_MAT` dot-products, token throughput, and prompt processing times across vector lengths |
| `llama-cli` | Standalone CLI for text-based autoregressive models | Testing language-only SLMs (e.g., Llama-3.2-1B, Qwen2.5-0.5B/1.5B) under emulation |
| `llama-mtmd-cli` | Multimodal CLI with projector (`mmproj`) support | Running quantized Vision-Language Models (Qwen2-VL-2B, Qwen3-VL-2B, LLaVA) on forest anomaly images |

---

## Architectural Context & Emulation Tooling

The goal of this sub-component is to validate edge GenAI feasibility on open-standard RISC-V hardware before deploying to physical edge micro-gateways:

* **QEMU System Emulation (`qemu-system-riscv64`)**: Boots a complete Buildroot-generated Linux kernel (`Image`) with OpenSBI firmware (`fw_jump.bin`), an ext2 root filesystem, and emulated VirtIO networking and storage.
* **QEMU User Emulation (`qemu-riscv64`)**: Executes user-space RISC-V ELF binaries directly on the host OS for fast microkernel regression and loop-level debugging.
* **RVV 1.0 & Half-Precision Hardware Features**: Emulates the `rv64gcv` architecture, specifically targeting:
  * `v=true, vext_spec=v1.0`: Standard Vector Extension specification version 1.0.
  * `vlen=128` (or `256`): Hardware vector register length in bits.
  * `zfh=true, zvfh=true, zvfhmin=true`: Scalar and vector IEEE 754 half-precision (FP16) extensions.

---

## Optimization Approach (`llama.cpp_q8_build_rvv`)

The primary computational bottleneck in running quantized models on edge RISC-V processors is the matrix multiplication (`MUL_MAT`) kernel between 8-bit quantized weights (`Q8_0`) and quantized activations.

```
                  +----------------------------------------------+
                  |  Q8_0 Block (QK8_0 = 32 elements)           |
                  |  - 32 x int8 quantized values                |
                  |  - 1  x FP16 / FP32 block scaling factor (d) |
                  +----------------------------------------------+
                                         |
                       [ 4-Way Loop Unrolled Vector Load ]
                                         |
                                         v
                         vle8.v (Vector Load 8-bit Ints)
                                         |
                                         v
                      vwmul.vv (Widening Multiply: int8 -> int16)
                                         |
                                         v
                    vwredsum.vs (Widening Reduction: int16 -> int32)
                                         |
                                         v
                  Scalar Scaling Factor Multiply (d_weight * d_act)
```

### Microkernel Strategy

1. **4-Way Loop Unrolling**: The RVV microkernel processes four 32-element blocks simultaneously, hiding load latency and maximizing pipeline fill on emulated vector units.
2. **Widening Vector Multiplies (`vwmul`)**: Performs signed 8-bit multiplication into 16-bit intermediate accumulators (`int8 * int8 -> int16`), preventing overflow without requiring expensive pre-conversions.
3. **Widening Reductions (`vwredsum.vs`)**: Reduces 16-bit products into 32-bit vector accumulators across vector lanes before returning to scalar registers.
4. **VLEN-Agnostic Design**: Dynamically interrogates the vector register width at runtime using `__riscv_vlenb()` and configures stripmining loops via `vsetvl`. The same binary dynamically scales across hardware configurations with `VLEN=128`, `VLEN=256`, or wider.

---

## Observed Benchmark Stats (QEMU Evaluation)

Extensive benchmarking was performed across quantized SLMs and VLMs (`Qwen2-VL-2B-Instruct`, `Qwen3-VL-2B-Instruct`, `LLaVA`, `Llama-3.2-1B`, and `Qwen2.5-0.5B` at `Q8_0` / `Q4_K_M` quantizations):

| Test Environment | Metric / Workload | Baseline (Scalar / Unoptimized) | Optimized RVV 1.0 Microkernel | Observed Speedup / Delta |
| :--- | :--- | :--- | :--- | :--- |
| **QEMU User-Mode** | `MUL_MAT` Q8_0 Kernel Latency | Baseline median latency | Optimized median latency | **43% median latency reduction** |
| **QEMU User-Mode** | End-to-End Prompt Processing | Baseline t/s | RVV accelerated t/s | **Up to 1.80× throughput speedup** |
| **QEMU System-Mode** | Guest Linux OS Inference (`smp=4`) | Baseline token execution | RVV accelerated execution | **~1.25× consistent speedup** |
| **Wider Vector (`VLEN=256`)** | System-mode scaling | Compared against `VLEN=128` | Emulation translation overhead | *Diminishing relative gains under QEMU TCG* |

> [!NOTE]
> **QEMU TCG Translation Dynamic**:
> In physical silicon with wide SIMD pipelines, `VLEN=256` or `VLEN=512` typically delivers substantial throughput gains. Under QEMU Tiny Code Generator (TCG) software emulation, widening the vector length increases dynamic translation and register-splitting overhead on the host x86_64 CPU, resulting in a moderate 1.25× gain rather than the higher theoretical speedups expected on bare-metal RVV ASICs.

---

## Execution Runner: `qemu_run_Script.sh`

The runner script launches the full RISC-V virtual machine with pre-configured CPU vector flags, console routing, and host network port forwarding.

### Configuration via Environment Variables

The script avoids hardcoded personal paths. You can customize all image and system parameters using environment variables:

```bash
# Optional: Set paths to your Buildroot output artifacts
export BUILDROOT_DIR="/path/to/buildroot"
export BUILDROOT_IMAGES="${BUILDROOT_DIR}/output/images"
export BIOS_PATH="${BUILDROOT_IMAGES}/fw_jump.bin"
export KERNEL_PATH="${BUILDROOT_IMAGES}/Image"
export ROOTFS_PATH="${BUILDROOT_IMAGES}/rootfs.ext2"

# System sizing parameters
export VLEN=128       # Vector length in bits (128 or 256)
export SMP=4          # Number of emulated CPU cores
export MEM="4G"       # Emulated guest RAM
export SSH_PORT=2222  # Forwarded host port for SSH
```

### Launching the VM

```bash
# Terminal console mode (default, redirects serial console to your shell)
./qemu_run_Script.sh --serial-only

# Use system-installed qemu-system-riscv64 instead of Buildroot host toolchain
./qemu_run_Script.sh --use-system-qemu
```

To exit QEMU in `-nographic` mode, press `Ctrl+A` followed by `X`.

### Interacting with the Guest

Once the VM has booted:

1. **SSH from Host**:
   ```bash
   ssh -p 2222 root@localhost
   ```
2. **Transfer Models or Data via SCP**:
   ```bash
   scp -P 2222 /local/path/to/model.gguf root@localhost:/root/
   ```

---

## Reproducible Benchmarking Guide

### 1. Running `llama-bench` Inside QEMU System Guest

```bash
# Test Q8_0 matrix multiplication performance across batch sizes and thread counts
./llama.cpp_q8_build_rvv/bin/llama-bench \
    -m /root/models/Qwen2.5-0.5B-Instruct-Q8_0.gguf \
    -p 128,512 \
    -n 32,128 \
    -t 4
```

### 2. Running Multimodal VLM Inference (`llama-mtmd-cli`)

To run inference on an anomaly test image (e.g., detecting forest fire or leaf disease):

```bash
./llama.cpp_q8_build_rvv/bin/llama-mtmd-cli \
    -m /root/models/Qwen3VL-2B-Instruct-Q4_K_M.gguf \
    --mmproj /root/models/mmproj-Qwen3VL-2B-Instruct-Q8_0.gguf \
    --image /root/test_images/forest_fire_sample.jpg \
    -sys "You are an expert arborist and forest ranger. Analyze the image and return a valid JSON object." \
    -p "Identify if this image shows normal conditions, fire, disease, or logging." \
    -n 128 \
    --temp 0.1 \
    -t 4
```

---

## Agent & Contributor Checklist

* **Do Not Commit Personal Paths**: Keep all runner invocations parameterized with environment variables or generic relative paths.
* **Binary Integrity**: Do not commit partial re-builds or overwrite `llama.cpp_q8_build_rvv/bin` without documenting toolchain version and compilation flags.
* **Model Storage**: Never commit `.gguf` weight files into git. Mount models externally using VirtIO disks (`EXTRA_DATA_PATH`) or load them via SSH/SCP.
