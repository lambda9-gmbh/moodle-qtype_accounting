#!/bin/bash
# View logs for the Moodle 3.10 development environment

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR/docker"

if [ "$1" == "-f" ] || [ "$1" == "--follow" ]; then
    docker compose -f docker-compose.moodle310.yml logs -f
else
    docker compose -f docker-compose.moodle310.yml logs --tail=100
    echo ""
    echo "Use './scripts/logs-moodle310.sh -f' to follow logs in real-time"
fi
