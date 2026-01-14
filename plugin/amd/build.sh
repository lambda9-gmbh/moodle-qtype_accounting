#!/bin/bash
# Simple build script for AMD JavaScript files.
# Copies source files to build directory with .min.js extension.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="$SCRIPT_DIR/src"
BUILD_DIR="$SCRIPT_DIR/build"

# Create build directory if it doesn't exist.
mkdir -p "$BUILD_DIR"

# Copy all .js files from src to build with .min.js extension.
for file in "$SRC_DIR"/*.js; do
    if [ -f "$file" ]; then
        filename=$(basename "$file" .js)
        cp "$file" "$BUILD_DIR/${filename}.min.js"
        echo "Built: ${filename}.min.js"
    fi
done

echo "Build complete."

