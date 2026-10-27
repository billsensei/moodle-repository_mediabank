@core @core_contentbank @contenttype_mediabank @repository_contentbank @javascript @_file_upload
Feature: Upload media to the content bank and use it in a course as a link or a copy
  In order to reuse media across courses
  As a teacher
  I need to upload media to the content bank and pick it in a course either as a link or as a copy

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | CAT1     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "user private file" exists:
      | user     | teacher1                                                  |
      | filepath | contentbank/contenttype/mediabank/tests/fixtures/sample.png |
    And the following "user private file" exists:
      | user     | teacher1                                                  |
      | filepath | contentbank/contenttype/mediabank/tests/fixtures/sample.pdf |
    And the following "user private file" exists:
      | user     | teacher1                                                      |
      | filepath | contentbank/contenttype/mediabank/tests/fixtures/notreally.png |

  Scenario Outline: A teacher uploads a media file to the course content bank
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    And I add the "Navigation" block if not present
    And I expand "Site pages" node
    And I click on "Content bank" "link"
    And I should not see "<file>"
    When I click on "Upload" "link"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "<file>" "link"
    And I click on "Select this file" "button"
    And I click on "Save changes" "button"
    And I wait until the page is ready
    Then I should see "<file>"

    Examples:
      | file       |
      | sample.png |
      | sample.pdf |

  Scenario: A file whose content does not match its name is refused with a clear message
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    And I add the "Navigation" block if not present
    And I expand "Site pages" node
    And I click on "Content bank" "link"
    When I click on "Upload" "link"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "notreally.png" "link"
    And I click on "Select this file" "button"
    And I click on "Save changes" "button"
    And I wait until the page is ready
    Then I should see "was not accepted because its content is not the kind of file its name says"
    And "notreally.png" "link" should not exist

  Scenario: A teacher picks a content bank item as a link, which is the default
    Given the following "contentbank content" exist:
      | contextlevel | reference | contenttype            | user     | contentname | filepath                                                    |
      | Course       | C1        | contenttype_mediabank  | teacher1 | bankimage   | /contentbank/contenttype/mediabank/tests/fixtures/sample.png |
    And I log in as "teacher1"
    When I add a resource activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Name | linkedimage |
    And I click on "Add..." "link" in the ".fp-toolbar" "css_element"
    And I select "Content bank" repository in file picker
    And I click on "bankimage" "file" in repository content area
    Then the field "Link to the file" matches value "1"
    And I click on "Select this file" "button"
    And I click on "Save and return to course" "button"
    And I should see "linkedimage"
    # The item page now says where it is used and explains why it cannot be deleted.
    And I am on "Course 1" course homepage with editing mode on
    And the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    And I add the "Navigation" block if not present
    And I expand "Site pages" node
    And I click on "Content bank" "link"
    And I click on "bankimage" "link"
    And I should see "Where this item is used"
    And I should see "Course 1: 1 link(s)"
    And I should see "This item cannot be deleted while courses link to it."
    And "Delete" "link" should not exist

  Scenario: A teacher picks a content bank item as an independent copy
    Given the following "contentbank content" exist:
      | contextlevel | reference | contenttype            | user     | contentname | filepath                                                    |
      | Course       | C1        | contenttype_mediabank  | teacher1 | bankimage   | /contentbank/contenttype/mediabank/tests/fixtures/sample.png |
    And I log in as "teacher1"
    When I add a resource activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Name | copiedimage |
    And I click on "Add..." "link" in the ".fp-toolbar" "css_element"
    And I select "Content bank" repository in file picker
    And I click on "bankimage" "file" in repository content area
    And I click on "Make a copy of the file" "radio"
    And I click on "Select this file" "button"
    And I click on "Save and return to course" "button"
    Then I should see "copiedimage"
