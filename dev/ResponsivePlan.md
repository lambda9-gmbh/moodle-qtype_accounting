# Mobile-Friendly Student View for Buchungssatz Question Type

## Overview

Transform the student quiz view from a 7-column table to a card-based stacked layout on mobile devices, maintaining accounting clarity (Soll/Haben structure) while providing touch-friendly inputs.

## Approach: CSS-Based Table-to-Card Transformation

Use CSS media queries to transform the existing table structure into cards without duplicating HTML markup. This keeps desktop unchanged and provides a mobile-optimized experience.

**Breakpoint:** 768px (matches Bootstrap/Moodle conventions)
- **Desktop (>768px):** Current 7-column table layout
- **Mobile (<=768px):** Card-based layout with stacked Soll/Haben sections

## Visual Design (Mobile)

Each entry becomes a card:
```
┌─────────────────────────────┐
│ ████ SOLL (Debit) ████     │  <- Green header
│ Account: [Dropdown ▼     ] │
│ Amount:  [12.000,00      ] │
├─────────────────────────────┤
│ ████ HABEN (Credit) ████   │  <- Blue header
│ Account: [Dropdown ▼     ] │
│ Amount:  [12.000,00      ] │
├─────────────────────────────┤
│                    [Delete] │
└─────────────────────────────┘
```

## Files to Modify

### 1. `/plugin/renderer.php`

**Changes to `render_entry_row()` (lines 285-332):**

Add `data-label` and `data-section` attributes to table cells:

```php
// Per label cell - add data-section for mobile header
$html .= '<td class="buchungssatz-label-cell" data-section="soll">' . ($isfirst ? $perstr : '') . '</td>';

// Soll account cell - add data-label for mobile
$html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';

// Soll amount cell - add data-label
$html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';

// an label cell - add data-section
$html .= '<td class="buchungssatz-label-cell" data-section="haben">' . ($isfirst ? $anstr : '') . '</td>';

// Haben account cell - add data-label
$html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';

// Haben amount cell - add data-label
$html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
```

**Changes to `correct_response()` method:**

Add `data-label` attributes to the solution table cells for mobile display.

### 2. `/plugin/styles.css`

Add comprehensive mobile styles after existing student table styles (~line 375):

```css
/* ========================================
   MOBILE RESPONSIVE STYLES
   ======================================== */

@media (max-width: 768px) {
    /* Hide table header on mobile */
    .buchungssatz-student-table thead,
    .buchungssatz-student-table colgroup {
        display: none;
    }

    /* Transform table to block layout */
    .buchungssatz-student-table,
    .buchungssatz-student-table tbody,
    .buchungssatz-student-table tr {
        display: block;
        width: 100%;
    }

    /* Each row becomes a card */
    .buchungssatz-student-table .buchungssatz-entry-row {
        display: block;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 1rem;
        padding: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    /* All cells become block elements */
    .buchungssatz-student-table td {
        display: block;
        width: 100%;
        border: none;
        padding: 0.75rem 1rem;
        max-width: none;
        overflow: visible;
        box-sizing: border-box;
    }

    /* Soll section header (label cell) */
    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="soll"] {
        background-color: #d4edda;
        color: #155724;
        font-weight: 700;
        font-style: normal;
        text-align: left;
        padding: 0.75rem 1rem;
    }

    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="soll"]::before {
        content: "Soll";
    }

    /* Hide original Per text on mobile, show Soll instead */
    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="soll"] {
        font-size: 0;
    }
    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="soll"]::before {
        font-size: 1rem;
    }

    /* Haben section header */
    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="haben"] {
        background-color: #cce5ff;
        color: #004085;
        font-weight: 700;
        font-style: normal;
        text-align: left;
        padding: 0.75rem 1rem;
        border-top: 1px solid #dee2e6;
    }

    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="haben"]::before {
        content: "Haben";
    }

    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="haben"] {
        font-size: 0;
    }
    .buchungssatz-student-table td.buchungssatz-label-cell[data-section="haben"]::before {
        font-size: 1rem;
    }

    /* Data cells with labels */
    .buchungssatz-student-table .buchungssatz-data-cell {
        background: #fff;
        padding: 0.5rem 1rem;
    }

    .buchungssatz-student-table .buchungssatz-data-cell::before {
        content: attr(data-label);
        display: block;
        font-size: 0.75rem;
        color: #6c757d;
        font-weight: 500;
        margin-bottom: 0.25rem;
    }

    /* Full-width, touch-friendly inputs */
    .buchungssatz-student-table .buchungssatz-account-select,
    .buchungssatz-student-table select.form-control,
    .buchungssatz-student-table .buchungssatz-amount-input,
    .buchungssatz-student-table input.form-control {
        width: 100% !important;
        min-height: 44px;
        font-size: 16px; /* Prevents iOS zoom */
    }

    /* Actions cell */
    .buchungssatz-student-table .buchungssatz-actions-cell {
        text-align: right;
        padding: 0.75rem 1rem;
        border-top: 1px solid #dee2e6;
        background: #f8f9fa;
    }

    /* Touch-friendly delete button */
    .buchungssatz-student-table .buchungssatz-delete-entry {
        min-width: 44px;
        min-height: 44px;
    }

    /* Full-width add entry button */
    .buchungssatz-controls .buchungssatz-add-entry {
        width: 100%;
        min-height: 44px;
        font-size: 16px;
    }

    /* Readonly spans in review mode */
    .buchungssatz-student-table .buchungssatz-readonly {
        display: block;
        width: 100%;
        min-height: 44px;
        padding: 0.75rem;
        background-color: #f8f9fa;
        border-radius: 4px;
    }

    /* Correct response table - also transform to cards */
    .buchungssatz-correct-response .buchungssatz-solution thead {
        display: none;
    }

    .buchungssatz-correct-response .buchungssatz-solution,
    .buchungssatz-correct-response .buchungssatz-solution tbody,
    .buchungssatz-correct-response .buchungssatz-solution tr {
        display: block;
        width: 100%;
    }

    .buchungssatz-correct-response .buchungssatz-solution tr {
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        margin-bottom: 0.5rem;
        padding: 1rem;
        background: #fff;
    }

    .buchungssatz-correct-response .buchungssatz-solution td {
        display: block;
        width: 100%;
        text-align: left;
        border: none;
        padding: 0.25rem 0;
    }

    .buchungssatz-correct-response .buchungssatz-solution td::before {
        content: attr(data-label) ": ";
        font-weight: 600;
    }
}

/* Extra small screens */
@media (max-width: 375px) {
    .buchungssatz-student-table .buchungssatz-entry-row {
        border-radius: 4px;
    }

    .buchungssatz-student-table td {
        padding: 0.5rem 0.75rem;
    }
}
```

### 3. `/plugin/amd/src/question.js`

Minor update to Select2 initialization for better mobile behavior:

```javascript
// In init() - update Select2 options
if (typeof $.fn.select2 !== 'undefined') {
    container.find('.buchungssatz-account-select').select2({
        placeholder: M.util.get_string('selectaccount', 'qtype_buchungssatz'),
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true
    });
}
```

### 4. `/plugin/lang/de/qtype_buchungssatz.php`

No changes needed - existing strings are used via `data-label` attributes.

## Implementation Steps

1. **Update renderer.php** - Add `data-label` and `data-section` attributes to table cells
2. **Add mobile CSS** - Add the media query styles to styles.css
3. **Update question.js** - Minor Select2 adjustments (optional)
4. **Rebuild JS** - Run `./scripts/build.sh`
5. **Test on devices** - Verify on various screen sizes

## Verification

### Testing Checklist

1. **Desktop (>768px)**
   - Table layout unchanged
   - All existing functionality works

2. **Mobile Quiz Attempt (<768px)**
   - Cards display with Soll/Haben sections clearly separated
   - Account dropdowns are full-width and tappable
   - Amount inputs are full-width with decimal keyboard
   - Add entry button is full-width
   - Delete button has adequate touch target (44px)
   - No horizontal scrolling required

3. **Mobile Review Mode**
   - Student answers display correctly in cards
   - Correct response section displays properly
   - Feedback summary is readable

4. **Test Devices/Widths**
   - 320px (iPhone SE)
   - 375px (iPhone 12 mini)
   - 390px (iPhone 12/13/14)
   - 768px (tablet portrait - breakpoint boundary)
   - 1024px (tablet landscape - should show table)

### Browser DevTools Testing
```
Chrome DevTools > Toggle Device Toolbar (Ctrl+Shift+M)
Test: iPhone SE, iPhone 12 Pro, iPad
```

### Manual Test Commands
```bash
# Purge Moodle cache after changes
docker exec accounting-moodle php admin/cli/purge_caches.php

# Build JavaScript
./scripts/build.sh
```
