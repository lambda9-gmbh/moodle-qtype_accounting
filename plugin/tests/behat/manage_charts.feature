@qtype @qtype_buchungssatz
Feature: Manage charts of accounts
  As an administrator
  I need to manage charts of accounts
  So that teachers can use them in Buchungssatz questions

  Scenario: Access chart management page as admin
    Given I log in as "admin"
    When I am on the "qtype_buchungssatz > Manage charts" page
    Then I should see "Add new chart"
    And I should see "Manage Charts of Accounts"

  Scenario: Create a new chart of accounts
    Given I log in as "admin"
    And I am on the "qtype_buchungssatz > Manage charts" page
    When I set the field "Chart name" to "Test Chart"
    And I set the field "Description" to "A test chart of accounts"
    And I press "Add new chart"
    Then I should see "Chart of accounts created successfully"
    And I should see "Test Chart"

  Scenario: Create default SKR03 chart
    Given I log in as "admin"
    And I am on the "qtype_buchungssatz > Manage charts" page
    When I press "SKR03 Standardkontenplan erstellen"
    Then I should see "Default SKR03 chart created successfully"
    And I should see "SKR03"

  @javascript
  Scenario: Edit accounts in a chart
    Given I log in as "admin"
    And I am on the "qtype_buchungssatz > Manage charts" page
    And I set the field "Chart name" to "Editable Chart"
    And I press "Add new chart"
    When I click on "Edit Accounts" "link" in the "Editable Chart" "table_row"
    Then I should see "Editable Chart"
    And I should see "Add Account"
