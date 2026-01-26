@qtype @qtype_buchungssatz
Feature: Manage charts of accounts
  As an administrator
  I need to manage charts of accounts
  So that teachers can use them in Buchungssatz questions

  Scenario: Access chart management page as admin
    Given I log in as "admin"
    When I am on the "qtype_buchungssatz > Manage charts" page
    Then I should see "Manage Charts of Accounts"
    And I should see "Import Chart of Accounts from CSV"
    And I should see "Create Default SKR03 Chart"

  Scenario: Create default SKR03 chart
    Given I log in as "admin"
    And I am on the "qtype_buchungssatz > Manage charts" page
    When I press "Create Default SKR03 Chart"
    Then I should see "Default SKR03 chart created successfully"
    And I should see "SKR03"

  Scenario: Admin can see chart list after creating SKR03
    Given I log in as "admin"
    And I am on the "qtype_buchungssatz > Manage charts" page
    And I press "Create Default SKR03 Chart"
    Then I should see "SKR03"
    And I should see "Edit Accounts"
    And I should see "Export"
    And I should see "Delete"

  @javascript
  Scenario: Edit accounts in default SKR03 chart
    Given I log in as "admin"
    And I am on the "qtype_buchungssatz > Manage charts" page
    And I press "Create Default SKR03 Chart"
    When I click on "Edit Accounts" "link" in the "SKR03" "table_row"
    Then I should see "SKR03"
    And I should see "Add Account"
