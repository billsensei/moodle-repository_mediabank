@core @core_contentbank @contenttype_mediabank @javascript
Feature: Add accessibility information to a media bank item
  In order to make media usable for everyone
  As a teacher
  I need to give images a text alternative and audio a transcript

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
    And the following "contentbank content" exist:
      | contextlevel | reference | contenttype            | user     | contentname | filepath                                                     |
      | Course       | C1        | contenttype_mediabank  | teacher1 | bankimage   | /contentbank/contenttype/mediabank/tests/fixtures/sample.png |
      | Course       | C1        | contenttype_mediabank  | teacher1 | bankaudio   | /contentbank/contenttype/mediabank/tests/fixtures/sample.mp3 |

  Scenario: An image cannot be saved without a text alternative
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    And I add the "Navigation" block if not present
    And I expand "Site pages" node
    And I click on "Content bank" "link"
    And I click on "bankimage" "link"
    And I click on "Edit" "link"
    When I press "Save"
    Then I should see "Required"
    And I set the field "Text alternative" to "A single red dot"
    And I press "Save"
    And I should see "Content updated"
    And "//img[@alt='A single red dot']" "xpath_element" should exist
    # Nothing links to this item, so it can still be deleted.
    And "Delete" "link" should exist

  Scenario: A transcript is shown under the audio preview
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    And I add the "Navigation" block if not present
    And I expand "Site pages" node
    And I click on "Content bank" "link"
    And I click on "bankaudio" "link"
    And I click on "Edit" "link"
    When I set the field "Transcript" to "Welcome to the course"
    And I press "Save"
    Then I should see "Content updated"
    # The transcript starts collapsed so it does not push the player down the page.
    And I should not see "Welcome to the course"
    And I click on "Transcript" "text" in the ".contenttype-mediabank-preview" "css_element"
    And I should see "Welcome to the course"
