#!/bin/bash
# Run PHPUnit tests for the plugin

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Running PHPUnit tests for qtype_buchungssatz..."

cd "$PROJECT_DIR/docker"

# Run tests inside the Moodle container
docker compose exec moodle bash -c "
    cd /var/www/html && \
    vendor/bin/phpunit question/type/buchungssatz/tests/question_test.php && \
    vendor/bin/phpunit question/type/buchungssatz/tests/questiontype_test.php
"
