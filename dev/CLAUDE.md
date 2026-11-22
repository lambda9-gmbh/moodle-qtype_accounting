# qtype_accounting — Moodle Question Type: Accounting Entry (Buchungssatz)

Internal developer notes. Not shipped: `dev/` is `export-ignore`d in `.gitattributes`.

The plugin lets instructors author accounting journal-entry questions (Buchungssätze) where students pick debit/credit accounts from a chart of accounts of the question bank and enter amounts. Developed for **Hochschule Flensburg** by **lambda9**.

## Coding standards

Follows the [Moodle Coding Style Guide](https://moodledev.io/general/development/policies/codingstyle), enforced by:
- `.phpcs.xml.dist` — rules `moodle` + `moodle-extra` (via `moodlehq/moodle-cs`).
- `.phpmd.xml` — code-size + design + naming + unused-code rules, with a few opt-outs documented inline.
- ESLint + Stylelint via `moodle-plugin-ci grunt`.

Key conventions:
- `@package qtype_accounting`, `@copyright`, `@license` on every file and class.
- `defined('MOODLE_INTERNAL') || die();` on every PHP file that doesn't bootstrap via `config.php`.
- 4-space indent, `else if` (not `elseif`), short array `[]`, no closing `?>`.
- Class names: lowercase + underscores (`qtype_accounting_renderer`).
- Lang keys: all lowercase, no camelCase.
- CSS selectors: `qtype_accounting-*` prefix (the frankenstyle component name).

## Plugin metadata

- Component: `qtype_accounting`
- Requires: Moodle 4.5 LTS (`$plugin->requires = 2024100700`)
- Maturity: `MATURITY_BETA`
- Release: `0.1.0`
- License: GNU GPLv3-or-later

## Repository layout

```
moodle-qtype_accounting/                 # Plugin root; unpacks to question/type/accounting/
├── amd/
│   ├── src/                             # ES module sources
│   │   ├── chart_overlay.js             # Chart management overlay in the question editor
│   │   ├── editform.js                  # Authoring form behaviour
│   │   ├── editform_validation.js       # Submit validation of the authoring form
│   │   ├── entry_utils.js               # Shared helpers
│   │   ├── manage_charts.js             # Delete confirmations on the chart pages
│   │   ├── mobile_layout.js             # Card layout for narrow questions
│   │   └── question.js                  # Student quiz interaction
│   └── build/                           # Minified output (committed)
├── backup/moodle2/                      # Backup + restore handlers
├── classes/                             # Autoloaded under qtype_accounting\
│   ├── admin/setting_sitecharts.php     # Site charts list on the settings page
│   ├── external/get_charts.php          # Web service: charts of a question bank
│   ├── form/                            # Chart create and file upload forms
│   ├── local/chart_page.php             # Shared setup of the chart pages
│   ├── output/                          # Renderables for the templates, Moodle App handler
│   ├── privacy/provider.php             # Privacy API
│   ├── account_manager.php              # Account CRUD and imports into a chart
│   ├── account_provider.php             # Accounts offered in a question's selects
│   ├── aggregator.php                   # Sums entries per side and account
│   ├── amount_helper.php                # Amounts in integer cents
│   ├── chart_manager.php                # Chart CRUD, chart contexts, matching
│   ├── chart_relocator.php              # Keeps charts in the question bank of their questions
│   ├── currency_helper.php              # Currency symbols
│   ├── entries_table_builder.php        # Submitted editor entries, editor table
│   ├── entry_helper.php                 # Entry row helpers
│   ├── entry_validator.php              # Server-side validation of the editor
│   ├── feedback_calculator.php          # Field and side feedback
│   ├── import_helper.php                # CSV, text and ODS parsing
│   ├── observer.php                     # Removes charts of deleted contexts
│   ├── scorer.php                       # Grading (percentage and points)
│   └── xml_handler.php                  # Moodle XML import/export
├── db/
│   ├── access.php                       # Capability definitions
│   ├── events.php                       # Event observers
│   ├── install.xml                      # XMLDB schema
│   ├── mobile.php                       # Moodle App handler
│   ├── services.php                     # Web services
│   └── upgrade.php                      # Per-version DB migrations
├── docs/screenshots/                    # README screenshots
├── js/mobileapp/question.js             # Moodle App controller
├── lang/
│   ├── en/qtype_accounting.php          # English (shipped)
│   └── de/qtype_accounting.php          # German (dev-only; export-ignored)
├── pix/icon.svg                         # Question-type icon
├── templates/                           # Mustache templates (question, answer, feedback, editor table, app)
├── tests/
│   ├── behat/                           # Acceptance tests
│   ├── generator/                       # Test data generator
│   └── *_test.php                       # PHPUnit tests
├── edit_accounting_form.php             # Question authoring form
├── edit_chart.php                       # Edit a single chart's accounts
├── manage_charts.php                    # Chart list of a question bank, or the site charts
├── question.php                         # qtype_accounting_question
├── questiontype.php                     # qtype_accounting (question_type)
├── renderer.php                         # qtype_accounting_renderer
├── settings.php                         # Site settings
├── styles.css                           # .qtype_accounting-* selectors
├── version.php
├── README.md
├── CHANGES.md
├── LICENSE
└── dev/                                 # Dev tooling — not shipped in release ZIP
```

Notes:
- Browser output uses renderables in `classes/output/` and Mustache templates in `templates/`.
  JavaScript and the Moodle App rely on their class names, field names and data attributes.
  Moodle App support is registered in
  `db/mobile.php`; `classes/output/mobile.php` serves `templates/mobileapp/question.mustache`
  and `js/mobileapp/question.js`. The app controller is evaluated by the app, not loaded as AMD.
  Bind template actions inside `componentInit`: other returned methods override the handler and
  are not copied to the compiled component. The quiz serializes native `form.elements`, so each
  answer has an always-present hidden input with `[attr.name]` and `[value]`; Ionic field events
  update that value immediately. Do not rely on Angular form metadata for submitted field names.
  `dev/tests/mobileapp_integration_test.mjs` renders the form using the Angular version in App 5.2.1.
  Native testing uses `dev/scripts/app.sh start`: the official Moodle 5.2.1 APK
  runs in a dedicated Android 13 emulator window. It reuses the existing SDK and
  keeps its AVD/data under `dev/mobile/.runtime/android/`; other AVDs are untouched.
  ADB reverse forwards the emulator's localhost:8080 to the local Moodle server.
  Demo quizzes use immediate feedback: saving retains TODO state until Check.
  Use deferred feedback for quizzes that should grade only at final submission.

## Database schema

All tables are frankenstyle-prefixed `qtype_accounting_`:

| Table | Purpose | Key fields |
|---|---|---|
| `qtype_accounting_charts` | Chart of accounts definitions, scoped to a context | `id`, `name`, `contextid`, `timecreated`, `timemodified`, `usermodified` |
| `qtype_accounting_accounts` | Individual accounts inside a chart | `id`, `chartid`, `accountname`, `sortorder` |
| `qtype_accounting_options` | Per-question settings | `id`, `questionid`, `chartofaccountsid`, `accountsindropdown`, `numberformat`, `extraentrydeduction`, `allornothinggrading`, `allowmultipleentries`, `maxentries` |
| `qtype_accounting_entries` | Correct-answer rows | `id`, `questionid`, `sortorder`, `debitaccountid`, `debitamount`, `creditaccountid`, `creditamount`, `weight_debit/credit_account/amount`, `explanation` |

Charts live in the **question bank context** of their questions (course context up to Moodle 4.5, `mod_qbank`/quiz module context from 5.0) or in the system context (site charts); `chart_relocator` moves or copies them when questions change banks. Capability `qtype/accounting:managecharts` is required to edit them. The chart-of-accounts ID is referenced from `qtype_accounting_options.chartofaccountsid`.

## Grading model

`classes/scorer.php` implements an **aggregation-based weighted score** (called from `question.php::calculate_fraction`):

1. Aggregate correct entries by `(side, account)` — summing amounts and per-field weights.
2. Aggregate the student's response by `(side, account)` — summing amounts.
3. For each correct `(side, account)` pair, award the account weight if the student named it, and award the amount weight if the summed integer cents match exactly.
4. Apply per-extra-account deduction (`extraentrydeduction` option, configurable; floored at 0).
5. If `allornothinggrading` is on, snap to {0, 1}.

Account matching is case-insensitive on `accountname` (via the account_id lookup). Monetary arithmetic uses integer cents, parsed directly from decimal text. Extra precision rounds to the nearest cent, with ties away from zero. Database and XML boundaries retain canonical decimal strings. Browser balance totals use BigInt cents.

## Frontend (AMD modules)

Wired into PHP via `$PAGE->requires->js_call_amd('qtype_accounting/<module>', 'init', […])`:

- **`question/init(containerId)`** — student quiz: add/delete entry rows, copy debit amount to credit amount, apply mobile card layout via `mobile_layout`.
- **`editform/init()`** — authoring form: populate account dropdowns from selected chart, auto-calculate total grade from per-entry fractions, sync per-field weight visibility, CSV import wiring.
- `entry_utils` — pure helpers reused by both above.
- `mobile_layout` — viewport-based switching between table view and card view.

Build: `./dev/scripts/build.sh` minifies `amd/src/*.js` to `amd/build/*.min.js` via `terser`. During dev, `$CFG->cachejs = false;` in `dev/docker/config.php` lets Moodle serve `amd/src/` directly.

## Dev environment

The Docker stack lives in `dev/`. Two flavours:

**MariaDB (default):**
```bash
./dev/scripts/start.sh        # mariadb + moodle + phpmyadmin + selenium
./dev/scripts/stop.sh
./dev/scripts/reset.sh        # destroys volumes
```

**PostgreSQL (for cross-DB testing — required by the Moodle plugin contribution checklist):**
```bash
./dev/scripts/start-pgsql.sh  # postgres + moodle + selenium
./dev/scripts/stop-pgsql.sh
./dev/scripts/reset-pgsql.sh
```

The `pgsql` stack overlays `docker-compose.pgsql.yml` on the base compose; `dev/docker/config.php` reads `MOODLE_DOCKER_DBTYPE/HOST/NAME/USER/PASS` from the container env, so the same image works for both.

Container paths:
- Moodle source: `/var/www/html` (the chosen `MOODLE_VERSION` is downloaded into this dir at image-build time).
- Plugin: `/var/www/html/question/type/accounting`, populated by per-file/per-directory bind mounts from the host repo (see `dev/docker-compose.yml`). **Caveat:** `sed -i.bak ... && rm .bak` on host replaces the inode and orphans the container's single-file bind mounts; restart the container after such edits (`docker restart accounting-moodle`).
- Moodledata: `/var/www/moodledata` (named volume).
- Behatdata: `/var/www/behatdata` (named volume).
- moodle-plugin-ci: `/opt/moodle-plugin-ci`.
- moodle-cs (phpcs ruleset): `/opt/moodle-cs`.

Services:
- Moodle (Apache + PHP 8.2 by default): http://localhost:8080
- phpMyAdmin (MariaDB stack only): http://localhost:8081
- Selenium (for Behat): http://localhost:4444 (VNC at 7900)
- MariaDB: `mariadb:3306` inside the docker network
- Postgres: `postgres:5432` inside the docker network

## Test + CI scripts

```bash
./dev/scripts/test.sh                # PHPUnit + Behat
./dev/scripts/test.sh phpunit
./dev/scripts/test.sh behat

./dev/scripts/ci.sh                  # GitHub checks + JS regressions + PHPUnit + Behat
./dev/scripts/ci.sh checks           # Static checks + standalone JS regressions
./dev/scripts/ci.sh js               # Standalone JS regressions only
./dev/scripts/ci.sh scope            # Complete-tree scanner manifests and scope regressions
./dev/scripts/js-tests.sh dom        # Optional Docker image: browser and Angular DOM tests
./dev/scripts/js-tests.sh runner     # Optional Docker image: CI script regressions
./dev/scripts/ci.sh codecheck        # phpcs (moodle + moodle-extra)
./dev/scripts/ci.sh codecheck fix    # phpcbf auto-fix
./dev/scripts/ci.sh phpcs            # moodle-plugin-ci phpcs
./dev/scripts/ci.sh phpmd
./dev/scripts/ci.sh phpdoc
./dev/scripts/ci.sh grunt            # eslint + stylelint + gherkinlint
./dev/scripts/ci.sh all              # check set + optional phpmd and phpcpd

./dev/scripts/build.sh               # Minify amd/src → amd/build
./dev/scripts/logs.sh                # docker compose logs -f
./dev/scripts/purge-cache.sh         # admin/cli/purge_caches.php
```

CI reads uncommitted working files and matches the GitHub warning thresholds:
PHPCS/PHPDoc/Grunt allow zero warnings, and PHPUnit uses `--fail-on-warning`.
It requires existing tools and running services; it does not install dependencies
or start Selenium. Local Behat uses the configured Firefox profile; GitHub uses Chrome.
Node runs inside the existing Moodle container. Optional DOM/runner checks use
`accounting-dom-tests`, built with `docker build -t accounting-dom-tests dev/tests/mobileapp`.
Source scanners use a complete temporary working-tree copy, including `dev/`,
and share `.moodle-plugin-ci.yml` with GitHub. PHPDoc uses that same file selector.
The scope guard verifies development files are present but excluded and required
plugin/test files are selected. GitHub also compares the installed and checkout
manifests. Grunt uses a container-native snapshot in a minimal Moodle build
layout, reusing the installed tools while keeping `qtype_accounting` as the
component name. Its backup does not read host bind mounts. Runtime tests use the
mounted Moodle component.

## Common gotchas

- **After PHP changes**, purge caches (`./dev/scripts/purge-cache.sh`) — Moodle aggressively caches plugin discovery + autoloader maps.
- **After JS source changes**, either run `./dev/scripts/build.sh` to rebuild `amd/build/` or set `$CFG->cachejs = false;`. Behat in particular reads `amd/build/`.
- **After DB schema changes**, bump `$plugin->version` and add a step to `db/upgrade.php`. Don't edit `install.xml` without a matching upgrade step (CI checks this).
- **Behat `attempt_question.feature`** depends on the renderer applying `.qtype_accounting-entry-row` to entry `<tr>`s — keep that class name in sync if you ever rename selectors.
- **Single-file bind mounts** in `dev/docker-compose.yml` pin to inodes; tools that unlink+recreate files (sed -i.bak, some editors) leave the container holding a deleted handle. Restart `accounting-moodle` if Moodle suddenly can't find a top-level plugin file.

## Working agreements with Claude

- Never switch Git branches unless the user explicitly instructs you to do so. Keep all work on the current branch; a task, ticket, or commit request does not authorize a branch switch.
- When creating a plan, surface anything unclear before writing code.
- Keep plans tight; one or two lines per step.
- Don't restate what the diff already says.
