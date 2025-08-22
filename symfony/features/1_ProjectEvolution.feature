Feature: Project Evolution
    In order to change projects over time
    As an Admin
    I need to be able to create/evolve/end projects

    Scenario: Admin Creating a new Project
        Given I am logged in as "Admin.User1" using password "Admin.Pass1"
        When I go to "index.php/Special:ProjectEvolution"
        And I click "Create"
        And I fill in "new_acronym" with "NewProj"
        And I fill in "new_full_name" with "New Project"
        And I fill in TinyMCE "new_description" with "New Project Description"
        And I press "Create"
        And I go to "index.php/NewProj:Main"
        Then I should see "New Project"
        And I should see "New Project Description"

    Scenario: Admin Ending a Project
        Given I am logged in as "Admin.User1" using password "Admin.Pass1"
        When I go to "index.php/Special:ProjectEvolution"
        And I click "Inactivate"
        And I fill in "combo_delete_project" with "Phase1Project5"
        And I press "Inactivate"
        And I go to "index.php/NETWORK:Projects"
        Then I should not see "Phase1Project5"
        When I go to "index.php/NETWORK:CompletedProjects"
        Then I should see "Phase1Project5"
        When I follow "Phase1Project5"
        Then I should see "Ended"
        
    Scenario: NI viewing their Dashboard
        Given I am logged in as "NI.User1" using password "NI.Pass1"
        When I follow "My Profile"
        And I click "Dashboard"
        Then I should see "Phase2Project1"
        And I should see "Phase2Project2"
        But I should not see "Phase1Project5"
        When I click "Show Completed Projects"
        Then I should see "Phase1Project5"
