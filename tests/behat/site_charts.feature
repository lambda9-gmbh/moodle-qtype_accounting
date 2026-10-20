@qtype @qtype_accounting @javascript
Feature: Provide site charts of accounts
  As an administrator
  I need to provide standard charts of accounts for all courses
  So that teachers do not have to upload them in every course

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

  Scenario: Teachers can select and copy a site chart created by the administrator
    Given I log in as "admin"
    And I navigate to "Plugins > Question types > Site charts of accounts" in site administration
    And I should see "available to teachers in every course"
    When I set the field "Chart name" to "Standard chart"
    And I press "Create chart of accounts"
    Then I should see "Chart of accounts created successfully."
    When I set the field "accountname" to "1200 Bank"
    And I press "Add Account"
    Then I should see "1200 Bank"
    And I log out
    When I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    And I press "Create a new question ..."
    And I set the field "Accounting Entry" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then the "Chart of Accounts" select box should contain "Standard chart (site default)"
    When I click on "Manage chart of accounts" "link"
    And I switch to a second window
    Then I should see "Site charts of accounts"
    And I should see "No charts of accounts"
    When I click on "Copy to this course" "link" in the "Standard chart" "table_row"
    Then I should see "Chart of accounts copied to this course."
    And I should see "1200 Bank"
