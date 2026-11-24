# Install script for directory: /home/furqan/llama.cpp/ggml

# Set the install prefix
if(NOT DEFINED CMAKE_INSTALL_PREFIX)
  set(CMAKE_INSTALL_PREFIX "/usr/local")
endif()
string(REGEX REPLACE "/$" "" CMAKE_INSTALL_PREFIX "${CMAKE_INSTALL_PREFIX}")

# Set the install configuration name.
if(NOT DEFINED CMAKE_INSTALL_CONFIG_NAME)
  if(BUILD_TYPE)
    string(REGEX REPLACE "^[^A-Za-z0-9_]+" ""
           CMAKE_INSTALL_CONFIG_NAME "${BUILD_TYPE}")
  else()
    set(CMAKE_INSTALL_CONFIG_NAME "Release")
  endif()
  message(STATUS "Install configuration: \"${CMAKE_INSTALL_CONFIG_NAME}\"")
endif()

# Set the component getting installed.
if(NOT CMAKE_INSTALL_COMPONENT)
  if(COMPONENT)
    message(STATUS "Install component: \"${COMPONENT}\"")
    set(CMAKE_INSTALL_COMPONENT "${COMPONENT}")
  else()
    set(CMAKE_INSTALL_COMPONENT)
  endif()
endif()

# Install shared libraries without execute permission?
if(NOT DEFINED CMAKE_INSTALL_SO_NO_EXE)
  set(CMAKE_INSTALL_SO_NO_EXE "1")
endif()

# Is this installation the result of a crosscompile?
if(NOT DEFINED CMAKE_CROSSCOMPILING)
  set(CMAKE_CROSSCOMPILING "TRUE")
endif()

# Set path to fallback-tool for dependency-resolution.
if(NOT DEFINED CMAKE_OBJDUMP)
  set(CMAKE_OBJDUMP "/home/furqan/buildroot/output/host/bin/riscv64-buildroot-linux-musl-objdump")
endif()

if(NOT CMAKE_INSTALL_LOCAL_ONLY)
  # Include the install script for the subdirectory.
  include("/home/furqan/llama.cpp/build-q8/ggml/src/cmake_install.cmake")
endif()

if(CMAKE_INSTALL_COMPONENT STREQUAL "Unspecified" OR NOT CMAKE_INSTALL_COMPONENT)
  file(INSTALL DESTINATION "${CMAKE_INSTALL_PREFIX}/lib" TYPE STATIC_LIBRARY FILES "/home/furqan/llama.cpp/build-q8/ggml/src/libggml.a")
endif()

if(CMAKE_INSTALL_COMPONENT STREQUAL "Unspecified" OR NOT CMAKE_INSTALL_COMPONENT)
  file(INSTALL DESTINATION "${CMAKE_INSTALL_PREFIX}/include" TYPE FILE FILES
    "/home/furqan/llama.cpp/ggml/include/ggml.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-cpu.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-alloc.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-backend.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-blas.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-cann.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-cpp.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-cuda.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-opt.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-metal.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-rpc.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-virtgpu.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-sycl.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-vulkan.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-webgpu.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-zendnn.h"
    "/home/furqan/llama.cpp/ggml/include/ggml-openvino.h"
    "/home/furqan/llama.cpp/ggml/include/gguf.h"
    )
endif()

if(CMAKE_INSTALL_COMPONENT STREQUAL "Unspecified" OR NOT CMAKE_INSTALL_COMPONENT)
  file(INSTALL DESTINATION "${CMAKE_INSTALL_PREFIX}/lib" TYPE STATIC_LIBRARY FILES "/home/furqan/llama.cpp/build-q8/ggml/src/libggml-base.a")
endif()

if(CMAKE_INSTALL_COMPONENT STREQUAL "Unspecified" OR NOT CMAKE_INSTALL_COMPONENT)
  file(INSTALL DESTINATION "${CMAKE_INSTALL_PREFIX}/lib/cmake/ggml" TYPE FILE FILES
    "/home/furqan/llama.cpp/build-q8/ggml/ggml-config.cmake"
    "/home/furqan/llama.cpp/build-q8/ggml/ggml-version.cmake"
    )
endif()

string(REPLACE ";" "\n" CMAKE_INSTALL_MANIFEST_CONTENT
       "${CMAKE_INSTALL_MANIFEST_FILES}")
if(CMAKE_INSTALL_LOCAL_ONLY)
  file(WRITE "/home/furqan/llama.cpp/build-q8/ggml/install_local_manifest.txt"
     "${CMAKE_INSTALL_MANIFEST_CONTENT}")
endif()
