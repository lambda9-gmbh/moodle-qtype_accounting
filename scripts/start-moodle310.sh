#!/bin/bash
# Start the Moodle 3.10 development environment for compatibility testing

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "============================================"
echo "Starting Moodle 3.10 Compatibility Environment"
echo "============================================"
echo ""
echo "This environment uses:"
echo "  - Moodle 3.10 (MOODLE_310_STABLE)"
echo "  - PHP 7.4"
echo "  - MariaDB 10.5"
echo ""
echo "First run will build the image and may take several minutes."
echo ""

cd "$PROJECT_DIR/docker"

# Build and start containers
docker compose -f docker-compose.moodle310.yml up -d --build

echo ""
echo "Waiting for database to be ready..."

# Wait for MariaDB to be healthy
timeout=120
elapsed=0
while [ $elapsed -lt $timeout ]; do
    if docker compose -f docker-compose.moodle310.yml ps mariadb310 | grep -q "healthy"; then
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
echo "Moodle 3.10 Development Environment"
echo "============================================"
echo ""
echo "Moodle 3.10:  http://localhost:8082"
echo "phpMyAdmin:   http://localhost:8083"
echo ""
echo "FIRST TIME SETUP:"
echo "  1. Go to http://localhost:8082"
echo "  2. Follow the Moodle installation wizard"
echo "  3. Database settings:"
echo "     - Type: MariaDB"
echo "     - Host: mariadb310"
echo "     - Name: moodle310"
echo "     - User: moodle"
echo "     - Password: moodle_password"
echo "  4. Data directory: /var/www/moodledata"
echo ""
echo "Plugin is mounted at:"
echo "  /var/www/html/question/type/buchungssatz"
echo ""
echo "To stop: ./scripts/stop-moodle310.sh"
echo "To view logs: ./scripts/logs-moodle310.sh"
echo "============================================"
