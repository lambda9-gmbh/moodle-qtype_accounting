#!/usr/bin/env bash
# Open the installed Moodle Android app in a dedicated local Android emulator.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEV_DIR="$(dirname "$SCRIPT_DIR")"
ACTION="${1:-start}"

if [[ "$ACTION" == start ]]; then
    COMPOSE=(docker compose -f "$DEV_DIR/docker-compose.yml")
    case "${ACCOUNTING_APP_DB:-pgsql}" in
        pgsql) COMPOSE+=(-f "$DEV_DIR/docker-compose.pgsql.yml"); DB_SERVICE=postgres ;;
        mariadb) DB_SERVICE=mariadb ;;
        *) echo 'ACCOUNTING_APP_DB must be pgsql or mariadb' >&2; exit 1 ;;
    esac
    export MOODLE_WWWROOT="${MOODLE_WWWROOT:-http://localhost:${MOODLE_PORT:-8080}}"
    "${COMPOSE[@]}" up -d --no-build "$DB_SERVICE" moodle
    "${COMPOSE[@]}" exec -T moodle php admin/cli/upgrade.php --non-interactive
    "${COMPOSE[@]}" cp "$DEV_DIR/mobile/setup.php" moodle:/tmp/accounting-app-setup.php
    "${COMPOSE[@]}" exec -T -e ACCOUNTING_APP_LOCAL_SETUP=1 moodle php /tmp/accounting-app-setup.php
elif [[ "$ACTION" == refresh ]]; then
    docker compose -f "$DEV_DIR/docker-compose.yml" exec -T moodle php admin/cli/purge_caches.php
fi

exec nix shell nixpkgs#android-tools nixpkgs#python3 nixpkgs#curl --command \
    python3 "$DEV_DIR/mobile/native.py" "$ACTION"
