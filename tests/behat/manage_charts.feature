@qtype @qtype_accounting @javascript
Feature: Manage charts of accounts
  As a teacher
  I need to create charts directly and review the consequences before deleting them
  So that I can manage accounts without uploading files or accidentally breaking questions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the accounting chart "Main" with accounts "Bank, Cash" exists in course "Course 1"
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    And I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I click on "Manage chart of accounts" "link"
    And I switch to "qtype_accounting-chart-frame" iframe

  Scenario: Create a chart directly and add accounts without uploading a file
    When I set the field "Chart name" to "Manual chart"
    And I press "Create chart of accounts"
    Then I should see "Chart of accounts created successfully."
    And I should see "Manual chart"
    And I should see "No accounts in this chart yet."
    When I set the field "accountname" to "1200 Bank"
    And I press "Add Account"
    Then I should see "Account added successfully."
    And I should see "1200 Bank"
    When I set the field "accountname" to "1000 Cash"
    And I press "Add Account"
    And I click on "Manage Charts of Accounts" "link"
    Then I should see "2" in the "Manual chart" "table_row"
    When I click on "Manual chart" "link"
    Then I should see "1200 Bank"
    And I should see "1000 Cash"

  Scenario: A chart name is required for direct creation
    When I set the field "Chart name" to "   "
    And I press "Create chart of accounts"
    Then I should see "Required"
    And "Main" "link" should exist
    And I should not see "Chart of accounts created successfully."

  Scenario: Cancelling deletion preserves the chart and its accounts
    When I click on "Delete" "link" in the "Main" "table_row"
    Then I should see "Are you sure you want to permanently delete the chart of accounts" in the "Delete chart" "dialogue"
    And I should see "Main"
    And I should see "All accounts in this chart will also be deleted."
    And I should see "Questions that use this chart will no longer work correctly."
    And I should see "This action cannot be undone."
    When I click on "Cancel" "button" in the "Delete chart" "dialogue"
    Then "Main" "link" should exist
    When I click on "Main" "link"
    Then I should see "Bank"
    And I should see "Cash"

  Scenario: Deleting a chart requires explicit confirmation
    When I click on "Delete" "link" in the "Main" "table_row"
    Then "Delete chart" "dialogue" should exist
    When I click on "Delete chart" "button" in the "Delete chart" "dialogue"
    Then I should see "Chart of accounts deleted successfully."
    And "Main" "link" should not exist
    And I should see "No charts of accounts have been created yet."
