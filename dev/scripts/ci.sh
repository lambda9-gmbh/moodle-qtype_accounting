#!/bin/bash
# Unified CI for the qtype_accounting plugin.
#
# Runs the GitHub Actions checks against the current working tree, then PHPUnit + Behat.
# Fail-fast: the first failing step aborts the script with a non-zero exit
# code and "FAILED at step: <step-name>".
#
# Usage:
#   ./ci.sh                       # GitHub-equivalent checks + PHPUnit + Behat
#   ./ci.sh test                  # Tests only (delegates to test.sh)
#   ./ci.sh test phpunit          # PHPUnit only
#   ./ci.sh test behat            # Behat only
#   ./ci.sh checks                # Static checks + standalone JavaScript regressions
#   ./ci.sh js                    # Standalone JavaScript regressions only
#   ./ci.sh scope                 # Verify and fingerprint scanner input files
#   ./ci.sh codecheck             # phpcs via .phpcs.xml.dist (moodle + moodle-extra)
#   ./ci.sh codecheck fix         # phpcbf auto-fix, then re-run phpcs
#   ./ci.sh codecheck summary     # phpcs summary report
#   ./ci.sh codecheck <path>      # phpcs on a specific path inside the plugin
#   ./ci.sh phpcs                 # Individual moodle-plugin-ci check (also: phpmd, phpdoc, ...)
#   ./ci.sh all                   # Check set + optional phpmd and phpcpd
#   ./ci.sh help                  # This message

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_ROOT="$(dirname "$PROJECT_DIR")"

CONTAINER="accounting-moodle"
PLUGIN_PATH="/var/www/html/question/type/accounting"
LIVE_PLUGIN_PATH="${PLUGIN_PATH}"
MOODLE_DIR="/var/www/html"
MPCI="/opt/moodle-plugin-ci/bin/moodle-plugin-ci"
PHPMD_BIN="/opt/moodle-plugin-ci/vendor/bin/phpmd"
PHPCS="/opt/moodle-cs/vendor/bin/phpcs"
PHPCBF="/opt/moodle-cs/vendor/bin/phpcbf"
# Paths excluded from phpmd because the rules don't apply:
#   - PHPUnit test classes (one public method per test case)
#   - Moodle's upgrade entry point (Moodle API requires the long-function pattern)
PHPMD_EXCLUDES="*/tests/*,*/db/upgrade.php,*/dev/*"

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
        sed -n '2,/^set -/p' "$0" | sed '$d; s/^# \{0,1\}//'
        exit 0
        ;;
esac

if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    echo "Error: container '${CONTAINER}' is not running."
    echo "Start the development environment explicitly before running CI."
    exit 1
fi

# Check existing tooling without installing dependencies or changing the site.
ensure_tooling() {
    local tool
    for tool in "${MPCI}" "${PHPCS}" "${PHPMD_BIN}"; do
        if ! docker exec "${CONTAINER}" test -x "${tool}"; then
            echo "Missing CI tool: ${tool}. Provision it in the development environment before running CI." >&2
            return 1
        fi
    done
    if ! docker exec "${CONTAINER}" test -d "${MOODLE_DIR}/node_modules"; then
        echo "Moodle's Grunt dependencies are missing. Provision the development environment before running CI." >&2
        return 1
    fi
    if ! docker exec "${CONTAINER}" test -f "${MOODLE_DIR}/local/moodlecheck/version.php"; then
        echo "The local_moodlecheck plugin is missing. Provision the development environment before running CI." >&2
        return 1
    fi
}
ensure_tooling || die "prerequisites"

# Scan a complete copy of tracked and untracked working files, including dev/.
# Gitignored files and deleted paths are omitted; export-ignore is deliberately
# not used, since GitHub checks repository sources rather than release archives.
snapshot_root=$(docker exec "${CONTAINER}" mktemp -d /tmp/accounting-ci.XXXXXX) || die "create source snapshot"
trap 'docker exec "$CONTAINER" rm -rf -- "$snapshot_root" >/dev/null' EXIT
SCAN_MOODLE_DIR="${snapshot_root}/moodle"
# Preserve Moodle's directory ancestry: moodle-cs discovers its version and
# components from the scanned file's location, not the MOODLE_DIR environment.
# Only the plugin is copied; the surrounding core files reuse the installation.
PLUGIN_PATH=$(docker exec -i "${CONTAINER}" bash -s -- "${MOODLE_DIR}" "${SCAN_MOODLE_DIR}" <<'SCAN_WORKSPACE'
set -euo pipefail
source_root=$1
scan_root=$2
link_directory() {
    local source=$1 target=$2 excluded=$3 entry
    mkdir -p "$target"
    shopt -s dotglob nullglob
    for entry in "$source"/*; do
        if [ "${entry##*/}" != "$excluded" ]; then
            ln -s "$entry" "$target/${entry##*/}"
        fi
    done
}
if [ -f "$source_root/public/version.php" ]; then
    link_directory "$source_root" "$scan_root" public
    link_directory "$source_root/public" "$scan_root/public" question
    source_root="$source_root/public"
    scan_root="$scan_root/public"
else
    link_directory "$source_root" "$scan_root" question
fi
link_directory "$source_root/question" "$scan_root/question" type
link_directory "$source_root/question/type" "$scan_root/question/type" accounting
mkdir -p "$scan_root/question/type/accounting"
printf '%s\n' "$scan_root/question/type/accounting"
SCAN_WORKSPACE
) || die "create scanner workspace"
sync_working_tree() {
    docker exec "${CONTAINER}" mkdir -p "${PLUGIN_PATH}" || return
    (
        cd "${PLUGIN_ROOT}" || exit 1
        git ls-files --cached --others --exclude-standard -z |
            while IFS= read -r -d '' file; do
                if [ -f "${file}" ]; then
                    printf '%s\0' "${file}"
                fi
            done |
            tar --null -T - -cf -
    ) | docker exec -i "${CONTAINER}" tar -xf - -C "${PLUGIN_PATH}"
}
sync_working_tree || die "sync working files"

run_scope_check() {
    docker exec "${CONTAINER}" php "${PLUGIN_PATH}/dev/scripts/ci-files.php" \
        --autoload=/opt/moodle-plugin-ci/vendor/autoload.php --plugin="${PLUGIN_PATH}" \
        --moodle="${SCAN_MOODLE_DIR}" --format=verify
}

run_phpcs() {
    docker exec "${CONTAINER}" php "${PLUGIN_PATH}/dev/scripts/ci-files.php" \
        --autoload=/opt/moodle-plugin-ci/vendor/autoload.php --plugin="${PLUGIN_PATH}" \
        --moodle="${SCAN_MOODLE_DIR}" --format=context || return
    run phpcs --max-warnings 0 "${PLUGIN_PATH}" "$@"
}

# Run a moodle-plugin-ci subcommand inside the container.
run() {
    docker exec -w "${MOODLE_DIR}" "${CONTAINER}" env "MOODLE_DIR=${MOODLE_DIR}" "${MPCI}" "$@"
}

# Grunt's backup/restore must not traverse Docker Desktop bind mounts: stale
# filesize() metadata there can make Symfony reject a correctly copied file.
# Reuse Moodle's installed build tools around a container-native plugin copy.
run_grunt() {
    local buildroot="${snapshot_root}/grunt-moodle"
    docker exec -i "${CONTAINER}" bash -s -- "${MOODLE_DIR}" "${PLUGIN_PATH}" "${buildroot}" <<'GRUNT_WORKSPACE' || return
set -euo pipefail
moodle_dir=$1
source_dir=$2
build_dir=$3
mkdir -p "$build_dir/question/type/accounting"
for directory in node_modules .grunt lib; do
    ln -s "$moodle_dir/$directory" "$build_dir/$directory"
done
for file in Gruntfile.js package.json package-lock.json version.php .eslintrc .eslintignore \
    .stylelintrc .stylelintignore .gherkin-lintrc .jshintrc .jshintignore .shifter.json; do
    if [ -f "$moodle_dir/$file" ]; then
        ln -s "$moodle_dir/$file" "$build_dir/$file"
    fi
done
cp -a "$source_dir/." "$build_dir/question/type/accounting/"
GRUNT_WORKSPACE
    run grunt --max-lint-warnings 0 --moodle="${buildroot}" "${buildroot}/question/type/accounting" "$@"
}

# Call phpmd directly so we can pass --exclude (moodle-plugin-ci's phpmd
# subcommand does not expose that flag).
run_phpmd() {
    echo " RUN  PHP Mess Detector on qtype_accounting"
    docker exec -w "${MOODLE_DIR}" "${CONTAINER}" bash -c \
        "${PHPMD_BIN} ${PLUGIN_PATH} text ${PLUGIN_PATH}/.phpmd.xml --exclude ${PHPMD_EXCLUDES} --ignore-violations-on-exit"
}

# The checker needs an up-to-date component registry to load its renderer.
# Call it directly: moodle-plugin-ci parses its output but loses fatal exit codes.
run_phpdoc() {
    local output status files
    echo " RUN  Moodle PHPDoc Checker on qtype_accounting"
    docker exec -w "${MOODLE_DIR}" "${CONTAINER}" php admin/cli/purge_caches.php || return
    files=$(docker exec "${CONTAINER}" php "${PLUGIN_PATH}/dev/scripts/ci-files.php" \
        --autoload=/opt/moodle-plugin-ci/vendor/autoload.php --plugin="${PLUGIN_PATH}" \
        --context=phpdoc --format=csv) || return
    if [ -z "${files}" ]; then
        echo "PHPDoc file selection is empty." >&2
        return 1
    fi
    output=$(docker exec -w "${MOODLE_DIR}" "${CONTAINER}" \
        php local/moodlecheck/cli/moodlecheck.php "-p=${files}" -f=text "$@" 2>&1)
    status=$?
    printf '%s\n' "${output}"
    if [ "${status}" -ne 0 ]; then
        return "${status}"
    fi
    # Match GitHub's --max-warnings 0, while also propagating checker crashes.
    if printf '%s\n' "${output}" | grep -E '^[[:space:]]+Line' >/dev/null; then
        return 1
    fi
}

# Stricter phpcs run using .phpcs.xml.dist (moodle + moodle-extra,
# amd/build/ excluded). Used by the `codecheck` subcommand.
run_phpcs_direct() {
    docker exec -w "${LIVE_PLUGIN_PATH}" "${CONTAINER}" "${PHPCS}" --extensions=php,js --no-colors "$@"
}
run_phpcbf_direct() {
    docker exec -w "${LIVE_PLUGIN_PATH}" "${CONTAINER}" "${PHPCBF}" --extensions=php,js --no-colors "$@"
}

# These dependency-free tests also run in GitHub Actions. Use the existing
# container's Node installation and copy the current test files into it.
run_js_tests() {
    "${SCRIPT_DIR}/js-tests.sh" basic
}

# Same order and warning thresholds as .github/workflows/moodle-ci.yml.
run_checks() {
    local cmd
    section "Verifying scanner scope"
    run_scope_check || die "scanner scope"
    for cmd in phplint phpcs phpdoc validate savepoints mustache; do
        section "Running: ${cmd}"
        case "${cmd}" in
            phpcs) run_phpcs || die "${cmd}" ;;
            phpdoc) run_phpdoc || die "${cmd}" ;;
            # Moodle's template linter only accepts templates inside the Moodle directory.
            mustache) run "${cmd}" "${LIVE_PLUGIN_PATH}" || die "${cmd}" ;;
            *) run "${cmd}" "${PLUGIN_PATH}" || die "${cmd}" ;;
        esac
    done
    section "Running: JavaScript regression tests"
    run_js_tests || die "JavaScript regression tests"
    section "Running: Grunt"
    run_grunt || die "grunt"
}

run_full_ci() {
    run_checks
    section "Running: tests (PHPUnit + Behat)"
    "${SCRIPT_DIR}/test.sh" all || die "test"
    echo "All CI steps passed."
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
    js)
        run_js_tests || die "JavaScript regression tests"
        ;;
    scope)
        run_scope_check || die "scanner scope"
        docker exec "${CONTAINER}" php "${PLUGIN_PATH}/dev/tests/ci_scope_test.php" \
            --autoload=/opt/moodle-plugin-ci/vendor/autoload.php --command="${MPCI}" --plugin="${PLUGIN_PATH}" \
            --moodle="${SCAN_MOODLE_DIR}"
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
                run_phpcs_direct --report=full .
                ;;
            summary)
                run_phpcs_direct --report=summary .
                ;;
            fix)
                echo "Auto-fixing with phpcbf..."
                run_phpcbf_direct . || true
                echo ""
                echo "Re-running phpcs to show what is left..."
                run_phpcs_direct --report=full .
                ;;
            --)
                shift
                run_phpcs_direct "$@"
                ;;
            *)
                run_phpcs_direct --report=full "$@"
                ;;
        esac
        ;;
    all)
        run_checks
        section "Running: optional PHP Mess Detector"
        run_phpmd || die "phpmd"
        section "Running: moodle-plugin-ci phpcpd"
        run phpcpd "${PLUGIN_PATH}" || die "phpcpd"
        echo ""
        echo "============================================================"
        echo "All CI steps passed."
        echo "============================================================"
        ;;
    phpmd)
        run_phpmd
        ;;
    phpdoc)
        shift
        run_phpdoc "$@"
        ;;
    phpcs|grunt)
        cmd="$1"
        shift
        if [ "${cmd}" = "phpcs" ]; then
            run_phpcs "$@"
        else
            run_grunt "$@"
        fi
        ;;
    phpunit|behat)
        "${SCRIPT_DIR}/test.sh" "$@"
        ;;
    phpcbf|phpcpd|phplint|validate|savepoints|mustache|parallel|install)
        cmd="$1"
        shift
        run "${cmd}" "${PLUGIN_PATH}" "$@"
        ;;
    *)
        run "$@"
        ;;
esac
