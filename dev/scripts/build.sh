#!/bin/bash
# Build script for AMD JavaScript files.
#
# Runs Moodle's own grunt-based AMD pipeline (Babel ES6 -> AMD define + minify)
# inside the moft-moodle container. The previous terser-only approach left
# raw `import` statements in build/*.min.js, which RequireJS cannot execute
# and which breaks Moodle's JS pipeline site-wide.
#
# Requirements (one-time setup inside the moft-moodle container):
#   - Node.js (matching Moodle's package.json engines field, currently >=22 <23)
#   - npm install run in /var/www/html
# After those exist, this script is the only thing needed to rebuild.

set -e

CONTAINER="moft-moodle"
PLUGIN_PATH="/var/www/html/question/type/buchungssatz"

echo "========================================"
echo "Building AMD JavaScript modules via grunt..."
echo "========================================"

if ! command -v docker &> /dev/null; then
    echo "Error: docker is not available on this host." >&2
    exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
    echo "Error: container '$CONTAINER' is not running." >&2
    echo "Start the dev environment first: docker compose up -d" >&2
    exit 1
fi

if ! docker exec "$CONTAINER" test -d "$PLUGIN_PATH/amd/src"; then
    echo "Error: $PLUGIN_PATH/amd/src not found in container." >&2
    echo "Is the plugin mounted into the container?" >&2
    exit 1
fi

if ! docker exec "$CONTAINER" sh -c 'command -v npx >/dev/null'; then
    echo "Error: npx not found in container. Install Node.js (>=22 <23) inside '$CONTAINER':" >&2
    echo "  docker exec $CONTAINER bash -c 'curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt-get install -y nodejs'" >&2
    exit 1
fi

if ! docker exec "$CONTAINER" test -d /var/www/html/node_modules; then
    echo "Error: /var/www/html/node_modules missing in container. Run grunt deps install:" >&2
    echo "  docker exec $CONTAINER bash -c 'cd /var/www/html && npm install'" >&2
    exit 1
fi

# grunt rollup (not grunt amd) skips eslint, which we don't want to gate the build on.
# The rollup task is what actually transforms ES6 modules to AMD and minifies.
docker exec "$CONTAINER" bash -c "cd $PLUGIN_PATH && npx grunt rollup"

echo "========================================"
echo "Build complete."
