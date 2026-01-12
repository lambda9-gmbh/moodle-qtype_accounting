#!/bin/bash
# Run PHPUnit tests for the plugin

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Running PHPUnit tests for local_moft..."

cd "$PROJECT_DIR/docker"

# Run tests inside the Moodle container
docker compose exec moodle bash -c "
    cd /var/www/html && \
    php admin/tool/phpunit/cli/init.php && \
    vendor/bin/phpunit --testsuite local_moft_testsuite
"
