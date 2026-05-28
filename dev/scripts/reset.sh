#!/bin/bash
# Reset the Moodle development environment (destroys all data)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "WARNING: This will destroy all Moodle data!"
read -p "Are you sure? (y/N): " confirm

if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Aborted."
    exit 0
fi

cd "$PROJECT_DIR/docker"

echo "Stopping containers..."
docker compose down

echo "Removing volumes..."
docker compose down -v

echo "Environment reset complete."
echo "Run ./scripts/start.sh to start fresh."
