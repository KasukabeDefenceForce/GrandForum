<?php

class CreateProjectTab extends ProjectTab {

    function CreateProjectTab(){
        parent::ProjectTab("Create");
    }
    
    static function createForm($pre){
        global $config;
    
        $form = new FormContainer("{$pre}_form_container");
    
        $table = new FormTable("{$pre}_form_table");
        
        $acronymRow = new FormTableRow("{$pre}_acronym_row");
        $acronymLabel = new Label("{$pre}_acronym_label", "Acronym", "The acronym/name for the project ie. MEOW", VALIDATE_NOT_NULL);
        $acronymField = new TextField("{$pre}_acronym", "Acronym", "", VALIDATE_NOT_NULL + VALIDATE_NOT_PROJECT);
        $acronymRow->append($acronymLabel)->append($acronymField->attr('size', 12));
        
        $fullNameRow = new FormTableRow("{$pre}_full_name_row");
        $fullNameLabel = new Label("{$pre}_full_name_label", "Full Name", "The project's full name ie. Media Enabled Organizational Worldflow", VALIDATE_NOT_NULL);
        $fullNameField = new TextField("{$pre}_full_name", "Full Name", "", VALIDATE_NOT_NULL);
        $fullNameRow->append($fullNameLabel)->append($fullNameField->attr('size', 40));
        
        $subprojectRow = new FormTableRow("{$pre}_subproject_row");

        //Sub-project radio button + parent project drop-down
        $projectOptions = "<option value='0'>Choose Parent</option>\n";
        foreach(Project::getAllProjects() as $project){
            $project_id = $project->getId();
            $project_name = $project->getName();
            $projectOptions .= "<option phase='{$project->getPhase()}' value='{$project_id}'>{$project_name}</option>\n";
        }
        $subp =<<<EOF
        <input type='radio' onclick='subReaction();' id='{$pre}_subproject_n'  name='{$pre}_subproject' value='No' checked='checked' />No
        &nbsp;&nbsp;&nbsp;&nbsp;
        <input type='radio' onclick='subReaction();' id='{$pre}_subproject_y' name='{$pre}_subproject' value='Yes' />Yes
EOF;
        $subprojectRow->append(new Label("{$pre}_subproject_label", "Sub-Project", "Is this a Sub-Project?", VALIDATE_NOT_NULL));
        $subprojectRow->append(new CustomElement("{$pre}_subproject", "", "", $subp, VALIDATE_NOT_NULL));

        $subprojectDDRow = new FormTableRow("{$pre}_subprojectdd_row");
        $subp_dd =<<<EOF
        <select id='{$pre}_subproject_parent_dd' name='new_parent_id' style='display:none;'>
        {$projectOptions}
        </select>
        <script type='text/javascript'>
            var options = Array();
        
            $(document).ready(function(){
                oldOptions = $("#new_subproject_parent_dd option");
                updateParents();
                $("[name=new_phase]").change(updateParents);
                $(".custom-combobox", $("#new_subproject_parent_dd").parent()).hide();
            });
            
            function updateParents(){
                $("#new_subproject_parent_dd").empty();
                var phase = $("[name=new_phase]").val();
                $("#new_subproject_parent_dd").append(oldOptions);
                $('#new_subproject_parent_dd').val(0);
                $("#new_subproject_parent_dd option").not("[value=0]").not("[phase=" + phase + "]").remove();
                $("#new_subproject_parent_dd").combobox();
                $(".custom-combobox input", $("#new_subproject_parent_dd").parent()).val("Choose Parent");
            }
        
            function subReaction(){
                updateParents();
                if($('#new_subproject_y').is(':checked')) { 
                     $(".custom-combobox", $("#new_subproject_parent_dd").parent()).show();
                }
                else{
                    $('#new_subproject_parent_dd').val(0);
                    $(".custom-combobox", $("#new_subproject_parent_dd").parent()).hide();
                }
            }
        </script>
EOF;
        $subprojectDDRow->append(new CustomElement("{$pre}_subproject_label", "", "", "", VALIDATE_NOTHING));
        $subprojectDDRow->append(new CustomElement("{$pre}_parent_id", "", "", $subp_dd, VALIDATE_NOT_NULL));
        
        $statusRow = new FormTableRow("{$pre}_status_row");
        $statusRow->append(new Label("{$pre}_status_label", "Status", "The status of this project", VALIDATE_NOT_NULL));
        $statusRow->append(new VerticalRadioBox("{$pre}_status", "Status", "Active", array("Proposed", "Deferred", "Active"), VALIDATE_NOT_NULL));
        
        $typeRow = new FormTableRow("{$pre}_type_row");
        $typeRow->append(new Label("{$pre}_type_label", "Type", "The type of this project", VALIDATE_NOT_NULL));
        $typeRow->append(new VerticalRadioBox("{$pre}_type", "Type", "Research", array("Research", "Administrative", "Strategic"), VALIDATE_NOT_NULL));
        
        $bigbetRow = new FormTableRow("{$pre}_bigbet_row");
        $bigbetRow->append(new Label("{$pre}_bigbet_label", "Big-Bet", "Whether or not this project is considered to be a 'Big-Bet' project", VALIDATE_NOT_NULL));
        $bigbetRow->append(new VerticalRadioBox("{$pre}_bigbet", "Big-Bet", "No", array("No", "Yes"), VALIDATE_NOT_NULL));
        
        $phaseRow = new FormTableRow("{$pre}_phase_row");
        $phaseRow->append(new Label("{$pre}_phase_label", "Phase", "What project phase the new project belongs to", VALIDATE_NOT_NULL));
        $phaseRow->append(new SelectBox("{$pre}_phase", "Phase", "Research", range(PROJECT_PHASE, 1, -1), VALIDATE_NOT_NULL));
        
        $effectiveRow = new FormTableRow("{$pre}_effective_row");
        $effectiveRow->append(new Label("{$pre}_effective_label", "Effective Date", "When this action is to take place", VALIDATE_NOT_NULL));
        $effectiveRow->append(new CalendarField("{$pre}_effective", "Effective Date", date("Y")."-04-01", VALIDATE_NOT_NULL));
        
        $names = array("");
        $people = array_merge(Person::getAllPeople(PNI), Person::getAllPeople(CNI));
        foreach($people as $person){
            $names[$person->getName()] = $person->getNameForForms();
        }
        asort($names);
        
        $plRow = new FormTableRow("{$pre}_pl_row");
        $plRow->append(new Label("{$pre}_pl_label", "Project Leader", "The leader of this Project.  The person should be a valid person on this project.", VALIDATE_NOTHING));
        $plRow->append(new ComboBox("{$pre}_pl", "Project Leader", "", $names, VALIDATE_NI));
        
        $coplRow = new FormTableRow("{$pre}_copl_row");
        $coplRow->append(new Label("{$pre}_copl_label", "Co-Project Leader", "The co-leader of this Project.  The person should be a valid person on this project.", VALIDATE_NOTHING));
        $coplRow->append(new ComboBox("{$pre}_copl", "Co-Project Leader", "", $names, VALIDATE_NI));
        
        $names = array("");
        $people = Person::getAllPeople(CHAMP);
        foreach($people as $person){
            $names[$person->getName()] = $person->getNameForForms();
        }
        asort($names);
        
        // Champion
        $champRow = new FormTableRow("{$pre}_champ_row");
        $champRow->append(new Label("{$pre}_champ_label", "Project Champion(s)", "The champions of this project.  Each champion must be an already existing member in the Champion role.  If the user is not created yet, then request a new member and you will be notified on the forum when the user gets created.", VALIDATE_NOTHING));
        
        $champPlusMinus = new PlusMinus("{$pre}_champ_plusminus");
        $champTable = new FormTable("{$pre}_champ_table");
        
        $champTableNameRow = new ComboBox("{$pre}_champ_name[]", "Name", "", $names, VALIDATE_CHAMPION);
        
        $champTable->append($champTableNameRow);
        $champPlusMinus->append($champTable);
        $champRow->append($champPlusMinus);
        
        $descRow = new FormTableRow("{$pre}_description_row");
        $descRow->append(new Label("{$pre}_description_label", "Description", "The description of the project", VALIDATE_NOTHING));
        $descRow->append(new TextareaField("{$pre}_description", "Description", "", VALIDATE_NOTHING));
        
        $probRow = new FormTableRow("{$pre}_problem_row");
        $probRow->append(new Label("{$pre}_problem_label", "Problem Summary", "The problem summary of the project", VALIDATE_NOTHING));
        $probRow->append(new TextareaField("{$pre}_problem", "Problem Summary", "", VALIDATE_NOTHING));
        
        $solRow = new FormTableRow("{$pre}_solution_row");
        $solRow->append(new Label("{$pre}_solution_label", "Proposed Solution Summary", "The proposed solution summary of the project", VALIDATE_NOTHING));
        $solRow->append(new TextareaField("{$pre}_solution", "Proposed Solution Summary", "", VALIDATE_NOTHING));
        
        //Challenges
        $challengeFieldSet = new FieldSet("{$pre}_challenges_set", "Primary Challenge");
       
        $challengeNames = array();
        $challenges = Theme::getAllThemes(PROJECT_PHASE);
        foreach($challenges as $challenge){
            $challengeNames[$challenge->getId()] = $challenge->getAcronym();
        }

        $challengeRadioBox = new VerticalRadioBox2("{$pre}_challenge", "", "", $challengeNames, VALIDATE_NOTHING);
        $challengeFieldSet->append($challengeRadioBox);

        if(!$config->getValue("projectTypes")){
            $typeRow->hide();
        }
        if(!$config->getValue("projectStatus")){
            $statusRow->hide();
        }
        if(!$config->getValue("bigBetProjects")){
            $bigbetRow->hide();
        }

        $table->append($acronymRow);
        $table->append($fullNameRow);
        $table->append($subprojectRow);
        $table->append($subprojectDDRow);
        $table->append($statusRow);
        $table->append($typeRow);
        $table->append($bigbetRow);
        $table->append($phaseRow);
        $table->append($effectiveRow);
        $table->append($plRow);
        $table->append($coplRow);
        $table->append($champRow);
        $table->append($descRow);
        $table->append($probRow);
        $table->append($solRow);
        
        $form->append($table);
        $form->append($challengeFieldSet);

        return $form;
    }
    
    function generateBody(){
        global $wgUser, $wgServer, $wgScriptPath;
        $this->html = "'Create Project' will create a new project, and automatically set up the mailing list.  Once the project is completed, project leaders can be created from the EditMember page.<br />";
        $form = self::createForm('new');
        $this->html .= $form->render();
        return $this->html;
    }
    
    function handleEdit(){
        global $wgMessages;
        $form = self::createForm('new');
        $status = $form->validate();
        if($status){
            // Call the API
            $form->getElementById("new_acronym")->setPOST("acronym");
            $form->getElementById("new_full_name")->setPOST("fullName");
            $form->getElementById("new_status")->setPOST("status");
            $form->getElementById("new_type")->setPOST("type");
            $form->getElementById("new_bigbet")->setPOST("bigbet");
            $form->getElementById("new_phase")->setPOST("phase");
            $form->getElementById("new_effective")->setPOST("effective_date");
            $form->getElementById("new_pl")->setPOST("pl");
            $form->getElementById("new_copl")->setPOST("copl");
            $form->getElementById("new_description")->setPOST("description");
            $form->getElementById("new_challenge")->setPOST("challenge");
            $form->getElementById("new_parent_id")->setPOST("parent_id");
            $form->getElementById("new_problem")->setPOST("problem");
            $form->getElementById("new_solution")->setPOST("solution");

            if(!APIRequest::doAction('CreateProject', true)){
                return "There was an error Creating the Project";
            }
            else{
                if($_POST['pl'] != ""){
                    $_POST['co_lead'] = "False";
                    $_POST['role'] = $_POST['acronym'];
                    $_POST['user'] = $_POST['pl'];
                    APIRequest::doAction('AddProjectLeader', true);
                }
                if($_POST['copl'] != ""){
                    $_POST['co_lead'] = "True";
                    $_POST['role'] = $_POST['acronym'];
                    $_POST['user'] = $_POST['copl'];
                    APIRequest::doAction('AddProjectLeader', true);
                }
                // Adding New Champions
                if(isset($_POST['new_champ_name'])){
                    foreach($_POST['new_champ_name'] as $key => $name){
                        if($name != ""){
                            $_POST['role'] = $_POST['role'] = $_POST['acronym'];
                            $_POST['user'] = $name;
                            $champ = Person::newFromName($name);
                            APIRequest::doAction('AddProjectMember', true);
                            MailingList::subscribeAll($champ);
                        }
                    }
                }
                $form->reset();
            }
        }
        else{
            return "The project was not created";
        }
    }
}    
    
?>
