#!/bin/bash
# Start the Moodle dev stack against PostgreSQL.
#
# Layered on top of dev/docker-compose.yml via dev/docker-compose.pgsql.yml.
# Uses a separate volume (postgres_data) so the MariaDB-backed stack remains
# intact and you can switch back with ./dev/scripts/start.sh.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "Starting Moodle dev environment against PostgreSQL..."

docker compose \
    -f docker-compose.yml \
    -f docker-compose.pgsql.yml \
    up -d --build postgres moodle selenium

echo ""
echo "Waiting for Postgres to be ready..."
timeout=60
elapsed=0
while [ $elapsed -lt $timeout ]; do
    if docker compose -f docker-compose.yml -f docker-compose.pgsql.yml ps postgres | grep -q "healthy"; then
        echo "Postgres is ready!"
        break
    fi
    echo -n "."
    sleep 3
    elapsed=$((elapsed + 3))
done

if [ $elapsed -ge $timeout ]; then
    echo ""
    echo "Warning: Postgres may still be starting."
fi

cat <<'EOF'

============================================
Moodle Dev Environment (PostgreSQL)
============================================

Moodle:   http://localhost:8080
Postgres: postgres:5432 inside the docker network

FIRST TIME SETUP:
  1. Go to http://localhost:8080
  2. Follow the Moodle installation wizard
  3. Database settings:
     - Type: PostgreSQL
     - Host: postgres
     - Name: moodle
     - User: moodle
     - Password: moodle_password

The phpunit_test DB (moodle_test) is created automatically on first boot
of the postgres container; if you started the stack before this script
existed, run ./dev/scripts/reset-pgsql.sh to recreate the volume.

To stop:        ./dev/scripts/stop-pgsql.sh
To switch back: ./dev/scripts/stop-pgsql.sh && ./dev/scripts/start.sh
============================================
EOF
