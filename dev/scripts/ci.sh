#!/bin/bash
# Unified CI for the qtype_accounting plugin.
#
# Runs PHPUnit + Behat first, then all moodle-plugin-ci checks.
# Fail-fast: the first failing step aborts the script with a non-zero exit
# code and "FAILED at step: <step-name>".
#
# Usage:
#   ./ci.sh                       # Full CI: tests + all checks
#   ./ci.sh test                  # Tests only (delegates to test.sh)
#   ./ci.sh test phpunit          # PHPUnit only
#   ./ci.sh test behat            # Behat only
#   ./ci.sh checks                # Lint/static-analysis set only (no tests)
#   ./ci.sh codecheck             # phpcs via .phpcs.xml.dist (moodle + moodle-extra)
#   ./ci.sh codecheck fix         # phpcbf auto-fix, then re-run phpcs
#   ./ci.sh codecheck summary     # phpcs summary report
#   ./ci.sh codecheck <path>      # phpcs on a specific path inside the plugin
#   ./ci.sh phpcs                 # Individual moodle-plugin-ci check (also: phpmd, phpdoc, ...)
#   ./ci.sh all                   # Default check set + phpcpd
#   ./ci.sh help                  # This message

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_ROOT="$(dirname "$PROJECT_DIR")"

CONTAINER="accounting-moodle"
PLUGIN_PATH="/var/www/html/question/type/accounting"
MOODLE_DIR="/var/www/html"
MPCI="/opt/moodle-plugin-ci/bin/moodle-plugin-ci"
PHPMD_BIN="/opt/moodle-plugin-ci/vendor/bin/phpmd"
PHPCS="/opt/moodle-cs/vendor/bin/phpcs"
PHPCBF="/opt/moodle-cs/vendor/bin/phpcbf"
# Paths excluded from phpmd because the rules don't apply:
#   - PHPUnit test classes (one public method per test case)
#   - Moodle's upgrade entry point (Moodle API requires the long-function pattern)
PHPMD_EXCLUDES="*/tests/*,*/db/upgrade.php"

die() {
    echo ""
    echo "============================================================"
    echo "FAILED at step: $1"
    echo "============================================================"
    exit 1
}

section() {
    echo ""
    echo "============================================================"
    echo "$1"
    echo "============================================================"
}

# Skip the container check and bootstrap when the user just asked for help.
case "${1:-}" in
    -h|--help|help)
        sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
        exit 0
        ;;
esac

if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    echo "Error: container '${CONTAINER}' is not running."
    echo "Start it with: cd ${PROJECT_DIR}/docker && docker compose up -d"
    exit 1
fi

# Ensure all CI tooling is installed inside the container. Idempotent — only
# downloads anything when a piece is missing. Survives container rebuilds.
ensure_tooling() {
    local need_apt=0 need_composer=0 need_node=0
    local need_moodlecs=0 need_mpci=0 need_npm=0 need_moodlecheck=0

    docker exec "${CONTAINER}" test -x /usr/local/bin/composer || need_composer=1
    docker exec "${CONTAINER}" test -x /usr/bin/node || need_node=1
    docker exec "${CONTAINER}" test -f /opt/moodle-cs/vendor/bin/phpcs || need_moodlecs=1
    docker exec "${CONTAINER}" test -f /opt/moodle-plugin-ci/bin/moodle-plugin-ci || need_mpci=1
    docker exec "${CONTAINER}" test -d /var/www/html/node_modules || need_npm=1
    docker exec "${CONTAINER}" test -f /var/www/html/local/moodlecheck/version.php || need_moodlecheck=1

    if [ ${need_composer} -eq 1 ] || [ ${need_node} -eq 1 ]; then
        need_apt=1
    fi

    if [ ${need_apt} -eq 0 ] && [ ${need_moodlecs} -eq 0 ] && [ ${need_mpci} -eq 0 ] \
        && [ ${need_npm} -eq 0 ] && [ ${need_moodlecheck} -eq 0 ]; then
        return 0
    fi

    section "Bootstrapping CI tooling inside ${CONTAINER}"

    if [ ${need_apt} -eq 1 ]; then
        echo "Installing system packages (curl, unzip)..."
        docker exec "${CONTAINER}" bash -c "apt-get update -qq && apt-get install -y -qq curl unzip" >/dev/null
    fi

    if [ ${need_composer} -eq 1 ]; then
        echo "Installing composer..."
        docker exec "${CONTAINER}" bash -c \
            "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --quiet"
    fi

    if [ ${need_node} -eq 1 ]; then
        echo "Installing Node.js 22..."
        docker exec "${CONTAINER}" bash -c \
            "curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null && apt-get install -y -qq nodejs >/dev/null"
    fi

    if [ ${need_moodlecs} -eq 1 ]; then
        echo "Installing moodle-cs at /opt/moodle-cs..."
        docker exec "${CONTAINER}" bash -c "mkdir -p /opt/moodle-cs && cat > /opt/moodle-cs/composer.json <<'EOF'
{
    \"require-dev\": {\"moodlehq/moodle-cs\": \"^3.7\"},
    \"config\": {\"allow-plugins\": {\"dealerdirect/phpcodesniffer-composer-installer\": true}}
}
EOF
cd /opt/moodle-cs && composer install --no-interaction --no-progress --quiet"
    fi

    if [ ${need_mpci} -eq 1 ]; then
        echo "Installing moodle-plugin-ci at /opt/moodle-plugin-ci..."
        docker exec "${CONTAINER}" bash -c \
            "composer create-project moodlehq/moodle-plugin-ci /opt/moodle-plugin-ci ^4 --no-interaction --no-progress --quiet || true"
    fi

    if [ ${need_npm} -eq 1 ]; then
        echo "Running 'npm install' in /var/www/html (needed for grunt)..."
        docker exec -w "${MOODLE_DIR}" "${CONTAINER}" npm install --no-audit --no-fund --silent
    fi

    if [ ${need_moodlecheck} -eq 1 ]; then
        echo "Installing local_moodlecheck plugin into Moodle..."
        docker exec "${CONTAINER}" bash -c \
            "cp -r /opt/moodle-plugin-ci/vendor/moodlehq/moodle-local_moodlecheck /var/www/html/local/moodlecheck \
                && chown -R www-data:www-data /var/www/html/local/moodlecheck \
                && php /var/www/html/admin/cli/upgrade.php --non-interactive >/dev/null"
    fi

    echo "Tooling ready."
}

# Run bootstrap before any subcommand needs it. Cheap when nothing is missing.
ensure_tooling

# Copy static text files (README.md, CHANGES.md, LICENSE) from the host into the
# container's plugin dir. We deliberately do NOT bind-mount these as single
# files: macOS Docker Desktop occasionally returns a stale filesize() for
# single-file bind mounts immediately after a host write, which trips Symfony
# Filesystem::copy() inside moodle-plugin-ci's grunt backup step. Copying via
# `docker cp` puts a fresh inode on the container's writable layer where the
# cache race can't happen. Re-run on every ci.sh invocation so edits flow.
sync_static_files() {
    for f in README.md CHANGES.md LICENSE; do
        if [ -f "${PLUGIN_ROOT}/${f}" ]; then
            docker cp "${PLUGIN_ROOT}/${f}" "${CONTAINER}:${PLUGIN_PATH}/${f}" >/dev/null
        fi
    done
}
sync_static_files

# Run a moodle-plugin-ci subcommand inside the container.
run() {
    docker exec -w "${MOODLE_DIR}" "${CONTAINER}" bash -c "MOODLE_DIR=${MOODLE_DIR} ${MPCI} $*"
}

# Call phpmd directly so we can pass --exclude (moodle-plugin-ci's phpmd
# subcommand does not expose that flag).
run_phpmd() {
    echo " RUN  PHP Mess Detector on qtype_accounting"
    docker exec -w "${MOODLE_DIR}" "${CONTAINER}" bash -c \
        "${PHPMD_BIN} ${PLUGIN_PATH} text ${PLUGIN_PATH}/.phpmd.xml --exclude ${PHPMD_EXCLUDES} --ignore-violations-on-exit"
}

# Stricter phpcs run using .phpcs.xml.dist (moodle + moodle-extra,
# amd/build/ excluded). Used by the `codecheck` subcommand.
run_phpcs_direct() {
    docker exec "${CONTAINER}" bash -c "cd ${PLUGIN_PATH} && ${PHPCS} --extensions=php,js --no-colors $*"
}
run_phpcbf_direct() {
    docker exec "${CONTAINER}" bash -c "cd ${PLUGIN_PATH} && ${PHPCBF} --extensions=php,js --no-colors $*"
}

# Run the lint/static-analysis suite. Fail-fast: aborts on first failure.
run_checks() {
    for cmd in phpcs phpmd phpdoc validate savepoints mustache phplint grunt; do
        section "Running: moodle-plugin-ci ${cmd}"
        if [ "${cmd}" = "phpmd" ]; then
            run_phpmd || die "${cmd}"
        else
            run "${cmd} ${PLUGIN_PATH}" || die "${cmd}"
        fi
    done
}

# Full CI: tests + checks. Fail-fast.
run_full_ci() {
    section "Running: tests (PHPUnit + Behat)"
    "${SCRIPT_DIR}/test.sh" all || die "test"

    run_checks

    echo ""
    echo "============================================================"
    echo "All CI steps passed."
    echo "============================================================"
}

case "${1:-default}" in
    ""|default)
        run_full_ci
        ;;
    checks)
        run_checks
        echo ""
        echo "============================================================"
        echo "All CI steps passed."
        echo "============================================================"
        ;;
    test)
        shift
        "${SCRIPT_DIR}/test.sh" "${@:-all}" || die "test"
        ;;
    codecheck)
        shift
        case "${1:-}" in
            ""|all)
                echo "Running phpcs (moodle + moodle-extra) on the plugin..."
                run_phpcs_direct "--report=full ."
                ;;
            summary)
                run_phpcs_direct "--report=summary ."
                ;;
            fix)
                echo "Auto-fixing with phpcbf..."
                run_phpcbf_direct "." || true
                echo ""
                echo "Re-running phpcs to show what is left..."
                run_phpcs_direct "--report=full ."
                ;;
            --)
                shift
                run_phpcs_direct "$*"
                ;;
            *)
                run_phpcs_direct "--report=full $(printf %q "$1")"
                ;;
        esac
        ;;
    all)
        run_checks
        section "Running: moodle-plugin-ci phpcpd"
        run "phpcpd ${PLUGIN_PATH}" || die "phpcpd"
        echo ""
        echo "============================================================"
        echo "All CI steps passed."
        echo "============================================================"
        ;;
    phpmd)
        run_phpmd
        ;;
    phpcs|phpcbf|phpdoc|phpcpd|phplint|validate|savepoints|mustache|grunt|parallel|install|behat|phpunit)
        cmd="$1"
        shift
        run "${cmd} ${PLUGIN_PATH} $*"
        ;;
    *)
        run "$*"
        ;;
esac
