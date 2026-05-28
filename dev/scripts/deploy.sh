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

echo -e "${YELLOW}Deploying MoFT to ${REMOTE_HOST}:${REMOTE_DIR}${NC}"

# Copy plugin folder.
# Script lives in dev/scripts/, so the plugin source is two levels up (the repo root).
# We use git archive to ship only the files that would be in the released ZIP
# (respects .gitattributes export-ignore, so dev/ tooling is excluded).
REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
echo "Packaging plugin from ${REPO_ROOT}..."
TMP_ARCHIVE="$(mktemp -t buchungssatz.XXXXXX.tar)"
git -C "$REPO_ROOT" archive --format=tar --prefix=buchungssatz/ HEAD -o "$TMP_ARCHIVE"
echo "Copying plugin archive to remote..."
scp "$TMP_ARCHIVE" "${REMOTE_HOST}:${REMOTE_DIR}/buchungssatz.tar"
ssh "${REMOTE_HOST}" "cd ${REMOTE_DIR} && rm -rf buchungssatz && tar -xf buchungssatz.tar && rm buchungssatz.tar"
rm -f "$TMP_ARCHIVE"

# Restart docker compose on remote server
echo "Restarting Docker Compose on remote server..."
ssh "${REMOTE_HOST}" "cd ${REMOTE_DIR} && docker compose restart"

echo -e "${GREEN}Deployment complete!${NC}"
