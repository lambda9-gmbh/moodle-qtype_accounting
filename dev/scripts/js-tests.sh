#!/usr/bin/env bash
# Run JavaScript and CI runner checks with container-provided tooling.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
CONTAINER=accounting-moodle
PLUGIN_PATH=/var/www/html/question/type/accounting
TEST_IMAGE=accounting-dom-tests

case "${1:-basic}" in
    basic)
        # dev/ is intentionally excluded from the Moodle plugin mounts. Copy the
        # tests into a temporary workspace and link the mounted working sources.
        docker exec "$CONTAINER" node --version >/dev/null
        test_root=$(docker exec "$CONTAINER" mktemp -d /tmp/accounting-js-tests.XXXXXX)
        trap 'docker exec "$CONTAINER" rm -rf -- "$test_root" >/dev/null' EXIT
        docker exec "$CONTAINER" mkdir -p "$test_root/dev/tests"
        for name in entry_utils_test.cjs mobileapp_test.cjs; do
            docker cp "$PLUGIN_ROOT/dev/tests/$name" "$CONTAINER:$test_root/dev/tests/$name" >/dev/null
        done
        docker exec "$CONTAINER" ln -s "$PLUGIN_PATH/amd" "$test_root/amd"
        docker exec "$CONTAINER" ln -s "$PLUGIN_PATH/js" "$test_root/js"
        docker exec "$CONTAINER" node --test \
            "$test_root/dev/tests/entry_utils_test.cjs" "$test_root/dev/tests/mobileapp_test.cjs"
        ;;
    dom|runner)
        if ! docker image inspect "$TEST_IMAGE" >/dev/null 2>&1; then
            echo "Build the optional test image first: docker build -t $TEST_IMAGE $PLUGIN_ROOT/dev/tests/mobileapp" >&2
            exit 1
        fi
        if [ "$1" = dom ]; then
            docker run --rm --mount "type=bind,src=$PLUGIN_ROOT,dst=/workspace,readonly" \
                "$TEST_IMAGE" node --test dev/tests/entry_utils_test.cjs dev/tests/mobileapp_test.cjs \
                dev/tests/editform_test.cjs dev/tests/question_test.cjs dev/tests/mobileapp_integration_test.mjs
        else
            docker run --rm --mount "type=bind,src=$PLUGIN_ROOT,dst=/workspace,readonly" \
                "$TEST_IMAGE" python3 dev/tests/ci_test.py
        fi
        ;;
    *)
        echo "Usage: $0 [basic|dom|runner]" >&2
        exit 1
        ;;
esac
