@qtype @qtype_buchungssatz
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
      | Test questions   | buchungssatz | Simple Question | A customer pays 1000 EUR in cash for services. |
    And I am on the "Simple Question" "core_question > preview" page logged in as "teacher1"
    Then I should see "A customer pays 1000 EUR in cash for services."
    And I should see "Debit"
    And I should see "Credit"

  @javascript
  Scenario: Question displays correctly in quiz
    Given the following "questions" exist:
      | questioncategory | qtype        | name            | questiontext                                   |
      | Test questions   | buchungssatz | Quiz Question   | Record this transaction: Cash sale of 500 EUR. |
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
