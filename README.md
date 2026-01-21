# MoFT-BuSa - Moodle Question Type: Buchungssatz

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
- Define partial credit percentage per entry (must sum to 100%)
- "Distribute equally" button to split points evenly across entries
- Import entries from CSV/Excel files
- Explanation field for each entry (shown in feedback)

### For Administrators
- Manage charts of accounts at system level
- Create custom charts with account numbers, names, and types
- Import test chart (SKR03) with one click
- CSV import/export for account structures

## Requirements

- Moodle 4.1 or higher
- PHP 8.0 or higher
- Docker and Docker Compose (for development)

## Quick Start (Development)

### 1. Start the Development Environment

```bash
./scripts/start.sh
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
- Click the link to manage charts, or navigate directly to `/question/type/buchungssatz/manage_charts.php`
- Create a new chart or click "SKR03 Standardkontenplan erstellen" for a default testing chart

### 5. Create a Test Question

1. Create a quiz in any course
2. Add a new question of type "Accounting Entry (Buchungssatz)"
3. Optionally select a chart of accounts
4. Enter the correct answer entries (Soll/Haben accounts and amounts)
5. Set grade percentages (must sum to 100%)

## Project Structure

```
MoFT/
├── plugin/                              # Moodle qtype plugin
│   ├── amd/
│   │   ├── src/                         # JavaScript ES modules
│   │   │   ├── question.js              # Student quiz interaction
│   │   │   └── editform.js              # Edit form functionality
│   │   └── build/                       # Minified JS (auto-generated)
│   ├── ajax/                            # AJAX endpoints
│   │   ├── get_accounts.php             # Fetch accounts for chart
│   │   └── import_entries.php           # CSV import endpoint
│   ├── backup/moodle2/                  # Backup/restore handlers
│   ├── classes/
│   │   ├── chart_manager.php            # Chart of accounts CRUD
│   │   └── privacy/provider.php         # GDPR privacy API
│   ├── db/
│   │   ├── access.php                   # Capability definitions
│   │   ├── install.xml                  # Database schema
│   │   └── upgrade.php                  # Database upgrades
│   ├── lang/
│   │   ├── de/qtype_buchungssatz.php    # German strings
│   │   └── en/qtype_buchungssatz.php    # English strings
│   ├── pix/
│   │   └── icon.svg                     # Question type icon
│   ├── tests/
│   │   ├── behat/                       # Behat acceptance tests
│   │   │   ├── attempt_question.feature
│   │   │   ├── create_question.feature
│   │   │   └── manage_charts.feature
│   │   ├── generator/lib.php            # Test data generator
│   │   ├── helper.php                   # Test helper class
│   │   ├── question_test.php            # PHPUnit: question grading
│   │   └── questiontype_test.php        # PHPUnit: save/load
│   ├── edit_buchungssatz_form.php       # Question edit form
│   ├── edit_chart.php                   # Chart editing page
│   ├── manage_charts.php                # Chart management page
│   ├── question.php                     # Question definition class
│   ├── questiontype.php                 # Question type class
│   ├── renderer.php                     # Question renderer
│   ├── settings.php                     # Admin settings
│   ├── styles.css                       # CSS styles
│   └── version.php                      # Plugin version
├── docker/                              # Docker development environment
│   ├── docker-compose.yml               # Moodle + MariaDB + phpMyAdmin + Selenium
│   ├── Dockerfile                       # Moodle container
│   └── config.php                       # Moodle configuration
├── scripts/                             # Development scripts
│   ├── start.sh                         # Start Docker environment
│   ├── stop.sh                          # Stop Docker environment
│   ├── reset.sh                         # Reset environment
│   ├── logs.sh                          # View container logs
│   ├── purge-cache.sh                   # Purge Moodle caches
│   └── test.sh                          # Run tests
├── CLAUDE.md                            # AI assistant instructions
└── README.md                            # This file
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

### Grade Distribution

- Each entry has a grade percentage (0-100%)
- All grades must sum to exactly 100%
- Use the "Distribute equally" button to split points evenly

## Chart of Accounts Management

### Creating a Chart

1. Navigate to **Site admin > Plugins > Question types > Accounting Entry**
2. Enter a name and description
3. Click "Add new chart"
4. Click "Edit Accounts" to add individual accounts

### CSV Import Format

```csv
accountnumber,accountname,accounttype
1000,Cash,asset
1200,Bank,asset
1400,Receivables,asset
1600,Payables,liability
8000,Revenue,revenue
3400,Purchases,expense
```

**Account types:** `asset`, `liability`, `equity`, `revenue`, `expense`

### Default SKR03

Click "SKR03 Standardkontenplan erstellen" to create a simplified German standard chart (Standardkontenrahmen 03) with common accounts.

## Development

### Development Scripts

```bash
# Start environment
./scripts/start.sh

# Stop environment
./scripts/stop.sh

# View logs
./scripts/logs.sh

# Purge Moodle caches
./scripts/purge-cache.sh

# Reset environment (destroys data)
./scripts/reset.sh
```

### Running Tests

Tests are automatically initialized on first run:

```bash
# Run all tests (PHPUnit + Behat)
./scripts/test.sh

# Run PHPUnit tests only
./scripts/test.sh phpunit

# Run Behat acceptance tests only
./scripts/test.sh behat
```

### Building JavaScript

After modifying JavaScript files in `plugin/amd/src/`:

```bash
./scripts/build.sh
```

The script minifies files using [terser](https://terser.org/) if available. Install it with `npm install -g terser` for proper minification.

Or disable JS caching in Moodle for development:
```php
$CFG->cachejs = false;
```

### Purging Caches

After PHP changes, purge Moodle caches:

```bash
./scripts/purge-cache.sh
# or
docker exec moft-moodle php admin/cli/purge_caches.php
```

## Grading Logic

- Each entry line has a configurable point value (grade percentage)
- Student entries are matched against correct entries
- Matching is case-insensitive for account numbers
- Amount matching allows 0.01 tolerance for floating-point precision
- Partial credit is awarded based on matched entries
- Total score = sum of matched entry fractions

## Database Tables

| Table                         | Purpose                           |
|-------------------------------|-----------------------------------|
| `qtype_buchungssatz_charts`   | Chart of accounts definitions     |
| `qtype_buchungssatz_accounts` | Individual accounts within charts |
| `qtype_buchungssatz_options`  | Question-specific settings        |
| `qtype_buchungssatz_entries`  | Correct answer entries            |

## Terminology

| German       | English                           |
|--------------|-----------------------------------|
| Buchungssatz | Journal entry / Accounting entry  |
| Soll         | Debit                             |
| Haben        | Credit                            |
| Konto        | Account                           |
| Betrag       | Amount                            |
| Kontenrahmen | Chart of accounts                 |
| SKR03        | Standard German chart of accounts |

## License

GNU GPL v3 or later - see [LICENSE](LICENSE)

## Credits

Developed for **Hochschule Flensburg** by **lambda9**.

Contact: tausch-nebel@hs-flensburg.de
