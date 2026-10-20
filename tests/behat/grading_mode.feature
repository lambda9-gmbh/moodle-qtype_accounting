@qtype @qtype_accounting @javascript
Feature: Choose the grading mode of an accounting question
  As a teacher
  I need to grade accounting questions in percent or in points
  So that marks follow my school's assessment rules

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
    And the accounting chart "Main" with accounts "Bank, Cash, Sales" exists in course "Course 1"

  Scenario: The site default is preselected and points mode derives the default mark
    Given the following config values are set as admin:
      | defaultgradingmode | points | qtype_accounting |
    And I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then the field "Grading mode" matches value "Points (one point per account-amount pair)"
    And I should see "A pair only earns its point if both account and amount are correct"
    And I should not see "Default mark"
    When I set the field "Grading mode" to "Percentage (weighted)"
    Then I should see "Default mark"
    And I should not see "A pair only earns its point if both account and amount are correct"
    When I set the following fields to these values:
      | Grading mode             | Points (one point per account-amount pair) |
      | Question name            | Points question                            |
      | Question text            | Record the transaction.                    |
      | Chart of Accounts        | Main                                       |
      | debitaccount_display[0]  | Bank                                       |
      | debitamount_display[0]   | 100                                        |
      | creditaccount_display[0] | Sales                                      |
      | creditamount_display[0]  | 100                                        |
      | debitaccount_display[1]  | Cash                                       |
      | debitamount_display[1]   | 50                                         |
      | creditaccount_display[1] | Sales                                      |
      | creditamount_display[1]  | 50                                         |
    And I press "id_submitbutton"
    Then I should see "Points question"
    When I choose "Edit question" action for "Points question" in the question bank
    Then the field "Grading mode" matches value "Points (one point per account-amount pair)"
    When I set the field "Grading mode" to "Percentage (weighted)"
    Then the field "Default mark" matches value "3"

  Scenario: Without a site default the percentage mode is preselected
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then the field "Grading mode" matches value "Percentage (weighted)"
    And I should see "Default mark"
