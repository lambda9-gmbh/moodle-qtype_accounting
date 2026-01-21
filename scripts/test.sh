#!/bin/bash
# Run tests for the qtype_buchungssatz plugin
#
# Usage:
#   ./test.sh          # Run all tests (PHPUnit + Behat)
#   ./test.sh phpunit  # Run PHPUnit tests only
#   ./test.sh behat    # Run Behat tests only

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR/docker"

# Check if PHPUnit environment needs initialization
check_phpunit_init() {
    docker compose exec -T moodle test -f /var/www/html/phpunit.xml > /dev/null 2>&1
    return $?
}

# Check if Behat environment needs initialization
check_behat_init() {
    docker compose exec -T moodle test -f /var/www/behatdata/behatrun/behat/behat.yml > /dev/null 2>&1
    return $?
}

# Initialize PHPUnit environment
init_phpunit() {
    echo "Initializing PHPUnit test environment..."

    # Create phpunit directory
    docker compose exec -T moodle bash -c "
        mkdir -p /var/www/moodledata_phpu && \
        chown -R www-data:www-data /var/www/moodledata_phpu
    "

    # Initialize PHPUnit
    docker compose exec -T moodle bash -c "
        cd /var/www/html && \
        php admin/tool/phpunit/cli/init.php
    "
}

# Initialize Behat environment
init_behat() {
    echo "Initializing Behat test environment..."

    # Create behat directories
    docker compose exec -T moodle bash -c "
        mkdir -p /var/www/behatdata /var/www/behatdata/faildump && \
        chown -R www-data:www-data /var/www/behatdata
    "

    # Initialize Behat
    docker compose exec -T moodle bash -c "
        cd /var/www/html && \
        php admin/tool/behat/cli/init.php
    "
}

# Ensure Selenium is running
ensure_selenium() {
    if ! docker compose ps selenium 2>/dev/null | grep -q "running"; then
        echo "Starting Selenium container..."
        docker compose up -d selenium
        sleep 5
    fi
}

# Run PHPUnit tests
run_phpunit() {
    echo "========================================"
    echo "Running PHPUnit tests for qtype_buchungssatz..."
    echo "========================================"

    # Auto-initialize if needed
    if ! check_phpunit_init; then
        echo "PHPUnit not initialized. Running initialization..."
        init_phpunit
    fi

    # Use Moodle's testsuite which auto-discovers all tests in the plugin's tests/ directory
    docker compose exec -T moodle bash -c "
        cd /var/www/html && \
        php admin/tool/phpunit/cli/util.php --run --testsuite=qtype_buchungssatz_testsuite
    "
}

# Run Behat tests
run_behat() {
    echo "========================================"
    echo "Running Behat tests for qtype_buchungssatz..."
    echo "========================================"

    # Ensure Selenium is running
    ensure_selenium

    # Auto-initialize if needed
    if ! check_behat_init; then
        echo "Behat not initialized. Running initialization..."
        init_behat
    fi

    docker compose exec -T moodle bash -c "
        cd /var/www/html && \
        php admin/tool/behat/cli/run.php --tags=@qtype_buchungssatz
    "
}

# Parse command line arguments
case "${1:-all}" in
    phpunit)
        run_phpunit
        ;;
    behat)
        run_behat
        ;;
    all)
        run_phpunit
        echo ""
        run_behat
        ;;
    *)
        echo "Usage: $0 [phpunit|behat|all]"
        echo ""
        echo "Commands:"
        echo "  phpunit  Run PHPUnit tests only"
        echo "  behat    Run Behat acceptance tests only"
        echo "  all      Run all tests (default)"
        echo ""
        echo "Test environments are automatically initialized when needed."
        exit 1
        ;;
esac
