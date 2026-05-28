# MoFT - Moodle Question Type: Buchungssatz

## Project Overview

This is a **Moodle Question Type Plugin** (`qtype_buchungssatz`) for teaching accounting/bookkeeping. It allows instructors to create questions where students must enter accounting entries (Buchungssätze) with debit (Soll) and credit (Haben) accounts and amounts.

The plugin is developed for **Hochschule Flensburg** by **lambda9**.

## Coding Standards

**IMPORTANT:** This project follows the [Moodle Coding Style Guide](https://moodledev.io/general/development/policies/codingstyle).

Key requirements:
- All classes must have PHPDoc with `@package`, `@copyright`, `@license` tags
- All methods must have PHPDoc with descriptive `@param` and `@return` tags
- Use 4-space indentation
- Use `else if` (not `elseif`)
- Use short array syntax `[]`
- Class names: lowercase with underscores (e.g., `qtype_buchungssatz_renderer`)
- No closing `?>` tag in PHP files

## Directory Structure

```
plugin/                          # The Moodle plugin (qtype_buchungssatz)
├── amd/
│   ├── src/                     # JavaScript source files
│   │   ├── question.js          # Student quiz interaction
│   │   └── editform.js          # Edit form functionality
│   └── build/                   # Generated .min.js files (gitignored)
├── backup/moodle2/              # Backup/restore functionality
├── classes/
│   ├── chart_manager.php        # Chart of accounts CRUD operations
│   └── privacy/provider.php     # GDPR privacy provider
├── db/
│   ├── access.php               # Capability definitions
│   ├── install.xml              # Database schema
│   └── upgrade.php              # Database upgrades
├── lang/
│   ├── en/qtype_buchungssatz.php  # English strings
│   └── de/qtype_buchungssatz.php  # German strings
├── pix/
│   └── icon.svg                 # Question type icon (§ symbol)
├── ajax/
│   ├── get_accounts.php         # AJAX endpoint for accounts
│   └── import_entries.php       # AJAX endpoint for CSV import
├── edit_buchungssatz_form.php   # Question editing form
├── question.php                 # Question definition class
├── questiontype.php             # Question type class
├── renderer.php                 # Question rendering
├── manage_charts.php            # Chart management page
├── edit_chart.php               # Chart editing page
├── styles.css                   # Plugin styles
└── version.php                  # Plugin version

docker/                          # Docker development environment
├── docker-compose.yml           # Moodle + MariaDB + phpMyAdmin
├── Dockerfile                   # Moodle container
└── config.php                   # Moodle configuration
```

## Key Concepts

### Terminology (German Accounting)
- **Buchungssatz** = Accounting entry / Journal entry
- **Soll** = Debit (left side of T-account)
- **Haben** = Credit (right side of T-account)
- **Konto** = Account
- **Betrag** = Amount
- **Kontenrahmen** = Chart of accounts (e.g., SKR03)

### Question Structure
A Buchungssatz question consists of:
1. Question text describing a business transaction
2. One or more correct answer entries, each with:
   - Debit account (Sollkonto) - optional
   - Debit amount (Sollbetrag)
   - Credit account (Habenkonto) - required
   - Credit amount (Habenbetrag)
   - Fraction (points for this entry)

### Grading
- Total points = sum of all entry fractions
- Student entries are matched against correct entries
- Partial credit is awarded for partially correct answers
- Account matching is case-insensitive
- Amount matching has 0.01 tolerance for floating point

## Plugin Version

- Component: `qtype_buchungssatz`
- Requires: Moodle 4.1+ (2022112800)
- Maturity: Alpha
- Release: 0.1.0

## Database Tables

### qtype_buchungssatz_charts
Charts of accounts definitions.
- `id`, `name`, `description`, `contextid`, `timecreated`, `timemodified`, `usermodified`

### qtype_buchungssatz_accounts
Individual accounts within a chart.
- `id`, `chartid`, `accountnumber`, `accountname`, `accounttype`, `sortorder`
- Account types: `asset`, `liability`, `equity`, `revenue`, `expense`

### qtype_buchungssatz_options
Question-specific options.
- `id`, `questionid`, `chartofaccountsid`, `allowmultipleentries`, `maxentries`

### qtype_buchungssatz_entries
Correct answer entries for questions.
- `id`, `questionid`, `sortorder`, `sollkonto`, `sollbetrag`, `habenkonto`, `habenbetrag`, `fraction`

## Key Files

### question.php
Defines the `qtype_buchungssatz_question` class:
- `get_expected_data()` - Defines response field names
- `grade_response()` - Grades student responses
- `calculate_fraction()` - Calculates correctness percentage
- `entries_match()` - Compares student entry to correct entry

### questiontype.php
Defines the `qtype_buchungssatz` class:
- `save_question_options()` - Saves question to database
- `get_question_options()` - Loads question from database
- `initialise_question_instance()` - Populates question object

### renderer.php
Defines the `qtype_buchungssatz_renderer` class:
- `formulation_and_controls()` - Renders the question for students
- `render_header_row()` - Renders Soll/Haben header with Account/Amount subheaders
- `render_entry_row()` - Renders input fields for one entry
- `correct_response()` - Renders the correct answer display

### edit_buchungssatz_form.php
Defines the question editing form:
- Uses Moodle's `repeat_elements()` for dynamic entry fields
- JavaScript for dynamic account dropdowns based on selected chart
- CSV import functionality for bulk entry creation

## AMD JavaScript Modules

### amd/src/question.js
Handles student quiz interaction:
- `init(containerId, accounts, maxEntries, allowEdit)` - Initialize question UI
- `addEntry()` - Show next hidden entry row
- `deleteEntry()` - Hide and clear an entry row
- Auto-copies debit amount to credit amount for convenience
- Integrates with Select2 for searchable dropdowns if available

### amd/src/editform.js
Handles the question editing form (defined inline in `edit_buchungssatz_form.php`):
- Dynamic account dropdown population based on selected chart
- Auto-calculation of total points from entry fractions
- Debit amount field disabled when no debit account selected
- CSV import functionality
- Auto-refresh accounts when returning from chart management

## AMD JavaScript Build

The `amd/build/` directory is gitignored. To build:

```bash
./scripts/build.sh
```

This minifies `src/*.js` to `build/*.min.js` using terser (if available). Install terser with `npm install -g terser` for proper minification.

For development, you can also set `$CFG->cachejs = false;` in Moodle's config.php to load directly from `amd/src/`.

## CSV Import Feature

The plugin supports importing accounting entries from CSV files.

**Supported formats:**
- Full: `Debit Account, Debit Name, Debit Amount, Credit Account, Credit Name, Credit Amount`
- Compact: `Debit Account, Debit Amount, Credit Account, Credit Amount`

**Delimiters:** Tab, semicolon, or comma
**Number format:** German format supported (1.234,56)

The import creates a new chart of accounts if needed and populates entry fields automatically.

## Docker Development Environment

Start the environment:
```bash
cd docker
docker-compose up -d
```

Access:
- Moodle: http://localhost:8080
- phpMyAdmin: http://localhost:8081

The plugin is mounted at `/var/www/html/question/type/buchungssatz`.

View PHP logs:
```bash
docker logs -f moft-moodle
```

## Common Development Tasks

### After modifying JavaScript
Run the build script to update minified files:
```bash
./plugin/amd/build.sh
```

### After modifying database schema
Increment version in `version.php` and run Moodle's upgrade.

### Purge Moodle caches
Navigate to Site Administration > Development > Purge caches, or use:
```bash
docker exec moft-moodle php admin/cli/purge_caches.php
```

## Important Notes

- **Debit (Soll) account is optional** - Only Credit (Haben) account is required for an entry
- **Entry deletion** uses Moodle's built-in `repeat_elements()` delete functionality
- **Points auto-calculation** - The question's total points equals sum of entry fractions
- **Account display** shows "number - name" format (e.g., "1200 - Bank") in review mode
- **MAX_STUDENT_ENTRIES = 20** - Students can add up to 20 entries per question

## Student Quiz Display

The quiz display shows:
1. Two-row header:
   - Row 1: "Soll" (Debit) | "Haben" (Credit)
   - Row 2: "Account" | "Amount" | "Account" | "Amount"
2. Entry rows with dropdowns for accounts and number inputs for amounts
3. Add/Delete buttons for managing entries
4. Correct answer display shows account number + name (e.g., "1200 - Bank")

## Planning

- When creating a plan, always ask if something is unclear
- Keep your plan clean and concise, but understandable
