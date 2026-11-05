#!/bin/bash
set -e

# Configuration
REMOTE_HOST="${DEPLOY_HOST:-testserver}"
REMOTE_DIR="${DEPLOY_DIR:-~/services/moodle}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Deploying moodle-qtype_accounting to ${REMOTE_HOST}:${REMOTE_DIR}${NC}"

# Copy plugin folder.
# Script lives in dev/scripts/, so the plugin source is two levels up (the repo root).
# Use the release archive as a base, then include German strings for testserver.
# Release archives still respect .gitattributes export-ignore.
REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
echo "Packaging plugin from ${REPO_ROOT}..."
TMP_ARCHIVE="$(mktemp -t accounting.XXXXXX.tar)"
TMP_GERMAN_ARCHIVE="$(mktemp -t accounting-de.XXXXXX.tar)"
trap 'rm -f "$TMP_ARCHIVE" "$TMP_GERMAN_ARCHIVE"' EXIT
git -C "$REPO_ROOT" archive --format=tar --prefix=accounting/ HEAD -o "$TMP_ARCHIVE"
# Archiving the subtree includes translations excluded by the root release attributes.
git -C "$REPO_ROOT" archive --format=tar --prefix=lang/de/ HEAD:lang/de -o "$TMP_GERMAN_ARCHIVE"
echo "Copying plugin archive to remote..."
scp "$TMP_ARCHIVE" "${REMOTE_HOST}:${REMOTE_DIR}/accounting.tar"
scp "$TMP_GERMAN_ARCHIVE" "${REMOTE_HOST}:${REMOTE_DIR}/accounting-de.tar"
# Extract into the host directory mounted by the testserver's Moodle service.
# Preserve the directory itself so the bind mount keeps pointing at it.
ssh "${REMOTE_HOST}" "cd ${REMOTE_DIR} && mkdir -p plugin && tar -xf accounting.tar -C plugin --strip-components=1 && tar -xf accounting-de.tar -C plugin && rm accounting.tar accounting-de.tar"

# Restart Moodle and purge its caches so deployed PHP and language strings take effect.
echo "Restarting Moodle and purging caches on remote server..."
ssh "${REMOTE_HOST}" "cd ${REMOTE_DIR} && docker compose restart moodle && docker compose exec -T moodle php /var/www/html/admin/cli/purge_caches.php"

echo -e "${GREEN}Deployment complete!${NC}"
