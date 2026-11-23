# Contributing

Thank you for contributing to the Accounting Entry question type for Moodle.
This guide covers the local workflow and checks expected for code changes.

## Before you start

For bugs and feature requests, please check the project issue tracker first. Bug reports should include:

- Moodle, PHP, and database versions.
- The plugin version.
- Steps to reproduce, expected behaviour, and observed behaviour.

For larger changes, open an issue or describe the proposed approach before implementation so the scope and compatibility can be agreed.

## Development environment

Install the plugin into a Moodle development site under `question/type/accounting`. Test both MariaDB/MySQL and PostgreSQL for database-related changes.

## Making changes

1. Create a focused branch from the current development branch.
2. Keep changes small and update documentation or tests when behaviour changes.
3. Follow the [Moodle Coding Style Guide](https://moodledev.io/general/development/policies/codingstyle).
4. Use the `qtype_accounting-` prefix for CSS selectors and lowercase language-string keys.
5. When changing `amd/src/`, rebuild and commit the corresponding files in `amd/build/`:

   ```bash
   npx grunt amd
   ```

6. For database changes, bump the plugin version in `version.php` and add a migration step to `db/upgrade.php`. Do not change `db/install.xml` without considering existing installations.

## Tests and checks

Run the relevant tests while developing:

```bash
vendor/bin/phpunit --testsuite qtype_accounting_testsuite
php admin/tool/behat/cli/run.php --tags=@qtype_accounting
```

Before submitting a change, run the complete suite and the static checks with [moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/), as listed in the README's Development section.

The CI checks include PHPUnit, Behat, Moodle coding standards, PHP Mess Detector, PHP documentation checks, plugin validation, PHP linting, and JavaScript/CSS/gherkin linting. PostgreSQL testing is required for cross-database changes.

If JavaScript is being iterated on locally, Moodle's JavaScript cache can be disabled with `$CFG->cachejs = false;` in `config.php`, but committed builds must still be regenerated.

## Moodle-specific guidance

- Preserve Moodle security conventions, including `defined('MOODLE_INTERNAL') || die();` where appropriate and capability checks for chart management.
- Use Moodle APIs for database access, output, forms, language strings, and privacy functionality.
- Add or update PHPUnit tests for PHP behaviour and Behat scenarios for user-visible workflows.
- Release archives contain English language strings; maintain translations through [Moodle AMOS](https://lang.moodle.org/). The repository's `lang/de/` files are for development/testing.

## Pull requests

Describe what changed, why it changed, and how it was tested. Include screenshots or a short recording for user-interface changes. Mention any database upgrade, backup/restore, import/export, accessibility, or cross-database considerations.

Please ensure generated AMD files and relevant documentation are included, all applicable checks pass, and unrelated changes are left out of the pull request.

## License

By contributing, you agree that your work may be distributed under the project's [GNU General Public License v3 or later](LICENSE).
