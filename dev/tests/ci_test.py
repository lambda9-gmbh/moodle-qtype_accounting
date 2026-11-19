"""CI runner regressions; run with ./dev/scripts/js-tests.sh runner.

Docker and Node are mocked so failure handling can be checked without a site.
"""

import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]


class CiRunnerTest(unittest.TestCase):
    def setUp(self):
        self.workspace = tempfile.TemporaryDirectory()
        self.addCleanup(self.workspace.cleanup)
        self.base = Path(self.workspace.name)
        self.root = self.base / "repo"
        scripts = self.root / "dev/scripts"
        scripts.mkdir(parents=True)
        for name in ("ci.sh", "test.sh", "js-tests.sh"):
            shutil.copy2(ROOT / "dev/scripts" / name, scripts / name)
        for name in ("README.md", "CHANGES.md", "LICENSE"):
            (self.root / name).write_text("working copy\n")
        for name in ("dev/docker/config.php", "dev/mobile/setup.php", "untracked.php"):
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text("<?php\n")
        (self.root / ".gitignore").write_text(".env\n")
        (self.root / ".env").write_text("ignored fixture\n")
        subprocess.run(["git", "init", "-q", str(self.root)], check=True)
        deleted = self.root / "deleted.php"
        deleted.write_text("<?php\n")
        subprocess.run(["git", "-C", str(self.root), "add", "deleted.php"], check=True)
        deleted.unlink()
        self.log = self.base / "commands.log"
        self.archive = self.base / "snapshot.tar"
        self.bin = self.base / "bin"
        self.bin.mkdir()
        self.executable("docker", r'''#!/bin/bash
printf '%s\n' "$*" >> "$CI_TEST_LOG"
case "$*" in
    'ps --format '*) echo accounting-moodle ;;
    *'mktemp -d /tmp/accounting-js-tests.'*) echo /tmp/accounting-js-tests.mock ;;
    *'mktemp -d /tmp/accounting-ci.'*) echo /tmp/accounting-ci.mock ;;
    *'tar -xf - -C '*)
        cat > "$CI_TEST_ARCHIVE"
        if [ "$CI_TEST_MODE" = copy-failure ]; then exit 1; fi ;;
    *'ci-files.php '*--format=csv*) echo /tmp/accounting-ci.mock/plugin/renderer.php ;;
    *'ci-files.php '*--format=verify*)
        if [ "$CI_TEST_MODE" = scope-failure ]; then exit 1; fi ;;
    *'bash -s -- '*)
        if [ "$CI_TEST_MODE" = grunt-workspace-failure ]; then exit 1; fi ;;
    *'node --test '*)
        if [ "$CI_TEST_MODE" = js-failure ]; then exit 5; fi ;;
    'compose ps --status running --services selenium')
        if [ "$CI_TEST_MODE" != selenium-missing ]; then echo selenium; fi ;;
    *'test -x '*)
        if [ "$CI_TEST_MODE" = tool-missing ]; then exit 1; fi ;;
    'cp '*)
        if [ "$CI_TEST_MODE" = copy-failure ]; then exit 1; fi ;;
    'compose cp '*)
        if [ "$CI_TEST_MODE" = fixture-copy-failure ]; then exit 1; fi ;;
    *'/moodle-plugin-ci phpcs '*)
        if [ "$CI_TEST_MODE" = phpcs-failure ]; then exit 9; fi ;;
    *'local/moodlecheck/cli/moodlecheck.php '*)
        if [ "$CI_TEST_MODE" = phpdoc-warning ]; then
            echo '  Line 12: Missing documentation (warning)'
        elif [ "$CI_TEST_MODE" = phpdoc-crash ]; then
            echo 'Fatal checker failure' >&2
            exit 255
        fi ;;
esac
exit 0
''')

    def executable(self, name, source):
        path = self.bin / name
        path.write_text(source)
        path.chmod(0o755)

    def run_ci(self, *args, mode="success"):
        result = subprocess.run(
            ["bash", str(self.root / "dev/scripts/ci.sh"), *args],
            env={**os.environ, "PATH": f"{self.bin}:{os.environ['PATH']}",
                 "CI_TEST_LOG": str(self.log), "CI_TEST_MODE": mode,
                 "CI_TEST_ARCHIVE": str(self.archive)},
            text=True, capture_output=True, check=False,
        )
        commands = self.log.read_text() if self.log.exists() else ""
        self.assertNotRegex(commands, r"apt-get|npm install|composer install|compose up|docker restart")
        return result, commands

    def test_full_suite_uses_strict_flags_and_working_tree_js(self):
        result, commands = self.run_ci()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        markers = [
            "moodle-plugin-ci phplint ", "moodle-plugin-ci phpcs --max-warnings 0 ",
            "local/moodlecheck/cli/moodlecheck.php ", "moodle-plugin-ci validate ",
            "moodle-plugin-ci savepoints ", "moodle-plugin-ci mustache ",
            "node --test ", "moodle-plugin-ci grunt --max-lint-warnings 0 ",
            "vendor/bin/phpunit --testsuite=qtype_accounting_testsuite --fail-on-warning",
            "admin/tool/behat/cli/run.php --tags=@qtype_accounting --auto-rerun=2 --verbose",
        ]
        positions = [commands.index(marker) for marker in markers]
        self.assertEqual(positions, sorted(positions))
        for name in ("entry_utils_test.cjs", "mobileapp_test.cjs"):
            self.assertIn(f"cp {self.root}/dev/tests/{name} accounting-moodle:/tmp/accounting-js-tests.mock/dev/tests/{name}", commands)
        self.assertIn("exec accounting-moodle node --test /tmp/accounting-js-tests.mock/dev/tests/", commands)
        self.assertIn(f"compose cp {self.root}/dev/../tests/fixtures/. "
                      "selenium:/var/www/html/question/type/accounting/tests/fixtures", commands)
        self.assertIn("phpcs --max-warnings 0 /tmp/accounting-ci.mock/plugin", commands)
        grunt_command = next(line for line in commands.splitlines() if "moodle-plugin-ci grunt " in line)
        self.assertIn("--moodle=/tmp/accounting-ci.mock/grunt-moodle", grunt_command)
        self.assertIn("/tmp/accounting-ci.mock/grunt-moodle/question/type/accounting", grunt_command)
        self.assertNotIn("/var/www/html/question/type/accounting", grunt_command)

    def test_snapshot_includes_dev_and_untracked_sources_without_ignored_or_deleted_files(self):
        result, _ = self.run_ci("scope")
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        with tarfile.open(self.archive) as archive:
            files = archive.getnames()
        self.assertIn("dev/docker/config.php", files)
        self.assertIn("dev/mobile/setup.php", files)
        self.assertIn("untracked.php", files)
        self.assertNotIn(".env", files)
        self.assertNotIn("deleted.php", files)
        self.assertFalse(any(file.startswith(".git/") for file in files))

    def test_scope_failure_stops_scanners(self):
        result, commands = self.run_ci(mode="scope-failure")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("moodle-plugin-ci phplint", commands)

    def test_static_check_failure_stops_later_checks_and_tests(self):
        result, commands = self.run_ci(mode="phpcs-failure")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("local/moodlecheck/cli/moodlecheck.php", commands)
        self.assertNotIn("node --test", commands)
        self.assertNotIn("vendor/bin/phpunit", commands)

    def test_phpdoc_warning_is_a_failure(self):
        result, _ = self.run_ci("phpdoc", mode="phpdoc-warning")
        self.assertEqual(result.returncode, 1)

    def test_phpdoc_crash_propagates(self):
        result, _ = self.run_ci("phpdoc", mode="phpdoc-crash")
        self.assertEqual(result.returncode, 255)

    def test_individual_phpunit_preserves_filter_arguments(self):
        result, commands = self.run_ci("phpunit", "--filter", "test a specific case")
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("--fail-on-warning --filter test a specific case", commands)

    def test_js_failure_stops_grunt_and_phpunit(self):
        result, commands = self.run_ci(mode="js-failure")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("moodle-plugin-ci grunt", commands)
        self.assertNotIn("vendor/bin/phpunit", commands)

    def test_failed_grunt_workspace_does_not_fall_back_to_bind_mounts(self):
        result, commands = self.run_ci(mode="grunt-workspace-failure")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("moodle-plugin-ci grunt", commands)
        self.assertNotIn("vendor/bin/phpunit", commands)

    def test_missing_tool_fails_before_checks(self):
        result, commands = self.run_ci(mode="tool-missing")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("Missing CI tool", result.stderr)
        self.assertNotIn("node --test", commands)
        self.assertNotIn("moodle-plugin-ci phplint", commands)

    def test_failed_working_copy_sync_stops_checks(self):
        result, commands = self.run_ci(mode="copy-failure")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("moodle-plugin-ci phplint", commands)

    def test_missing_selenium_fails_without_starting_services(self):
        result, commands = self.run_ci("test", "behat", mode="selenium-missing")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("Selenium is not running", result.stderr)
        self.assertNotIn("admin/tool/behat/cli/run.php", commands)

    def test_fixture_sync_failure_stops_browser_tests(self):
        result, commands = self.run_ci("test", "behat", mode="fixture-copy-failure")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("admin/tool/behat/cli/run.php", commands)


if __name__ == "__main__":
    unittest.main()
