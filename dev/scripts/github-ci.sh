#!/usr/bin/env bash
# Run inside dev/shell.nix. Test databases and the Moodle site are isolated in RUNNER_TEMP.
set -euo pipefail

plugin_dir="${GITHUB_WORKSPACE:?Run this script from GitHub Actions}"
ci_dir="${RUNNER_TEMP:?}/accounting-ci"
mkdir -p "$ci_dir"
cd "$ci_dir"
composer create-project --no-interaction --no-dev --prefer-dist moodlehq/moodle-plugin-ci ci '^4.5'
export PATH="$ci_dir/ci/bin:$ci_dir/ci/vendor/bin:$PATH"
# Keep the Nix-provided Node version; plugin-ci otherwise installs another through nvm.
unset NVM_DIR NVM_BIN
export MOODLE_DOCKER_PHPUNIT_EXTERNAL_SERVICES=false
export LANG=C.UTF-8
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}"
mkdir -p "$ci_dir/php-config"
printf '%s\n' 'max_input_vars=5000' 'memory_limit=512M' > "$ci_dir/php-config/testing.ini"
cat > "$ci_dir/php-config/components.php" <<'PHP'
<?php
// CI checkers are added temporarily; discover their components in each CLI process.
if (!defined('IGNORE_COMPONENT_CACHE')) {
    define('IGNORE_COMPONENT_CACHE', true);
}
PHP
printf 'auto_prepend_file=%s\n' "$ci_dir/php-config/components.php" >> "$ci_dir/php-config/testing.ini"
export PHP_INI_SCAN_DIR="$PHP_INI_SCAN_DIR:$ci_dir/php-config"
export MOODLE_BEHAT_DEFAULT_BROWSER=chrome
chrome_binary="$(command -v chromium)"
printf -v MOODLE_BEHAT_CHROME_CAPABILITIES \
    '["extra_capabilities" => ["chromeOptions" => ["binary" => "%s", "args" => ["--headless=new", "--no-sandbox", "--disable-dev-shm-usage"]]]]' \
    "$chrome_binary"
export MOODLE_BEHAT_CHROME_CAPABILITIES

moodle-plugin-ci install --plugin "$plugin_dir" --db-host=127.0.0.1 --db-user="$DB_USER"
moodle-plugin-ci phplint
moodle-plugin-ci phpcs --max-warnings 0
moodle-plugin-ci phpdoc --max-warnings 0
moodle-plugin-ci validate
moodle-plugin-ci savepoints
moodle-plugin-ci mustache
node --test "$plugin_dir/dev/tests/entry_utils_test.cjs" "$plugin_dir/dev/tests/mobileapp_test.cjs"
moodle-plugin-ci grunt --max-lint-warnings 0
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome
