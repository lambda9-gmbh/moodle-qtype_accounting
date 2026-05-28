@qtype @qtype_accounting
Feature: Attempt Buchungssatz questions
  As a student
  I need to answer accounting entry questions
  So that I can demonstrate my bookkeeping knowledge

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |

  @javascript
  Scenario: Student can see Buchungssatz question in preview
    Given the following "questions" exist:
      | questioncategory | qtype        | name            | questiontext                                   |
      | Test questions   | accounting | Simple Question | A customer pays 1000 EUR in cash for services. |
    And I am on the "Simple Question" "core_question > preview" page logged in as "teacher1"
    Then I should see "A customer pays 1000 EUR in cash for services."
    And I should see "Debit"
    And I should see "Credit"

  @javascript
  Scenario: Question displays correctly in quiz
    Given the following "questions" exist:
      | questioncategory | qtype        | name            | questiontext                                   |
      | Test questions   | accounting | Quiz Question   | Record this transaction: Cash sale of 500 EUR. |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question      | page |
      | Quiz Question | 1    |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then I should see "Record this transaction: Cash sale of 500 EUR."
    And I should see "Debit"
    And I should see "Credit"

  @javascript
  Scenario: Student can add additional entry rows
    Given the following "questions" exist:
      | questioncategory | qtype        | name            | questiontext                  |
      | Test questions   | accounting | Multi Question  | Record multiple transactions. |
    And I am on the "Multi Question" "core_question > preview" page logged in as "teacher1"
    Then I should see "Add Debit Entry"
    When I click on "Add Debit Entry" "button"
    Then ".accounting-entry-row" "css_element" should exist

  @javascript
  Scenario: Question shows Per and an labels
    Given the following "questions" exist:
      | questioncategory | qtype        | name            | questiontext        |
      | Test questions   | accounting | Label Question  | Test Per/an labels. |
    And I am on the "Label Question" "core_question > preview" page logged in as "teacher1"
    Then I should see "Per"
    And I should see "to"

  @javascript
  Scenario: Preview fill in correct response works
    Given the following "questions" exist:
      | questioncategory | qtype        | name            | questiontext                | debitaccount | debitamount | creditaccount | creditamount |
      | Test questions   | accounting | Fill Question   | Test fill correct response. | 1200      | 1000       | 8400       | 1000        |
    And I am on the "Fill Question" "core_question > preview" page logged in as "teacher1"
    When I press "Fill in correct responses"
    # Use CSS selectors for the input fields since exact field names vary
    Then "input[name*='debitaccount_0'][value='1200']" "css_element" should exist
    And "input[name*='creditaccount_0'][value='8400']" "css_element" should exist

  @javascript
  Scenario: Student can complete a quiz attempt
    Given the following "questions" exist:
      | questioncategory | qtype        | name            | questiontext   | debitaccount | debitamount | creditaccount | creditamount |
      | Test questions   | accounting | Complete Quiz   | Complete test. | 1200      | 100        | 8400       | 100         |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question      | page |
      | Complete Quiz | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    # Fill in the account fields using CSS selectors
    And I set the field with xpath "//input[contains(@name,'debitaccount_0')]" to "1200"
    And I set the field with xpath "//input[contains(@name,'debitamount_0')]" to "100"
    And I set the field with xpath "//input[contains(@name,'creditaccount_0')]" to "8400"
    And I set the field with xpath "//input[contains(@name,'creditamount_0')]" to "100"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "Finished"
