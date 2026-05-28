# qtype_accounting — Moodle Question Type: Accounting Entry (Buchungssatz)

Internal developer notes. Not shipped: `dev/` is `export-ignore`d in `.gitattributes`.

The plugin lets instructors author accounting journal-entry questions (Buchungssätze) where students pick debit/credit accounts from a course-scoped chart of accounts and enter amounts. Developed for **Hochschule Flensburg** by **lambda9**.

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
moodle-qtype_accounting/                  # Unpacks to question/type/accounting/ in Moodle
├── amd/
│   ├── src/                              # ES-module sources
│   │   ├── editform.js                   # Question authoring UI
│   │   ├── entry_utils.js                # Shared entry helpers
│   │   ├── mobile_layout.js              # Mobile card layout
│   │   └── question.js                   # Student quiz interaction
│   └── build/                            # Minified output (committed; rebuilt via grunt)
├── backup/moodle2/                       # Backup + restore handlers
├── classes/                              # PSR-4 autoloaded under qtype_accounting\
│   ├── account_manager.php               # Account CRUD
│   ├── account_provider.php              # Dropdown filtering + seeded shuffle
│   ├── amount_helper.php                 # Number-format parsing/formatting
│   ├── answer_renderer.php               # Renders the "correct answer" block
│   ├── chart_manager.php                 # Chart CRUD + CSV import/export
│   ├── entries_table_builder.php         # Edit-form entry table HTML
│   ├── entry_helper.php                  # Shared entry-row helpers
│   ├── entry_validator.php               # Edit-form validation
│   ├── feedback_calculator.php           # Per-entry feedback computation
│   ├── feedback_renderer.php             # Student-side feedback HTML
│   ├── import_helper.php                 # CSV parsing utilities
│   ├── privacy/provider.php              # GDPR provider (usermodified only)
│   ├── scorer.php                        # Aggregation-based weighted scoring
│   └── xml_handler.php                   # Moodle XML import/export
├── db/
│   ├── access.php                        # qtype/accounting:managecharts capability
│   ├── install.xml                       # XMLDB schema
│   └── upgrade.php                       # xmldb_qtype_accounting_upgrade()
├── lang/
│   ├── en/qtype_accounting.php           # English (shipped)
│   └── de/qtype_accounting.php           # German (export-ignored; ship via AMOS)
├── pix/icon.svg                          # Question type icon
├── tests/
│   ├── behat/                            # Acceptance tests
│   │   ├── attempt_question.feature
│   │   ├── behat_qtype_accounting.php
│   │   ├── create_question.feature
│   │   └── grading.feature
│   ├── generator/lib.php                 # Test data generator
│   ├── account_manager_test.php
│   ├── chart_manager_test.php
│   ├── helper.php
│   ├── import_helper_test.php
│   ├── question_test.php
│   └── questiontype_test.php
├── edit_accounting_form.php              # qtype_accounting_edit_form (question authoring)
├── edit_chart.php                        # Per-chart edit page (manages accounts in one chart)
├── manage_charts.php                     # Course-scoped chart list / upload / delete
├── question.php                          # qtype_accounting_question (question_graded_automatically)
├── questiontype.php                      # qtype_accounting (question_type)
├── renderer.php                          # qtype_accounting_renderer
├── styles.css                            # .qtype_accounting-* rules
├── version.php
├── README.md
├── CHANGES.md
├── LICENSE
├── .gitattributes                        # Marks dev/, lang/de/, .idea/, .claude/ export-ignore
├── .phpcs.xml.dist                       # moodle + moodle-extra ruleset
└── .phpmd.xml
```

Notable absences (intentional):
- No `settings.php` — the plugin has no admin settings.
- No `ajax/` — earlier endpoints were dead code; chart import now goes through Moodle's filepicker form in `manage_charts.php` / `edit_chart.php`.
- No `db/services.php` / `classes/external/` — no external web service surface.
- No mustache templates — all output via `html_writer` / direct HTML strings in the renderer.

## Database schema

All tables are frankenstyle-prefixed `qtype_accounting_`:

| Table | Purpose | Key fields |
|---|---|---|
| `qtype_accounting_charts` | Chart of accounts definitions, scoped to a context | `id`, `name`, `contextid`, `timecreated`, `timemodified`, `usermodified` |
| `qtype_accounting_accounts` | Individual accounts inside a chart | `id`, `chartid`, `accountname`, `sortorder` |
| `qtype_accounting_options` | Per-question settings | `id`, `questionid`, `chartofaccountsid`, `accountsindropdown`, `numberformat`, `extraentrydeduction`, `allornothinggrading`, `allowmultipleentries`, `maxentries` |
| `qtype_accounting_entries` | Correct-answer rows | `id`, `questionid`, `sortorder`, `debitaccountid`, `debitamount`, `creditaccountid`, `creditamount`, `weight_debit/credit_account/amount`, `explanation` |

Charts live at a **course context**; capability `qtype/accounting:managecharts` is required to edit them. The chart-of-accounts ID is referenced from `qtype_accounting_options.chartofaccountsid`.

## Grading model

`classes/scorer.php` implements an **aggregation-based weighted score** (called from `question.php::calculate_fraction`):

1. Aggregate correct entries by `(side, account)` — summing amounts and per-field weights.
2. Aggregate the student's response by `(side, account)` — summing amounts.
3. For each correct `(side, account)` pair, award the account weight if the student named it, and award the amount weight if the summed amount is within 0.01 of the correct sum.
4. Apply per-extra-account deduction (`extraentrydeduction` option, configurable; floored at 0).
5. If `allornothinggrading` is on, snap to {0, 1}.

Account matching is case-insensitive on `accountname` (via the account_id lookup). Amount tolerance: 0.01.

## Frontend (AMD modules)

Wired into PHP via `$PAGE->requires->js_call_amd('qtype_accounting/<module>', 'init', […])`:

- **`question/init(containerId)`** — student quiz: add/delete entry rows, copy debit amount to credit amount, integrate with Select2 (if the theme provides it), apply mobile card layout via `mobile_layout`.
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

./dev/scripts/ci.sh                  # Full CI: tests + all moodle-plugin-ci checks
./dev/scripts/ci.sh checks           # Lint/static analysis only
./dev/scripts/ci.sh codecheck        # phpcs (moodle + moodle-extra)
./dev/scripts/ci.sh codecheck fix    # phpcbf auto-fix
./dev/scripts/ci.sh phpcs            # moodle-plugin-ci phpcs
./dev/scripts/ci.sh phpmd
./dev/scripts/ci.sh phpdoc
./dev/scripts/ci.sh grunt            # eslint + stylelint + gherkinlint
./dev/scripts/ci.sh all              # default check set + phpcpd

./dev/scripts/build.sh               # Minify amd/src → amd/build
./dev/scripts/logs.sh                # docker compose logs -f
./dev/scripts/purge-cache.sh         # admin/cli/purge_caches.php
```

## Common gotchas

- **After PHP changes**, purge caches (`./dev/scripts/purge-cache.sh`) — Moodle aggressively caches plugin discovery + autoloader maps.
- **After JS source changes**, either run `./dev/scripts/build.sh` to rebuild `amd/build/` or set `$CFG->cachejs = false;`. Behat in particular reads `amd/build/`.
- **After DB schema changes**, bump `$plugin->version` and add a step to `db/upgrade.php`. Don't edit `install.xml` without a matching upgrade step (CI checks this).
- **Behat `attempt_question.feature`** depends on the renderer applying `.qtype_accounting-entry-row` to entry `<tr>`s — keep that class name in sync if you ever rename selectors.
- **Single-file bind mounts** in `dev/docker-compose.yml` pin to inodes; tools that unlink+recreate files (sed -i.bak, some editors) leave the container holding a deleted handle. Restart `accounting-moodle` if Moodle suddenly can't find a top-level plugin file.

## Working agreements with Claude

- When creating a plan, surface anything unclear before writing code.
- Keep plans tight; one or two lines per step.
- Don't restate what the diff already says.
