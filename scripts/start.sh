#!/bin/bash
# Start the Moodle development environment

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Starting Moodle development environment..."
echo "First run will build the image and may take several minutes."

cd "$PROJECT_DIR/docker"

# Build and start containers
docker compose --profile testing up -d --build

echo ""
echo "Waiting for database to be ready..."

# Wait for MariaDB to be healthy
timeout=120
elapsed=0
while [ $elapsed -lt $timeout ]; do
    if docker compose ps mariadb | grep -q "healthy"; then
        echo "Database is ready!"
        break
    fi
    echo -n "."
    sleep 5
    elapsed=$((elapsed + 5))
done

if [ $elapsed -ge $timeout ]; then
    echo ""
    echo "Warning: Database may still be starting."
fi

echo ""
echo "============================================"
echo "Moodle Development Environment"
echo "============================================"
echo ""
echo "Moodle:      http://localhost:8080"
echo "phpMyAdmin:  http://localhost:8081"
echo ""
echo "FIRST TIME SETUP:"
echo "  1. Go to http://localhost:8080"
echo "  2. Follow the Moodle installation wizard"
echo "  3. Database settings:"
echo "     - Type: MariaDB"
echo "     - Host: mariadb"
echo "     - Name: moodle"
echo "     - User: moodle"
echo "     - Password: moodle_password"
echo "  4. Data directory: /var/www/moodledata"
echo ""
echo "Plugin Location (after install):"
echo "  Site administration > Plugins > Local plugins"
echo ""
echo "To stop: ./scripts/stop.sh"
echo "To view logs: ./scripts/logs.sh"
echo "============================================"
