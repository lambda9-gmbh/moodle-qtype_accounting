#!/bin/bash
# Reset the PostgreSQL-backed dev stack (destroys postgres_data + moodledata).

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "WARNING: This will destroy the Postgres dev volume and Moodle data."
read -r -p "Are you sure? (y/N): " confirm

if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Aborted."
    exit 0
fi

cd "$PROJECT_DIR"

docker compose \
    -f docker-compose.yml \
    -f docker-compose.pgsql.yml \
    down -v

echo "PostgreSQL dev stack reset. Run ./dev/scripts/start-pgsql.sh to start fresh."
