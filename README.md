# MoFT-BuSa - Moodle Fragetyp Buchungssatz

A Moodle question type plugin for practicing accounting entries (Buchungssätze). Students select accounts from a chart of accounts (Kontenplan) and enter debit/credit amounts.

## Features

- **For Students:**
  - Select debit (Soll) and credit (Haben) accounts from dropdown menus
  - Enter amounts for each booking entry
  - Support for compound journal entries (multiple lines)
  - Automatic balance checking (Soll = Haben)
  - Immediate feedback and grading

- **For Teachers/Instructors:**
  - Create questions with multiple correct answer lines
  - Define partial credit per entry
  - Use custom charts of accounts
  - Import/Export charts of accounts via CSV

- **For Administrators:**
  - Manage charts of accounts at system level
  - Import standard German chart (SKR03)
  - CSV import for custom account structures

## Project Structure

```
MoFT/
├── plugin/                           # Moodle qtype plugin
│   ├── amd/src/                     # JavaScript modules
│   │   ├── question.js              # Student interface
│   │   └── editform.js              # Edit form enhancements
│   ├── backup/moodle2/              # Backup/restore handlers
│   ├── classes/                     # PHP classes
│   │   ├── chart_manager.php        # Chart of accounts management
│   │   └── privacy/provider.php     # Privacy API
│   ├── db/                          # Database definitions
│   │   ├── access.php               # Capabilities
│   │   └── install.xml              # Database schema
│   ├── lang/                        # Language strings
│   │   ├── de/qtype_buchungssatz.php
│   │   └── en/qtype_buchungssatz.php
│   ├── edit_buchungssatz_form.php   # Question edit form
│   ├── question.php                 # Question definition
│   ├── questiontype.php             # Question type class
│   ├── renderer.php                 # Question renderer
│   ├── manage_charts.php            # Chart management page
│   ├── styles.css                   # CSS styles
│   └── version.php                  # Plugin version
├── docker/                          # Docker configuration
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── config.php                   # Moodle config
├── scripts/                         # Development scripts
└── README.md
```

## Requirements

- Moodle 4.1 or higher
- PHP 8.0 or higher
- Docker and Docker Compose (for development)

## Quick Start (Development)

1. **Start the development environment:**
   ```bash
   ./scripts/start.sh
   ```
   First startup builds the Docker image (may take several minutes).

2. **Run the Moodle installer (first time only):**
   - Go to http://localhost:8080
   - Follow the installation wizard
   - Database settings:
     - Type: **MariaDB**
     - Host: **mariadb**
     - Name: **moodle**
     - User: **moodle**
     - Password: **moodle_password**
   - Data directory: `/var/www/moodledata`

3. **Install the plugin:**
   After Moodle installation, go to Site administration > Notifications to trigger the plugin installation.

4. **Create a chart of accounts:**
   - Go to Site administration > Plugins > Question types > Buchungssatz
   - Or navigate to: `/question/type/buchungssatz/manage_charts.php`
   - Create a new chart or use "SKR03 Standardkontenplan erstellen" for a default German chart

5. **Create a test question:**
   - Create a quiz in any course
   - Add a new question of type "Buchungssatz"
   - Select your chart of accounts
   - Enter the correct answer (Soll/Haben accounts and amounts)

## Creating Questions

### Basic Question Structure

A Buchungssatz question presents a business transaction scenario. Students must:
1. Select the correct debit account(s) from the Kontenplan
2. Enter the debit amount(s)
3. Select the correct credit account(s)
4. Enter the credit amount(s)

### Example Question

**Question text:**
> "Ein Kunde begleicht eine offene Rechnung über 1.190,00 EUR (inkl. 19% USt) per Banküberweisung."

**Correct Answer:**
| Soll | Betrag | Haben | Betrag |
|------|--------|-------|--------|
| 1200 Bank | 1.190,00 | 1400 Forderungen | 1.190,00 |

### Compound Entries

Enable "Allow multiple entries" for questions requiring multiple booking lines:

**Example: Wareneinkauf auf Ziel**
| Soll | Betrag | Haben | Betrag |
|------|--------|-------|--------|
| 3400 Wareneinkauf | 1.000,00 | 1600 Verbindlichkeiten | 1.190,00 |
| 1576 Vorsteuer 19% | 190,00 | | |

## Chart of Accounts Management

### Creating a Chart

1. Navigate to the management page
2. Enter a name and description
3. Click "Add new chart"

### Importing Accounts (CSV)

CSV format:
```csv
accountnumber,accountname,accounttype
1000,Kasse,asset
1200,Bank,asset
1400,Forderungen aus L+L,asset
1600,Verbindlichkeiten aus L+L,liability
8000,Umsatzerlöse 19%,revenue
3400,Wareneinkauf,expense
```

Account types: `asset`, `liability`, `equity`, `revenue`, `expense`

### Default SKR03

Click "SKR03 Standardkontenplan erstellen" to create a simplified German standard chart (Standardkontenrahmen 03).

## Development

### File Changes

The plugin directory is mounted into the container. Changes to PHP files are reflected immediately. After changes, purge the Moodle cache:

```bash
./scripts/purge-cache.sh
```

### Running Tests

```bash
./scripts/test.sh
```

### Viewing Logs

```bash
./scripts/logs.sh
```

### Reset Environment

```bash
./scripts/reset.sh
```

## Grading

- Each entry line can have a configurable point value (fraction)
- Matching is case-insensitive for account numbers
- Amount matching allows for floating-point tolerance (0.01)
- Partial credit is awarded based on matched entries

## Accessibility

The plugin follows Moodle accessibility guidelines:
- Semantic HTML structure
- ARIA labels on form controls
- Keyboard navigation support
- Screen reader compatible

## License

GNU GPL v3 or later - see [LICENSE](LICENSE)

## Credits

Developed for Hochschule Flensburg by lambda9.

Contact: tausch-nebel@hs-flensburg.de
