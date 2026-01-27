#!/bin/bash
# Stop the Moodle 3.10 development environment

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Stopping Moodle 3.10 development environment..."

cd "$PROJECT_DIR/docker"
docker compose -f docker-compose.moodle310.yml down

echo "Moodle 3.10 environment stopped."
