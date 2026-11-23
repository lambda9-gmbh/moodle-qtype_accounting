@qtype @qtype_accounting @javascript
Feature: Copy accounts from chart of accounts templates into questions
  As an administrator
  I need to provide chart of accounts templates for all courses
  So that teachers do not have to type the accounts in every question

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

  Scenario: A question keeps its copied accounts when the template changes
    Given I log in as "admin"
    And I navigate to "Plugins > Question types > Accounting Entry" in site administration
    And I should see "No charts of accounts have been created yet."
    When I click on "Create or upload a chart of accounts template" "link"
    And I set the field "Chart name" to "Standard chart"
    And I press "Create chart of accounts"
    And I press "Add Account"
    And I set the field "Account name" to "1200 Bank"
    And I click on "Add Account" "button" in the "Add Account" "dialogue"
    And I should see "1200 Bank" in the "region-main" "region"
    And I press "Add Account"
    And I set the field "Account name" to "8400 Sales"
    And I click on "Add Account" "button" in the "Add Account" "dialogue"
    And I should see "8400 Sales" in the "region-main" "region"
    And I log out
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    And I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    And I set the field "Question name" to "Template question"
    And I set the field "Question text" to "Record the sale."
    And I set the field "copyaccountsfrom" to "Standard chart"
    And I press "id_copyaccounts"
    And I set the following fields to these values:
      | debitaccount_display[0]  | 1200 Bank  |
      | debitamount_display[0]   | 100        |
      | creditaccount_display[0] | 8400 Sales |
      | creditamount_display[0]  | 100        |
      | debitaccount_display[1]  | 8400 Sales |
      | debitamount_display[1]   | 50         |
      | creditaccount_display[1] | 1200 Bank  |
      | creditamount_display[1]  | 50         |
    And I press "id_submitbutton"
    Then I should see "Template question"
    And I log out
    When I log in as "admin"
    And I navigate to "Plugins > Question types > Accounting Entry" in site administration
    And I click on "Delete" "link" in the "Standard chart" "table_row"
    And I click on "Delete chart" "button" in the "Delete chart" "dialogue"
    And I log out
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    And I choose "Edit question" action for "Template question" in the question bank
    Then the field "Accounts of this question" matches multiline:
      """
      1200 Bank
      8400 Sales
      """
    And the field "debitaccount_display[0]" matches value "1200 Bank"
    And the field "creditaccount_display[0]" matches value "8400 Sales"
