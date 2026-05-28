#!/bin/bash
# Stop the PostgreSQL-backed dev stack (leaves volumes intact).

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "Stopping Moodle dev environment (PostgreSQL stack)..."

docker compose \
    -f docker-compose.yml \
    -f docker-compose.pgsql.yml \
    down

echo "Environment stopped."
