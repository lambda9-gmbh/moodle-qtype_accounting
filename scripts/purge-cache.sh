#!/bin/bash
# Purge Moodle caches (useful after plugin changes)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Purging Moodle caches..."

cd "$PROJECT_DIR/docker"
docker compose exec moodle php /var/www/html/admin/cli/purge_caches.php

echo "Caches purged."
