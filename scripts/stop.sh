#!/bin/bash
# Stop the Moodle development environment

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Stopping Moodle development environment..."

cd "$PROJECT_DIR/docker"
docker compose down

echo "Environment stopped."
