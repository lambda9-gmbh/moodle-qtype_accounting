#!/bin/bash
# Run tests for the qtype_accounting plugin.
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

cd "$PROJECT_DIR" || exit 1

# Run a command inside the moodle container at /var/www/html.
in_moodle() {
    docker compose exec -T -w /var/www/html moodle "$@"
}

# Return the exit code of `util.php --diag` for the given tool (phpunit|behat).
# 0 means installed and up-to-date; anything else means (re)install needed.
diag_status() {
    local tool="$1"
    in_moodle php "admin/tool/${tool}/cli/util.php" --diag >/dev/null 2>&1
    echo $?
}

# Initialize PHPUnit environment (drops and reinstalls).
init_phpunit() {
    echo "Initializing PHPUnit test environment..."
    in_moodle bash -c "mkdir -p /var/www/moodledata_phpu && chown -R www-data:www-data /var/www/moodledata_phpu" || return
    in_moodle php admin/tool/phpunit/cli/init.php
}

# Initialize Behat environment (drops and reinstalls).
init_behat() {
    echo "Initializing Behat test environment..."
    in_moodle bash -c "mkdir -p /var/www/behatdata /var/www/behatdata/faildump && chown -R www-data:www-data /var/www/behatdata" || return
    in_moodle php admin/tool/behat/cli/init.php
}

# Ensure the PHPUnit environment is initialized and up-to-date. Reinstalls if needed.
ensure_phpunit_ready() {
    local status
    status=$(diag_status phpunit)
    if [ "${status}" != "0" ]; then
        echo "PHPUnit environment not ready (util.php --diag exit ${status}). Reinstalling..."
        init_phpunit || return
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
        init_behat || return
        status=$(diag_status behat)
        if [ "${status}" != "0" ]; then
            echo "ERROR: Behat environment still not ready after init (--diag exit ${status})."
            return 1
        fi
    fi
}

# Checks never start services; use the already running development stack.
ensure_selenium() {
    if ! docker compose ps --status running --services selenium | grep -qx selenium; then
        echo "Selenium is not running. Start the development environment explicitly before running Behat." >&2
        return 1
    fi
}

# WebDriver resolves upload paths in Selenium's filesystem, not Moodle's.
# Copy current plugin fixtures to the same absolute path before browser tests.
sync_behat_fixtures() {
    local fixturepath=/var/www/html/question/type/accounting/tests/fixtures
    docker compose exec -T --user root selenium mkdir -p "${fixturepath}" || return
    docker compose cp "${PROJECT_DIR}/../tests/fixtures/." "selenium:${fixturepath}"
}

# Run PHPUnit tests.
run_phpunit() {
    echo "========================================"
    echo "Running PHPUnit tests for qtype_accounting..."
    echo "========================================"

    ensure_phpunit_ready || return 1

    in_moodle php vendor/bin/phpunit --testsuite=qtype_accounting_testsuite --fail-on-warning "$@"
}

# Run Behat tests. Retries once if Behat reports the environment as outdated
# mid-run (this can happen if plugin code changed since the last init).
run_behat() {
    echo "========================================"
    echo "Running Behat tests for qtype_accounting..."
    echo "========================================"

    ensure_selenium || return
    sync_behat_fixtures || return
    ensure_behat_ready || return 1

    local output rc
    output=$(in_moodle php admin/tool/behat/cli/run.php --tags=@qtype_accounting --auto-rerun=2 --verbose "$@" 2>&1)
    rc=$?
    echo "${output}"

    if [ "${rc}" -ne 0 ] && echo "${output}" | grep -qiE "outdated|reinstall the Behat test site|test environment was initialised for a different version"; then
        echo ""
        echo "Behat reported the test site is outdated. Reinstalling and retrying..."
        init_behat || return
        in_moodle php admin/tool/behat/cli/run.php --tags=@qtype_accounting --auto-rerun=2 --verbose "$@"
        rc=$?
    fi

    return ${rc}
}

test_command="${1:-all}"
if [ "$#" -gt 0 ]; then
    shift
fi
case "${test_command}" in
    phpunit)
        run_phpunit "$@"
        ;;
    behat)
        run_behat "$@"
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
