FROM node:22-bookworm-slim

# Optional browser DOM tests and CI runner regressions run in a disposable CLI container.
RUN apt-get update && apt-get install -y --no-install-recommends python3 git shellcheck \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /opt/accounting-tests
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
ENV NODE_PATH=/opt/accounting-tests/node_modules
ENV ACCOUNTING_MOBILE_TEST_DEPS=/opt/accounting-tests
WORKDIR /workspace
