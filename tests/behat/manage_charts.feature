@qtype @qtype_accounting @javascript
Feature: Manage chart of accounts templates
  As an administrator
  I need to create, upload, edit and delete chart of accounts templates
  So that teachers can copy standard accounts into their questions

  Background:
    Given the accounting chart template "Main" with accounts "Bank, Cash" exists
    And I log in as "admin"
    And I navigate to "Plugins > Question types > Accounting Entry" in site administration

  Scenario: Create a template directly and add accounts without uploading a file
    Then I should see "Teachers can copy the accounts of these templates into their questions."
    When I click on "Create or upload a chart of accounts template" "link"
    And I set the field "Chart name" to "Manual chart"
    And I press "Create chart of accounts"
    Then I should see "Chart of accounts created successfully."
    And I should see "Manual chart"
    And I should see "Questions keep their own copy of the accounts."
    And I should see "No accounts in this chart yet."
    When I set the field "accountname" to "1200 Bank"
    And I press "Add Account"
    Then I should see "Account added successfully."
    And I should see "1200 Bank"
    When I set the field "accountname" to "1000 Cash"
    And I press "Add Account"
    And I click on "Chart of accounts templates" "link" in the "region-main" "region"
    Then I should see "2" in the "Manual chart" "table_row"
    When I click on "Manual chart" "link"
    Then I should see "1200 Bank"
    And I should see "1000 Cash"

  Scenario: A chart name is required for direct creation
    When I click on "Create or upload a chart of accounts template" "link"
    And I set the field "Chart name" to "   "
    And I press "Create chart of accounts"
    Then I should see "Required"
    And I should not see "Chart of accounts created successfully."

  @_file_upload
  Scenario: Upload an ODS template and import its accounts into an existing template
    When I click on "Create or upload a chart of accounts template" "link"
    And I upload "question/type/accounting/tests/fixtures/Accounts.ods" file to "CSV, text or ODS file" filemanager
    And I press "Upload"
    Then I should see "3 accounts imported successfully."
    When I click on "Accounts" "link"
    Then I should see "0010 Bank"
    And I should see "8400 Erlöse & USt"
    And I should see "1000 Kasse"
    And I should not see "Ignored column"
    When I click on "Chart of accounts templates" "link" in the "region-main" "region"
    And I click on "Main" "link"
    And I upload "question/type/accounting/tests/fixtures/Accounts.ods" file to "CSV, text or ODS file" filemanager
    And I press "Import Chart"
    Then I should see "3 accounts imported successfully."
    And I should see "0010 Bank"
    And I should see "1000 Kasse"

  Scenario: Accounts of a template can be renamed and deleted
    When I click on "Main" "link"
    And I click on "Edit" "link" in the "Bank" "table_row"
    And I set the field with xpath "//input[@name='accountname' and @form]" to "Renamed account"
    And I click on "input[type='submit'][form^='form_account_']" "css_element"
    Then I should see "Account updated successfully."
    And I should see "Renamed account"
    When I click on "Delete" "link" in the "Renamed account" "table_row"
    Then I should see "Are you sure you want to delete this account?"
    When I press "Cancel"
    Then I should see "Renamed account"
    When I click on "Delete" "link" in the "Renamed account" "table_row"
    And I press "Delete account"
    Then I should see "Account deleted successfully."
    And I should not see "Renamed account"

  Scenario: Cancelling deletion preserves the template and its accounts
    When I click on "Delete" "link" in the "Main" "table_row"
    Then I should see "Are you sure you want to permanently delete the chart of accounts template" in the "Delete chart" "dialogue"
    And I should see "Questions that copied its accounts keep their own copy."
    When I click on "Cancel" "button" in the "Delete chart" "dialogue"
    Then "Main" "link" should exist
    When I click on "Main" "link"
    Then I should see "Bank"
    And I should see "Cash"

  Scenario: Deleting a template requires explicit confirmation
    When I click on "Delete" "link" in the "Main" "table_row"
    Then "Delete chart" "dialogue" should exist
    When I click on "Delete chart" "button" in the "Delete chart" "dialogue"
    Then I should see "Chart of accounts deleted successfully."
    And "Main" "link" should not exist
    And I should see "No charts of accounts have been created yet."
