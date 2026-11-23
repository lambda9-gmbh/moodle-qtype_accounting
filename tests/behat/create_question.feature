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
  Scenario Outline: Each accounting side keeps at least one entry after adding and deleting rows
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I click on ".qtype_accounting-delete-debit[data-index='1']" "css_element"
    And I click on ".qtype_accounting-delete-credit[data-index='1']" "css_element"
    Then ".qtype_accounting-delete-debit[data-index='0'][disabled]" "css_element" should exist
    And ".qtype_accounting-delete-credit[data-index='0'][disabled]" "css_element" should exist
    When I click on "Add <side> Entry" "button"
    Then ".qtype_accounting-delete-<other>[data-index='0'][disabled]" "css_element" should exist
    And ".qtype_accounting-delete-<type>[data-index='0']:not([disabled])" "css_element" should exist
    When I click on ".qtype_accounting-delete-<type>[data-index='0']" "css_element"
    Then ".qtype_accounting-delete-<type>[data-index='2'][disabled]" "css_element" should exist
    And ".qtype_accounting-delete-<other>[data-index='0'][disabled]" "css_element" should exist
    When I click on "Add <side> Entry" "button"
    Then ".qtype_accounting-delete-<type>[data-index='0']:not([disabled])" "css_element" should exist
    And ".qtype_accounting-delete-<type>[data-index='2']:not([disabled])" "css_element" should exist
    When I click on ".qtype_accounting-delete-<type>[data-index='2']" "css_element"
    Then ".qtype_accounting-delete-<type>[data-index='0'][disabled]" "css_element" should exist
    And ".qtype_accounting-delete-<other>[data-index='0'][disabled]" "css_element" should exist

    Examples:
      | side   | type   | other  |
      | Debit  | debit  | credit |
      | Credit | credit | debit  |

  @javascript
  Scenario: Empty and zero account limits visibly mean all accounts
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then the field "Number of additional accounts in selection list" matches value "All"
    When I set the field "Number of additional accounts in selection list" to "0"
    And I click on "Question name" "field"
    Then the field "Number of additional accounts in selection list" matches value "All"
    When I set the field "Number of additional accounts in selection list" to ""
    And I click on "Question name" "field"
    Then the field "Number of additional accounts in selection list" matches value "All"
    When I set the field "Number of additional accounts in selection list" to "3"
    And I click on "Question name" "field"
    Then the field "Number of additional accounts in selection list" matches value "3"

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
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Cancelled accounting question"
    And I set the field "Question text" to "Record the transaction."
    And I set the field "Accounts of this question" to multiline:
      """
      Bank
      Cash
      """
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
    Then I should see "Accounts of this question"
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
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Missing solution amounts"
    And I set the field "Question text" to "Record the transaction."
    And I set the field "Number format" to "<format>"
    And I set the field "Accounts of this question" to multiline:
      """
      Bank
      Cash
      """
    And I set the field "debitaccount_display[0]" to "Bank"
    And I set the field "creditaccount_display[0]" to "Cash"
    And I set the field "debitaccount_display[1]" to "Bank"
    And I set the field "creditaccount_display[1]" to "Cash"
    And I set the field "weight_debitaccount_display[0]" to "3"
    And I press "id_submitbutton"
    Then I should see "Debit amount is required."
    And I should see "Credit amount is required."
    And "input[name='debitamount_display[0]'].is-invalid" "css_element" should exist
    And "input[name='creditamount_display[0]'].is-invalid" "css_element" should exist
    And "#id_debitamount_display_0_error.form-control-feedback.invalid-feedback" "css_element" should exist
    And "#id_creditamount_display_0_error.form-control-feedback.invalid-feedback" "css_element" should exist
    And the field "Accounts of this question" matches multiline:
      """
      Bank
      Cash
      """
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
  Scenario: Editing the account list updates the entry options and keeps selected accounts
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Edited account list"
    And I set the field "Question text" to "Record the transaction."
    And I set the field "Number format" to "#.###,00"
    And I set the field "Accounts of this question" to multiline:
      """
      Bank
      Cash
      """
    And I set the field "debitaccount_display[0]" to "Bank"
    And I set the field "debitamount_display[0]" to "100"
    And I set the field "creditaccount_display[0]" to "Cash"
    And I set the field "creditamount_display[0]" to "100"
    And I set the field "debitaccount_display[1]" to "Cash"
    And I set the field "debitamount_display[1]" to "50"
    And I set the field "creditaccount_display[1]" to "Bank"
    And I set the field "creditamount_display[1]" to "50"
    And I set the field "Accounts of this question" to multiline:
      """
      Assets
      Bank
      Cash
      """
    And I set the field "Question name" to "Edited account list"
    Then the field "debitaccount_display[0]" matches value "Bank"
    And the field "creditaccount_display[0]" matches value "Cash"
    When I set the field "Accounts of this question" to "Cash"
    And I set the field "Question name" to "Edited account list"
    And I press "id_submitbutton"
    Then I should see "This account is no longer in the account list. Please choose another account."
    And the field "debitamount_display[0]" matches value "100,00"
    When I set the field "debitaccount_display[0]" to "Cash"
    And I set the field "creditaccount_display[1]" to "Cash"
    And I press "id_submitbutton"
    Then I should see "Edited account list"
    And I should not see "Adding an Accounting Entry question"
    When I choose "Edit question" action for "Edited account list" in the question bank
    Then the field "Accounts of this question" matches value "Cash"
    And the field "debitaccount_display[0]" matches value "Cash"

  @javascript
  Scenario: Copy accounts from a template or from another question of the question bank
    Given the accounting chart template "Standard chart" with accounts "1200 Bank, 8400 Sales" exists
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following accounting questions exist:
      | questioncategory | qtype      | name              | debitaccount | creditaccount |
      | Test questions   | accounting | Existing question | 1000 Cash    | 8400          |
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Copied accounts"
    And I set the field "copyaccountsfrom" to "Standard chart"
    And I press "id_copyaccounts"
    Then the field "Accounts of this question" matches multiline:
      """
      1200 Bank
      8400 Sales
      """
    And the field "Question name" matches value "Copied accounts"
    When I set the field "debitaccount_display[0]" to "8400 Sales"
    Then the field "debitaccount_display[0]" matches value "8400 Sales"
    When I set the field "copyaccountsfrom" to "Test questions: Existing question"
    And I press "id_copyaccounts"
    Then the field "Accounts of this question" matches multiline:
      """
      1200
      8400
      9998
      9999
      1000 Cash
      """
