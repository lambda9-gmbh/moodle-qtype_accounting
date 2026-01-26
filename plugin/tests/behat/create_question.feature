@qtype @qtype_buchungssatz
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
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Adding an Accounting Entry question"
    And I should see "Question name"
    And I should see "Correct Answer"

  @javascript
  Scenario: Question form shows entry input fields
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Chart of Accounts"
    And I should see "Correct Answer"
    And I should see "Per"
    And I should see "Debit"
    And I should see "Credit"

  @javascript
  Scenario: Validation error when question name is empty
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question text" to "Test question"
    And I press "id_submitbutton"
    Then I should see "You must supply a value here"

  @javascript
  Scenario: Weight fields are visible in entry table
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Weight"

  @javascript
  Scenario: Number format option is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Number format"
    And I should see "German/EU"
    And I should see "US"

  @javascript
  Scenario: All-or-nothing option is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Only award marks if all entries are correct"

  @javascript
  Scenario: Decimal places option is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Decimal places"

  @javascript
  Scenario: Multiple tries section is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Multiple tries"

  @javascript
  Scenario: Manage charts link is visible
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Manage Charts of Accounts"

  @javascript
  Scenario: Add entry button works
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Add Entry"
    When I click on "Add Entry" "button"
    Then ".buchungssatz-entry-row" "css_element" should exist
