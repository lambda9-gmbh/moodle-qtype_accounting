# Changelog

All notable changes to the `qtype_accounting` plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the plugin follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-05-13

Initial public release.

### Added
- Buchungssatz (German accounting journal entry) question type for Moodle 4.1+.
- Question authoring form with dynamic entry rows (debit/credit account, debit/credit amount, per-entry fraction) and CSV import for bulk entries.
- Course-level chart of accounts management (`manage_charts.php`, `edit_chart.php`) backed by `qtype_accounting_charts` and `qtype_accounting_accounts` tables.
- Account types: asset, liability, equity, revenue, expense.
- Student question rendering with searchable account dropdowns (Select2 when available), responsive mobile layout, and auto-copy of debit amount to credit amount.
- Grading: partial credit summed from per-entry fractions, case-insensitive account matching, amount tolerance of 0.01.
- Backup and restore support (`backup/moodle2/`).
- GDPR privacy provider declaring no personal-data storage by the plugin itself.
- English and German language packs.
- AMD JavaScript modules built via Moodle's grunt/rollup pipeline.
