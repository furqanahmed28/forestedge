#!/bin/bash
# ==============================================================================
# ForestEdge RISC-V QEMU Emulation Runner
# ==============================================================================
# Parameterized for reproducible execution across environments and CI runners.
#
# Environment Variables:
#   BUILDROOT_DIR      : Base buildroot directory (default: $HOME/buildroot)
#   BUILDROOT_IMAGES   : Path to output images directory (default: $BUILDROOT_DIR/output/images)
#   BUILDROOT_HOST     : Path to host tools directory (default: $BUILDROOT_DIR/output/host)
#   BIOS_PATH          : Path to OpenSBI fw_jump.bin (default: $BUILDROOT_IMAGES/fw_jump.bin)
#   KERNEL_PATH        : Path to kernel Image (default: $BUILDROOT_IMAGES/Image)
#   ROOTFS_PATH        : Path to rootfs.ext2 (default: $BUILDROOT_IMAGES/rootfs.ext2)
#   EXTRA_DATA_PATH    : Path to secondary disk image (default: $BUILDROOT_DIR/extra_data.qcow2)
#   VLEN               : Vector register length in bits (default: 128, e.g. 128, 256)
#   SMP                : Number of vCPUs (default: 4)
#   MEM                : Guest RAM allocation (default: 4G)
#   SSH_PORT           : Host port forwarded to guest SSH port 22 (default: 2222)
#
# Flags:
#   --serial-only      : Console redirected to current terminal via -nographic (default: true)
#   --use-system-qemu  : Use system-installed qemu-system-riscv64 rather than Buildroot host bin
# ==============================================================================

set -e

BUILDROOT_DIR="${BUILDROOT_DIR:-${HOME}/buildroot}"
BUILDROOT_IMAGES="${BUILDROOT_IMAGES:-${BUILDROOT_DIR}/output/images}"
BUILDROOT_HOST="${BUILDROOT_HOST:-${BUILDROOT_DIR}/output/host}"

BIOS_PATH="${BIOS_PATH:-${BUILDROOT_IMAGES}/fw_jump.bin}"
KERNEL_PATH="${KERNEL_PATH:-${BUILDROOT_IMAGES}/Image}"
ROOTFS_PATH="${ROOTFS_PATH:-${BUILDROOT_IMAGES}/rootfs.ext2}"
EXTRA_DATA_PATH="${EXTRA_DATA_PATH:-${BUILDROOT_DIR}/extra_data.qcow2}"

VLEN="${VLEN:-128}"
SMP="${SMP:-4}"
MEM="${MEM:-4G}"
SSH_PORT="${SSH_PORT:-2222}"

mode_serial=true
mode_sys_qemu=false

while [ "$1" ]; do
    case "$1" in
    --serial-only|serial-only) mode_serial=true; shift;;
    --use-system-qemu) mode_sys_qemu=true; shift;;
    --) shift; break;;
    *) echo "Unknown option: $1" >&2; exit 1;;
    esac
done

if ${mode_serial}; then
    EXTRA_ARGS='-nographic'
else
    EXTRA_ARGS=''
fi

if ! ${mode_sys_qemu}; then
    if [ -d "${BUILDROOT_HOST}/bin" ]; then
        export PATH="${BUILDROOT_HOST}/bin:${PATH}"
    fi
fi

# Build secondary drive args only if the image exists
EXTRA_DRIVE_ARGS=""
if [ -f "${EXTRA_DATA_PATH}" ]; then
    EXTRA_DRIVE_ARGS="-drive file=${EXTRA_DATA_PATH},format=qcow2,id=hd1,if=none -device virtio-blk-device,drive=hd1"
fi

# Execute QEMU System Emulation with RVV 1.0 (vector extension) & half-precision floating point
exec qemu-system-riscv64 \
    -M virt \
    -bios "${BIOS_PATH}" \
    -kernel "${KERNEL_PATH}" \
    -cpu "rv64,v=true,vlen=${VLEN},vext_spec=v1.0,zfh=true,zvfh=true,zvfhmin=true" \
    -smp "${SMP}" \
    -m "${MEM}" \
    -append "rootwait root=/dev/vda ro console=ttyS0" \
    -drive "file=${ROOTFS_PATH},format=raw,id=hd0,if=none" \
    -device virtio-blk-device,drive=hd0 \
    ${EXTRA_DRIVE_ARGS} \
    -netdev "user,id=net0,hostfwd=tcp::${SSH_PORT}-:22" \
    -device virtio-net-device,netdev=net0 \
    ${EXTRA_ARGS}
