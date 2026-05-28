# Changelog

All notable changes to the `qtype_accounting` plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the plugin follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
