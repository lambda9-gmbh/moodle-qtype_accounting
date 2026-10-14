@qtype @qtype_accounting
Feature: Create and edit Buchungssatz questions
  As a teacher
  I need to create and edit accounting entry questions
  So that I can test students' bookkeeping knowledge

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  @javascript
  Scenario: Access the Buchungssatz question creation form
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Adding an Accounting Entry question"
    And I should see "Question name"
    And I should see "Correct Answer"

  @javascript
  Scenario: Cancel a new question with empty required fields
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I press "Cancel"
    Then I should see "Create a new question ..."
    And I should not see "Adding an Accounting Entry question"

  @javascript
  Scenario: Cancel unbalanced entries after a rejected save
    Given the accounting chart "Main" with accounts "Bank, Cash" exists in course "Course 1"
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Cancelled accounting question"
    And I set the field "Question text" to "Record the transaction."
    And I set the field "Chart of Accounts" to "Main"
    And I set the field "debitaccount_display[0]" to "Bank"
    And I set the field "debitamount_display[0]" to "100"
    And I press "id_submitbutton"
    Then I should see "Please select an account, or remove this entry."
    And I should see "Total debit amount must equal total credit amount for a valid accounting entry."
    When I press "Cancel"
    Then I should see "Create a new question ..."
    And I should not see "Adding an Accounting Entry question"
    And I should not see "Cancelled accounting question"

  @javascript
  Scenario: Question form shows entry input fields
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Chart of Accounts"
    And I should see "Correct Answer"
    And I should see "Dr."
    And I should see "Debit"
    And I should see "Credit"

  @javascript
  Scenario: Validation error when question name is empty
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question text" to "Test question"
    And I press "id_submitbutton"
    Then I should see "You must supply a value here"

  @javascript
  Scenario: Weight fields are visible in entry table
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Weight"

  @javascript
  Scenario: Number format option is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Number format"
    And I should see "#.###,00"
    And I should see "#,###.00"

  @javascript
  Scenario: All-or-nothing option is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "No partial credit"

  @javascript
  Scenario: Multiple tries section is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Multiple tries"

  @javascript
  Scenario: Add entry button works
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Add Debit Entry"
    When I click on "Add Debit Entry" "button"
    Then ".qtype_accounting-entry-row" "css_element" should exist
    And "#id_debitamount_display_2" "css_element" should exist

  @javascript
  Scenario Outline: Missing solution amounts show guidance and preserve selections after saving
    Given the accounting chart "Main" with accounts "Bank, Cash" exists in course "Course 1"
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Missing solution amounts"
    And I set the field "Question text" to "Record the transaction."
    And I set the field "Number format" to "<format>"
    And I set the field "Chart of Accounts" to "Main"
    And I set the field "debitaccount_display[0]" to "Bank"
    And I set the field "creditaccount_display[0]" to "Cash"
    And I set the field "debitaccount_display[1]" to "Bank"
    And I set the field "creditaccount_display[1]" to "Cash"
    And I set the field "weight_debitaccount_display[0]" to "3"
    And I press "id_submitbutton"
    Then I should see "Debit amount is required when a debit account is selected."
    And I should see "Credit amount is required."
    And "input[name='debitamount_display[0]'].is-invalid" "css_element" should exist
    And "input[name='creditamount_display[0]'].is-invalid" "css_element" should exist
    And "#id_debitamount_display_0_error.form-control-feedback.invalid-feedback" "css_element" should exist
    And "#id_creditamount_display_0_error.form-control-feedback.invalid-feedback" "css_element" should exist
    And the field "Chart of Accounts" matches value "Main"
    And the field "debitaccount_display[0]" matches value "Bank"
    And the field "creditaccount_display[0]" matches value "Cash"
    And the field "debitaccount_display[1]" matches value "Bank"
    And the field "creditaccount_display[1]" matches value "Cash"
    And the field "weight_debitaccount_display[0]" matches value "3"
    When I set the field "debitamount_display[0]" to "<amount>"
    And I set the field "creditamount_display[0]" to "<amount>"
    And I set the field "debitamount_display[1]" to "<amount>"
    And I set the field "creditamount_display[1]" to "<amount>"
    And I press "id_submitbutton"
    Then I should see "Missing solution amounts"
    And I should not see "Adding an Accounting Entry question"

    Examples:
      | format   | amount |
      | #.###,00 | 12,50  |
      | #,###.00 | 12.50  |

  @javascript
  Scenario: Closing chart management refreshes options and preserves the unsaved question
    Given the accounting chart "Main" with accounts "Bank, Cash" exists in course "Course 1"
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Unsaved accounting question"
    And I set the field "Question text" to "Unsaved question text"
    And I set the field "Number format" to "#.###,00"
    And I set the field "Chart of Accounts" to "Main"
    And I set the field "debitaccount_display[0]" to "Bank"
    And I set the field "debitamount_display[0]" to "100"
    And I set the field "creditaccount_display[0]" to "Cash"
    And I set the field "creditamount_display[0]" to "100"
    And I click on "Manage chart of accounts" "link"
    Then ".qtype_accounting-chart-overlay" "css_element" should be visible
    When I switch to "qtype_accounting-chart-frame" iframe
    Then I should see "Main"
    And I should not see "Save and continue"
    When I click on "Main" "link"
    And I set the field "chartname" to "Renamed"
    And I press "Save changes"
    And I set the field "accountname" to "Inventory"
    And I press "Add Account"
    And I switch to the main frame
    And I press "Done"
    Then I should see "Charts of accounts updated."
    And the field "Chart of Accounts" matches value "Renamed"
    And the field "Question name" matches value "Unsaved accounting question"
    And the field "Question text" matches value "Unsaved question text"
    And the field "debitaccount_display[0]" matches value "Bank"
    And the field "creditaccount_display[0]" matches value "Cash"
    And the field "debitamount_display[0]" matches value "100,00"
    And the field "creditamount_display[0]" matches value "100,00"
    When I set the field "creditaccount_display[0]" to "Inventory"
    And I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe
    And I click on "Renamed" "link"
    And I click on "Delete" "link" in the "Bank" "table_row"
    And I press "Continue"
    And I switch to the main frame
    And I press "Done"
    Then I should see "A selected chart or account is no longer available."
    And the field "debitaccount_display[0]" matches value "Bank"
    And the field "debitamount_display[0]" matches value "100,00"
    When I click on "Add Debit Entry" "button"
    Then the field "debitaccount_display[0]" matches value "Bank"
    And the field "debitamount_display[0]" matches value "100,00"
    When I set the field "debitaccount_display[0]" to "Cash"
    And I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe
    And I switch to the main frame
    And I press "Done"
    Then I should see "Charts of accounts updated."
    And the field "debitamount_display[0]" matches value "100,00"
    When I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe
    And I click on "Delete" "link" in the "Renamed" "table_row"
    And I press "Delete chart"
    And I switch to the main frame
    And I press "Done"
    Then I should see "A selected chart or account is no longer available."
    And the field "Chart of Accounts" matches value "Renamed"
    And the field "debitaccount_display[0]" matches value "Cash"
    And the field "debitamount_display[0]" matches value "100,00"
    Given the accounting chart "Additional" with accounts "New account" exists in course "Course 1"
    When I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe
    And I switch to the main frame
    And I press "Done"
    And I set the field "Chart of Accounts" to "Additional"
    And I set the field "debitaccount_display[0]" to "New account"
    Then the field "Chart of Accounts" matches value "Additional"

  @javascript
  Scenario: Closing the overlay protects unfinished edits and keeps already saved changes
    Given the accounting chart "Main" with accounts "Bank, Cash" exists in course "Course 1"
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Unsaved accounting question"
    And I set the field "Chart of Accounts" to "Main"
    And I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe
    And I click on "Main" "link"
    And I set the field "chartname" to "Saved rename"
    And I press "Save changes"
    And I set the field "accountname" to "Unfinished account"
    And I switch to the main frame
    And I press "Done"
    Then I should see "Unsaved chart changes"
    When I click on "Cancel" "button" in the "Unsaved chart changes" "dialogue"
    And I switch to "qtype_accounting-chart-frame" iframe
    Then the field "accountname" matches value "Unfinished account"
    When I switch to the main frame
    And I press "Done"
    And I click on "Discard unsaved edits" "button" in the "Unsaved chart changes" "dialogue"
    Then I should see "Charts of accounts updated."
    And the field "Chart of Accounts" matches value "Saved rename"
    And the field "Question name" matches value "Unsaved accounting question"
    When I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe
    And I click on "Saved rename" "link"
    Then I should not see "Unfinished account"
    When I switch to the main frame
    And I click on "Close" "button" in the "Manage chart of accounts" "dialogue"
    Then I should see "Charts of accounts updated."
    And the field "Question name" matches value "Unsaved accounting question"

  @javascript
  Scenario: Create a chart in the overlay and select it after closing
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe
    And I set the field "Chart name" to "New chart"
    And I press "Create chart of accounts"
    Then I should see "New chart"
    When I set the field "accountname" to "New account"
    And I press "Add Account"
    And I click on "Manage Charts of Accounts" "link"
    Then I should see "New chart"
    When I switch to the main frame
    And I press "Done"
    Then I should see "Charts of accounts updated."
    When I set the field "Chart of Accounts" to "New chart"
    And I set the field "debitaccount_display[0]" to "New account"
    Then the field "debitaccount_display[0]" matches value "New account"
