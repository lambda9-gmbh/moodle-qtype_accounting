@qtype @qtype_buchungssatz
Feature: Grading Buchungssatz questions
  As a student
  I need to answer accounting entry questions and receive accurate grades
  So that I can learn from my mistakes

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
  Scenario: Correct answer receives full marks
    Given the following "questions" exist:
      | questioncategory | qtype        | name           | questiontext               | sollkonto | sollbetrag | habenkonto | habenbetrag |
      | Test questions   | buchungssatz | Simple booking | Record a cash sale of 500. | 1200      | 500        | 8400       | 500         |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question       | page |
      | Simple booking | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    And I set the field with xpath "//input[contains(@name,'sollkonto_0')]" to "1200"
    And I set the field with xpath "//input[contains(@name,'sollbetrag_0')]" to "500"
    And I set the field with xpath "//input[contains(@name,'habenkonto_0')]" to "8400"
    And I set the field with xpath "//input[contains(@name,'habenbetrag_0')]" to "500"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "1.00"
    And I should see "All entries are correct!"

  @javascript
  Scenario: Completely wrong answer receives zero marks
    Given the following "questions" exist:
      | questioncategory | qtype        | name           | questiontext               | sollkonto | sollbetrag | habenkonto | habenbetrag |
      | Test questions   | buchungssatz | Simple booking | Record a cash sale of 500. | 1200      | 500        | 8400       | 500         |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question       | page |
      | Simple booking | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    And I set the field with xpath "//input[contains(@name,'sollkonto_0')]" to "9999"
    And I set the field with xpath "//input[contains(@name,'sollbetrag_0')]" to "999"
    And I set the field with xpath "//input[contains(@name,'habenkonto_0')]" to "9998"
    And I set the field with xpath "//input[contains(@name,'habenbetrag_0')]" to "999"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "0.00"
    And I should see "incorrect"

  @javascript
  Scenario: Partially correct answer shows partial credit feedback
    Given the following "questions" exist:
      | questioncategory | qtype        | name           | questiontext               | sollkonto | sollbetrag | habenkonto | habenbetrag |
      | Test questions   | buchungssatz | Simple booking | Record a cash sale of 500. | 1200      | 500        | 8400       | 500         |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question       | page |
      | Simple booking | 1    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    # Correct debit, wrong credit
    And I set the field with xpath "//input[contains(@name,'sollkonto_0')]" to "1200"
    And I set the field with xpath "//input[contains(@name,'sollbetrag_0')]" to "500"
    And I set the field with xpath "//input[contains(@name,'habenkonto_0')]" to "9999"
    And I set the field with xpath "//input[contains(@name,'habenbetrag_0')]" to "500"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    Then I should see "0.50"
    And I should see "incorrect"

  @javascript
  Scenario: Teacher can see Multiple Tries section in question form
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Multiple tries"
    # Expand the Multiple tries section to see its contents
    When I click on "Multiple tries" "link"
    Then I should see "Penalty for each incorrect try"
    And I should see "Hint"

  @javascript
  Scenario: All-or-nothing grading option is available
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "Accounting Entry (Buchungssatz)" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Only award marks if all entries are correct"

  @javascript
  Scenario: Question preview shows correct answer structure
    Given the following "questions" exist:
      | questioncategory | qtype        | name           | questiontext               | sollkonto | sollbetrag | habenkonto | habenbetrag |
      | Test questions   | buchungssatz | Preview test   | Record a cash sale of 500. | 1200      | 500        | 8400       | 500         |
    And I am on the "Preview test" "core_question > preview" page logged in as "teacher1"
    Then I should see "Debit"
    And I should see "Credit"
    And I should see "Account"
    And I should see "Amount"

  @javascript
  Scenario: Correct answer display shows proper format
    Given the following "questions" exist:
      | questioncategory | qtype        | name           | questiontext               | sollkonto | sollbetrag | habenkonto | habenbetrag |
      | Test questions   | buchungssatz | Format test    | Record a cash sale of 500. | 1200      | 500        | 8400       | 500         |
    And I am on the "Format test" "core_question > preview" page logged in as "teacher1"
    When I press "Fill in correct responses"
    And I press "Submit and finish"
    Then I should see "The correct answer is:"
    And I should see "1200"
    And I should see "8400"
