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

# Copy docker folder
echo "Copying docker folder..."
scp -r "./docker" "${REMOTE_HOST}:${REMOTE_DIR}/"

# Copy plugin folder
echo "Copying plugin folder..."
scp -r "./plugin" "${REMOTE_HOST}:${REMOTE_DIR}/"

# Copy docker-compose.yml
echo "Copying docker-compose.yml..."
scp "./docker-compose.yml" "${REMOTE_HOST}:${REMOTE_DIR}/"

# Restart docker compose on remote server
echo "Restarting Docker Compose on remote server..."
ssh "${REMOTE_HOST}" "cd ${REMOTE_DIR} && docker compose restart"

echo -e "${GREEN}Deployment complete!${NC}"
