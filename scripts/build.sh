#!/bin/bash
# Build script for AMD JavaScript files.
# Minifies source files and outputs to build directory with .min.js extension.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SRC_DIR="$PROJECT_DIR/plugin/amd/src"
BUILD_DIR="$PROJECT_DIR/plugin/amd/build"

# Create build directory if it doesn't exist.
mkdir -p "$BUILD_DIR"

# Check if terser is available locally
if command -v terser &> /dev/null; then
    USE_TERSER=true
# Check if npx is available (can run terser without global install)
elif command -v npx &> /dev/null; then
    USE_NPX=true
else
    USE_TERSER=false
    USE_NPX=false
fi

echo "========================================"
echo "Building AMD JavaScript modules..."
echo "========================================"

# Process all .js files from src
for file in "$SRC_DIR"/*.js; do
    if [ -f "$file" ]; then
        filename=$(basename "$file" .js)
        outfile="$BUILD_DIR/${filename}.min.js"

        if [ "$USE_TERSER" = true ]; then
            # Use terser for minification
            terser "$file" -c -m -o "$outfile" 2>/dev/null
            if [ $? -eq 0 ]; then
                echo "Minified: ${filename}.min.js"
            else
                # Fallback to copy if minification fails
                cp "$file" "$outfile"
                echo "Copied (minification failed): ${filename}.min.js"
            fi
        elif [ "$USE_NPX" = true ]; then
            # Use npx to run terser
            npx terser "$file" -c -m -o "$outfile" 2>/dev/null
            if [ $? -eq 0 ]; then
                echo "Minified: ${filename}.min.js"
            else
                # Fallback to copy if minification fails
                cp "$file" "$outfile"
                echo "Copied (minification failed): ${filename}.min.js"
            fi
        else
            # No minifier available, just copy
            cp "$file" "$outfile"
            echo "Copied: ${filename}.min.js (no minifier available)"
        fi
    fi
done

echo "========================================"
echo "Build complete."

if [ "$USE_TERSER" != true ] && [ "$USE_NPX" != true ]; then
    echo ""
    echo "Note: Install terser for proper minification:"
    echo "  npm install -g terser"
fi
