#!/bin/bash
# Reset the Moodle 3.10 development environment (removes all data)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "============================================"
echo "WARNING: This will delete all Moodle 3.10 data!"
echo "============================================"
echo ""
read -p "Are you sure you want to continue? (y/N) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

echo "Stopping Moodle 3.10 environment..."
cd "$PROJECT_DIR/docker"
docker compose -f docker-compose.moodle310.yml down -v

echo ""
echo "Moodle 3.10 environment has been reset."
echo "Run './scripts/start-moodle310.sh' to start fresh."
