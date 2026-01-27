#!/bin/bash
# Purge Moodle 3.10 caches

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Purging Moodle 3.10 caches..."

cd "$PROJECT_DIR/docker"
docker compose -f docker-compose.moodle310.yml exec moodle310 php admin/cli/purge_caches.php

echo "Caches purged."
