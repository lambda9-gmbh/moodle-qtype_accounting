#!/bin/bash
# Run tests for the qtype_buchungssatz plugin.
#
# Auto-initializes and auto-reinstalls the PHPUnit / Behat test environments
# when they are missing or outdated (e.g. after a plugin upgrade or container
# rebuild). Diagnosis is done via Moodle's own util.php --diag.
#
# Usage:
#   ./test.sh          # Run all tests (PHPUnit + Behat)
#   ./test.sh phpunit  # PHPUnit only
#   ./test.sh behat    # Behat only

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR/docker"

# Run a command inside the moodle container at /var/www/html.
in_moodle() {
    docker compose exec -T moodle bash -c "cd /var/www/html && $*"
}

# Return the exit code of `util.php --diag` for the given tool (phpunit|behat).
# 0 means installed and up-to-date; anything else means (re)install needed.
diag_status() {
    local tool="$1"
    in_moodle "php admin/tool/${tool}/cli/util.php --diag" >/dev/null 2>&1
    echo $?
}

# Initialize PHPUnit environment (drops and reinstalls).
init_phpunit() {
    echo "Initializing PHPUnit test environment..."
    in_moodle "mkdir -p /var/www/moodledata_phpu && chown -R www-data:www-data /var/www/moodledata_phpu"
    in_moodle "php admin/tool/phpunit/cli/init.php"
}

# Initialize Behat environment (drops and reinstalls).
init_behat() {
    echo "Initializing Behat test environment..."
    in_moodle "mkdir -p /var/www/behatdata /var/www/behatdata/faildump && chown -R www-data:www-data /var/www/behatdata"
    in_moodle "php admin/tool/behat/cli/init.php"
}

# Ensure the PHPUnit environment is initialized and up-to-date. Reinstalls if needed.
ensure_phpunit_ready() {
    local status
    status=$(diag_status phpunit)
    if [ "${status}" != "0" ]; then
        echo "PHPUnit environment not ready (util.php --diag exit ${status}). Reinstalling..."
        init_phpunit
        status=$(diag_status phpunit)
        if [ "${status}" != "0" ]; then
            echo "ERROR: PHPUnit environment still not ready after init (--diag exit ${status})."
            return 1
        fi
    fi
}

# Ensure the Behat environment is initialized and up-to-date. Reinstalls if needed.
ensure_behat_ready() {
    local status
    status=$(diag_status behat)
    if [ "${status}" != "0" ]; then
        echo "Behat environment not ready (util.php --diag exit ${status}). Reinstalling..."
        init_behat
        status=$(diag_status behat)
        if [ "${status}" != "0" ]; then
            echo "ERROR: Behat environment still not ready after init (--diag exit ${status})."
            return 1
        fi
    fi
}

# Ensure Selenium is running.
ensure_selenium() {
    if ! docker compose ps selenium 2>/dev/null | grep -q "running"; then
        echo "Starting Selenium container..."
        docker compose up -d selenium
        sleep 5
    fi
}

# Run PHPUnit tests.
run_phpunit() {
    echo "========================================"
    echo "Running PHPUnit tests for qtype_buchungssatz..."
    echo "========================================"

    ensure_phpunit_ready || return 1

    in_moodle "php admin/tool/phpunit/cli/util.php --run --testsuite=qtype_buchungssatz_testsuite"
}

# Run Behat tests. Retries once if Behat reports the environment as outdated
# mid-run (this can happen if plugin code changed since the last init).
run_behat() {
    echo "========================================"
    echo "Running Behat tests for qtype_buchungssatz..."
    echo "========================================"

    ensure_selenium
    ensure_behat_ready || return 1

    local output rc
    output=$(in_moodle "php admin/tool/behat/cli/run.php --tags=@qtype_buchungssatz" 2>&1)
    rc=$?
    echo "${output}"

    if [ "${rc}" -ne 0 ] && echo "${output}" | grep -qiE "outdated|reinstall the Behat test site|test environment was initialised for a different version"; then
        echo ""
        echo "Behat reported the test site is outdated. Reinstalling and retrying..."
        init_behat
        in_moodle "php admin/tool/behat/cli/run.php --tags=@qtype_buchungssatz"
        rc=$?
    fi

    return ${rc}
}

case "${1:-all}" in
    phpunit)
        run_phpunit
        ;;
    behat)
        run_behat
        ;;
    all)
        run_phpunit && \
            echo "" && \
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
        echo "Test environments are auto-initialized and auto-reinstalled"
        echo "when Moodle reports them as missing or outdated."
        exit 1
        ;;
esac
