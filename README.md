# moodle-qtype_accounting — Moodle Question Type: Accounting Entry (Buchungssatz)

A Moodle question type plugin for practicing accounting entries (Buchungssätze). Students select accounts from a chart of accounts (Kontenplan) and enter debit/credit amounts.

Developed for **Hochschule Flensburg** by **lambda9**.

## Features

### For Students
- Select debit (Soll) and credit (Haben) accounts from searchable dropdown menus
- Enter amounts for each booking entry
- Support for compound journal entries (multiple lines)
- Add/remove entry rows dynamically
- Immediate feedback and grading after submission
- View correct answers with account names

### For Teachers/Instructors
- Create questions with multiple correct answer entries
- Define partial credit weights
- Manage charts of accounts at course level
- CSV import/export for account structures

## Requirements

- Moodle 4.1 or higher
- PHP 8.0 or higher
- Docker and Docker Compose (for development)

## Quick Start (Development)

### 1. Start the Development Environment

```bash
./dev/scripts/start.sh
```

First startup builds the Docker image and may take several minutes.

**Services:**
- Moodle: http://localhost:8080
- phpMyAdmin: http://localhost:8081
- Selenium (for Behat tests): http://localhost:4444

### 2. Run Moodle Installation (First Time Only)

1. Go to http://localhost:8080
2. Follow the installation wizard, if asked for database settings enter the following:
   - Type: **MariaDB**
   - Host: **mariadb**
   - Name: **moodle**
   - User: **moodle**
   - Password: **moodle_password**

### 3. Install the Plugin

After Moodle installation, go to **Site administration > Notifications** to trigger the plugin installation.

### 4. Create a Chart of Accounts (Optional)

- Go to **Site administration > Plugins > Question types > Accounting Entry (Buchungssatz)**
- Click the link to manage charts, or navigate directly to `/question/type/accounting/manage_charts.php`
- Create a new chart or click "SKR03 Standardkontenplan erstellen" for a default testing chart

### 5. Create a Test Question

1. Create a quiz in any course
2. Add a new question of type "Accounting Entry (Buchungssatz)"
3. Select a chart of accounts
4. Enter the correct answer entries (Debit/Credit accounts and amounts)

## Project Structure

```
moodle-qtype_accounting/                 # Plugin root (unpacks to question/type/accounting/)
├── amd/
│   ├── src/                             # JavaScript ES modules
│   │   ├── question.js                  # Student quiz interaction
│   │   ├── editform.js                  # Edit form functionality
│   │   ├── entry_utils.js               # Shared entry helpers
│   │   └── mobile_layout.js             # Mobile layout
│   └── build/                           # Minified JS (auto-generated)
├── ajax/                                # AJAX endpoints
│   ├── get_accounts.php                 # Fetch accounts for chart
│   └── import_entries.php               # CSV import endpoint
├── backup/moodle2/                      # Backup/restore handlers
│   ├── backup_qtype_accounting_plugin.class.php
│   └── restore_qtype_accounting_plugin.class.php
├── classes/                             # Autoloaded classes (qtype_accounting namespace)
│   ├── chart_manager.php                # Chart of accounts CRUD
│   ├── account_manager.php              # Account CRUD
│   ├── scorer.php                       # Grading logic
│   └── privacy/provider.php             # GDPR privacy API
├── db/
│   ├── access.php                       # Capability definitions
│   ├── install.xml                      # Database schema
│   └── upgrade.php                      # Database upgrades
├── lang/
│   ├── en/qtype_accounting.php          # English strings (shipped)
│   └── de/qtype_accounting.php          # German strings (dev only — export-ignored)
├── pix/
│   └── icon.svg                         # Question type icon
├── tests/
│   ├── behat/                           # Behat acceptance tests
│   │   ├── attempt_question.feature
│   │   ├── create_question.feature
│   │   └── grading.feature
│   ├── generator/lib.php                # Test data generator
│   ├── helper.php                       # Test helper class
│   ├── question_test.php                # PHPUnit: question grading
│   └── questiontype_test.php            # PHPUnit: save/load
├── edit_accounting_form.php             # Question edit form (Moodle convention)
├── edit_chart.php                       # Chart editing page
├── manage_charts.php                    # Chart management page
├── question.php                         # Question definition class
├── questiontype.php                     # Question type class
├── renderer.php                         # Question renderer
├── styles.css                           # CSS styles
├── version.php                          # Plugin version
├── README.md                            # This file
├── CHANGES.md                           # Changelog
├── .phpcs.xml.dist                      # PHP_CodeSniffer ruleset
├── .phpmd.xml                           # PHPMD ruleset
├── .gitattributes                       # Marks dev/ as export-ignore for release ZIPs
└── dev/                                 # Development tooling (NOT shipped in release ZIP)
    ├── docker-compose.yml               # Moodle + MariaDB + phpMyAdmin + Selenium
    ├── docker/
    │   ├── Dockerfile
    │   └── config.php                   # Moodle configuration
    ├── init/                            # MariaDB init SQL
    ├── scripts/                         # build / ci / test / deploy / start / stop / …
    └── CLAUDE.md                        # AI assistant instructions
```

## Creating Questions

### Basic Question Structure

A Buchungssatz question presents a business transaction scenario. Students must:
1. Select the correct debit account(s) from the dropdown (or enter manually)
2. Enter the debit amount(s)
3. Select the correct credit account(s)
4. Enter the credit amount(s)

### Example: Simple Entry

**Question text:**
> "A customer pays an invoice of 1,000 EUR via bank transfer."

**Correct Answer:**
| Debit (Soll) | Amount | Credit (Haben) | Amount |
|--------------|--------|----------------|--------|
| 1200 Bank    | 1,000  | 1400 Receivables | 1,000 |

### Example: Compound Entry

**Question text:**
> "Purchase goods on credit for 1,000 EUR plus 19% VAT."

**Correct Answer:**
| Debit (Soll) | Amount | Credit (Haben) | Amount |
|--------------|--------|----------------|--------|
| 3400 Purchases | 1,000 | 1600 Payables | 1,190 |
| 1576 Input VAT | 190   |                |       |


## Chart of Accounts Management

### Creating a Chart

1. Navigate to **Site admin > Plugins > Question types > Accounting Entry**
2. Enter a name and description
3. Click "Add new chart"
4. Click "Edit Accounts" to add individual accounts

## Development

### Development Scripts

```bash
# Start environment
./dev/scripts/start.sh

# Stop environment
./dev/scripts/stop.sh

# View logs
./dev/scripts/logs.sh

# Purge Moodle caches
./dev/scripts/purge-cache.sh

# Reset environment (destroys data)
./dev/scripts/reset.sh
```

### Running Tests

Tests are automatically initialized on first run:

```bash
# Run all tests (PHPUnit + Behat)
./dev/scripts/test.sh

# Run PHPUnit tests only
./dev/scripts/test.sh phpunit

# Run Behat acceptance tests only
./dev/scripts/test.sh behat
```

### Building JavaScript

After modifying JavaScript files in `plugin/amd/src/`:

```bash
./dev/scripts/build.sh
```

The script minifies files using [terser](https://terser.org/) if available. Install it with `npm install -g terser` for proper minification.

Or disable JS caching in Moodle for development:
```php
$CFG->cachejs = false;
```

### Purging Caches

After PHP changes, purge Moodle caches:

```bash
./dev/scripts/purge-cache.sh
# or
docker exec accounting-moodle php admin/cli/purge_caches.php
```

## Database Tables

| Table                         | Purpose                           |
|-------------------------------|-----------------------------------|
| `qtype_accounting_charts`   | Chart of accounts definitions     |
| `qtype_accounting_accounts` | Individual accounts within charts |
| `qtype_accounting_options`  | Question-specific settings        |
| `qtype_accounting_entries`  | Correct answer entries            |

## Terminology

| German       | English                           |
|--------------|-----------------------------------|
| Buchungssatz | Journal entry / Accounting entry  |
| Debit         | Debit                             |
| Credit        | Credit                            |
| Account        | Account                           |
| Amount       | Amount                            |
| Kontenrahmen | Chart of accounts                 |
| SKR03        | Standard German chart of accounts |

## License

GNU GPL v3 or later - see [LICENSE](LICENSE)

## Credits

Developed for **Hochschule Flensburg** by **lambda9**.

Contact: tausch-nebel@hs-flensburg.de
