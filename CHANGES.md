# Changelog

All notable changes to the `qtype_accounting` plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the plugin follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Points grading mode: one point per account-amount pair, with the default mark derived from the correct answer, site-wide rules for strict pairs and accounts without amounts, and a point deduction per extra account.
- Chart of accounts templates managed on the plugin settings page; teachers copy their accounts into questions with **Copy accounts from**, which also offers the other questions of the same question bank.
- Site settings to hide the leading "Dr." / "Per" column, set the minimum width of the amount inputs, and hide the "Number of additional accounts in selection list" option.
- Selectable currency symbol (€, $, £, CHF or none) after the amount fields, in the review and in the correct answer, with a site default.
- ODS chart uploads and account imports, reading column A of the first worksheet with the same header and duplicate handling as CSV imports.
- GitHub Actions checks for Moodle 4.5 and 5.1 on MariaDB and PostgreSQL, using the standard Moodle Plugin CI setup. Added number-format, restore-attempt, hint-option, no-JavaScript form, and atomic-import regressions.
- Working interactive hint options for clearing incorrect fields and showing correct parts, random-question selection, and response-analysis classifications.
- Native Android testing helper for the official Moodle 5.2.1 APK, with a dedicated emulator, local server connection, and DE/US sample quizzes.
- Moodle App question handler with debit/credit entry cards, multiple entries, local-answer restoration, explicit clearing of deleted entries, and read-only review. Deployment requires a Moodle upgrade/cache purge and refreshing the site's app session.
- Chart of accounts templates can be created directly in Moodle by entering a name, then adding accounts in the editor without uploading a CSV file.

### Changed
- Template pages use Moodle's building blocks: templates and their accounts are renamed in place, accounts are added in a modal form, deletions are confirmed in modals, and the lists are Mustache templates.
- Requires Moodle 4.5.2, which provides the question identity hash API used to avoid duplicate questions on restore (MDL-83541).
- Each question version has its own account list (`qtype_accounting_qaccounts`), entered in the question form one account per line. Changing a template, another question or a later version no longer changes existing questions, their correct answers or their attempts. Accounts keep their key across versions while their name is unchanged; regrading with another version is refused if that version does not keep every account. The upgrade copies each course chart's accounts into the questions using it, with the former account IDs as keys, so correct answers and saved attempts stay valid; course charts are then removed and site-wide charts remain as templates. Backups and XML exports of earlier versions are imported the same way.
- The question editor's JavaScript is split into modules under `amd/src/editform/` (state, hidden fields, field states, accounts, entry rows, validation); `editform.js` only sets up the form.
- GitHub Actions also run the full check suite against Moodle 5.2 and 5.3 on MariaDB and PostgreSQL.
- The booking table, the correct answer, the feedback summary and the editor's entry table are rendered from Mustache templates (renderables in `classes/output/`), so themes can override them. The HTML structure used by the JavaScript and the Moodle App is unchanged.

### Removed
- The Dockerised development environment in `dev/` and the CI steps that depended on it; README and CONTRIBUTING describe the standard Moodle Plugin CI workflow instead.
- Course charts of accounts with their management pages in the course, the capability `qtype/accounting:managecharts`, and the chart selection in the question form; questions have their own accounts instead.

### Fixed
- Template downloads quote account names containing commas, quotes or line breaks as CSV fields, and imports read such quoted names back.
- The question editor's no-submit buttons (copy accounts, update rows) no longer run the client-side validation of the correct answer.
- Numeric account names such as `1200` are kept as text when importing Moodle XML.
- Card view labels are linked to their controls, delete buttons there have accessible names, and account selects use the full card width.
- Delete buttons have accessible names ("Delete debit entry", "Delete credit entry"), and reviewed fields state their correctness for screen readers instead of by colour alone.
- Template changes (rename, add or edit accounts) are only accepted as form submissions, and template downloads use Moodle's file delivery.
- Attempts keep the points-mode grading rules (strict pairs, accounts without amounts) that applied when they started, so changing these site settings no longer changes the grades of existing attempts on regrade.
- The Debit and Credit headings of the narrow table layout are translated instead of being fixed English text in the stylesheet.
- The student entry table stays inside the content area and switches to cards when the question is narrow, e.g. next to open drawers.
- Students can no longer delete the last entry of the debit or credit side.
- Delete buttons fit the narrow table columns, and the chart editor no longer uses Bootstrap 4 classes removed in Bootstrap 5.
- Updated question-form and restore fixtures for Moodle 5.1 module question banks, preserving per-bank identity and account-mapping assertions. Tests use the current XML parser and PHPUnit attribute metadata while remaining compatible with Moodle 4.5/PHPUnit 9.
- Local Grunt checks build a container-native snapshot to avoid Docker Desktop file-size races during backup. Behat question fixtures keep charts in the owning course when question categories live in a Moodle 5.1 question-bank module context.
- Local source scanners now inspect a complete working-tree snapshot and share explicit development/dependency exclusions with GitHub. Both runners verify scanner scope before linting; scope regressions reproduce the previously hidden GitHub failures when the filter is removed.
- Removed the host development shell; CI JavaScript tests now run in the Moodle container, optional DOM/runner tests have a Docker image, and the Android helper reuses existing host SDK tools.
- Local CI now matches GitHub's checks and warning thresholds, includes the standalone JavaScript regressions, and fails explicitly when tools or Selenium are missing instead of installing dependencies or starting services.
- Removed optional Select2 integration; account dropdowns use native controls throughout. Student row visibility and mobile switching now use stylesheet classes, with theme-aware colors and a CSS variable for calculated table width.
- Moodle App amount fields now format saved values and amounts on blur using the question's German or US format. Moved feedback summaries use the app's orange feedback background, and empty feedback wrappers no longer leave an orange panel below the solution.
- Moodle App correct answers now use stacked debit and credit cards, preserving formatted amounts and entry explanations and respecting quiz answer visibility.
- Moodle App review now highlights correct and incorrect accounts and amounts independently using the app's theme-aware feedback backgrounds.
- Generated correct responses and response summaries now respect German and US number formats. German input such as `1.000` is parsed as one thousand; formatting is isolated per question.
- Added and deleted entry rows no longer disappear beyond index 19. Browser and app answers share a 100-row limit, deleted student indices are reused for autosave, and larger imported solutions remain readable.
- Used accounts can be renamed while retaining their IDs. Chart and account deletion requires explicit confirmation with warnings about existing questions, grading, and saved attempts; account warnings list affected questions. Duplicate and overlong names produce readable errors. Imports and question saves roll back completely on failure.
- Restore now resets chart state for every question and recodes student attempt account references. Legacy restore and migration reads and chart counts are batched.
- Question editing also works without JavaScript through the chart/row refresh controls. Both amount sides follow the same account-selection rules, controls have accessible labels, and rendering uses Moodle's HTML writer and theme-aware color fallbacks.
- Nested unsaved-change confirmations no longer consume the chart overlay's final refresh callback.
- Moodle App attempts now keep at least one debit entry and one credit entry. Delete buttons are shown only when that side has multiple visible entries; the controller also prevents removing the last entry or clearing its values.
- Moodle App entry buttons now bind to the compiled component. Ionic account/amount controls follow the app theme, and persistent native named fields preserve entered and added rows when checking or submitting answers, including explicit clearing of deleted entries. Added Angular DOM regression tests using Moodle App 5.2.1's Angular version.
- Cancelling question editing now skips account and balance validation, allowing incomplete or unbalanced entries to be discarded without saving.
- Chart deletion opens a confirmation modal naming the chart. Deletion requires an explicit "Delete chart" confirmation submitted by POST, with a confirmation page available when JavaScript is disabled.
- Question editing now shows missing solution amount errors beside the affected fields with Moodle's standard validation styling and preserves the submitted chart, accounts, amounts, and weights after failed validation. Normalised decimal amounts are validated consistently in both number formats.
- Removed N+1 database reads from question-form account loading and chart matching while preserving sorting and matching results.
- Added behavior and query-count regression tests for those two paths, plus behavior coverage for the unchanged legacy account-name migration.
- Course backup and restore now remaps debit and credit account IDs to the restored chart, including when reusing an existing chart. Chart matching is scoped to the destination course instead of a process-wide name cache.
- Added course backup/restore regression tests for shared charts, existing charts, one-sided entries, and consecutive restores into different courses.

Backups made before this fix omit the source IDs from chart accounts, so their ID-based answer selections cannot be reliably reconstructed. Create a fresh backup after updating the plugin; already affected questions need their accounts reconfigured.

## [1.0.0] - 2026-05-28

Stable release. No functional changes since `0.1.0`; promoted to `MATURITY_STABLE` and considered ready for production use.

### Changed
- Maturity raised from `MATURITY_BETA` to `MATURITY_STABLE`.
- Declared `$plugin->supported = [405, 501]` — verified against Moodle 4.5 LTS through 5.1.
- All in-tree code, comments, and identifiers normalised to English.
- CSS selectors namespaced under `.qtype_accounting-*` to prevent collisions with other plugins or themes.
- Repository licensing aligned: `LICENSE` is the GPL v3 text; per-file headers reference GPL v3 or later.

### Fixed
- CI grunt step on macOS Docker Desktop hosts: static text files (`README.md`, `CHANGES.md`, `LICENSE`) are no longer single-file bind-mounted into the container — they were tripping a stale-`filesize()` race in Symfony's `Filesystem::copy()` used by `moodle-plugin-ci grunt`'s backup step. `dev/scripts/ci.sh` now syncs them into the container's writable layer on every run.

## [0.1.0] - 2026-05-13

Initial public release.

### Added
- Buchungssatz (accounting journal entry) question type for Moodle 4.5 LTS and later, on PHP 8.1+, verified against MariaDB 10.11 and PostgreSQL 16.
- Question authoring form with dynamic entry rows. Each row carries a debit account, debit amount, credit account, credit amount, and independent per-field weights (1, 2, or 3) for grading.
- Authoring options: chart of accounts selection, number of decoy accounts in dropdowns, number-format toggle (DE/US), deduction-per-extra-entry penalty, all-or-nothing grading.
- Course-scoped chart of accounts management (`manage_charts.php`, `edit_chart.php`) backed by the `qtype_accounting_charts` and `qtype_accounting_accounts` tables, gated by the `qtype/accounting:managecharts` capability.
- CSV import and export of charts of accounts (UTF-8 and Windows-1252 input, BOM stripping).
- Student question rendering with account dropdowns that integrate with Select2 when the theme provides it, a responsive mobile card layout, auto-copy of debit amount to credit amount on row entry, and deterministic per-attempt decoy shuffling (seeded by attempt).
- Aggregation-based grading (`classes/scorer.php`): for each `(side, account)` pair the configured account weight is awarded if the student named that account on that side, and the amount weight is awarded if the summed amount is within 0.01 of the correct sum. The configured percentage is subtracted per extra account named (each side independent), floored at 0, and snapped to 0/1 in all-or-nothing mode. Account matching is case-insensitive.
- Side-level feedback messages ("The debit side is incorrect." / "The credit side is incorrect." and partial variants), per-field correct/incorrect colouring on the student's response, and the full correct answer rendered alongside.
- Moodle XML question import and export, including the embedded chart of accounts so questions move between courses and sites with their account list intact.
- Backup and restore via `backup/moodle2/`, deduplicating against matching existing charts in the destination context.
- GDPR Privacy API provider declaring the `usermodified` audit column on `qtype_accounting_charts` and supporting export, deletion, and userlist queries for that column.
- English (shipped) and German (development-only; `lang/de/` is `export-ignore`d in `.gitattributes`) language packs.
- AMD JavaScript modules built via Moodle's grunt + rollup pipeline (`amd/src/{editform,question,entry_utils,mobile_layout}.js`).
