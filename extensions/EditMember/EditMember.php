<?php

require_once("EditMemberAdmin.php");

$dir = dirname(__FILE__) . '/';
$wgSpecialPages['EditMember'] = 'EditMember'; # Let MediaWiki know about the special page.
$wgExtensionMessagesFiles['EditMember'] = $dir . 'EditMember.i18n.php';
$wgSpecialPageGroups['EditMember'] = 'network-tools';

$wgHooks['ToolboxLinks'][] = 'EditMember::createToolboxLinks';

function runEditMember($par) {
  EditMember::run($par);
}

class EditMember extends SpecialPage{

    function EditMember() {
        wfLoadExtensionMessages('EditMember');
        if(FROZEN){
            SpecialPage::SpecialPage("EditMember", STAFF.'+', true, 'runEditMember');
        }
        else{
            SpecialPage::SpecialPage("EditMember", CNI.'+', true, 'runEditMember');
        }
    }

    function run($par){
        global $wgOut, $wgUser, $wgServer, $wgScriptPath, $wgTitle, $wgMessage;
        $user = Person::newFromId($wgUser->getId());
        $date = date("Y-m-d");
        $wgOut->addScript("<script type='text/javascript'>
                                $(document).ready(function(){
                                $('.datepicker').datepicker({showOn: 'both',
                                                            buttonImage: '../skins/calendar.gif',
                                                            buttonText: 'Date',
                                                            buttonImageOnly: true});
                                $('.datepicker').datepicker('option','dateFormat', 'yy-mm-dd');
                                $('.datepicker').datepicker('option','showAnim', 'blind');
                                $('.datepicker').keydown(function(){
                                    return false;
                                });
                                $('.datepicker').attr('value', '$date');
                                $('#tabs').tabs({
                                                    cookie: {
                                                        expires: 1
                                                    }
                                                });
                            });
                            
                            function addComment(box, cannotchange){
                                if(cannotchange){
                                    if(!$(box).is(':checked') && $(box).hasClass('already')){
                                        $(box).attr('checked', 'checked');
                                        alert('You cannot change the role of an HQP that is supervised by someone else.');
                                    }
                                }
                                else{
                                    if(!$(box).is(':checked') && $(box).hasClass('already')){
                                        $(box).next().slideDown('fast');
                                    }
                                    else{
                                        $(box).next().slideUp('fast');
                                    }
                                }
                            }  
                           </script>");
        if(isset($_GET['action']) && $_GET['action'] == "view" && $user->isRoleAtLeast(STAFF)){
            if(isset($_POST['submit']) && $_POST['submit'] == "Accept"){
                // Admin Accepted
                EditMember::handleAdminAccept();
            }
            else if(isset($_POST['submit']) && $_POST['submit'] == "Ignore"){
                // Admin Ignored
                DBFunctions::update('grand_role_request',
                                    array('last_modified' => EQ(COL('SUBDATE(CURRENT_TIMESTAMP, INTERVAL 5 SECOND)')),
                                          'staff' => $user->getId(),
                                          '`ignore`' => 1),
                                    array('id' => EQ($_POST['id'])));
            }
            EditMember::generateViewHTML($wgOut);
        }
        else if(!isset($_POST['submit'])){
            // Form not entered yet
            if(isset($_GET['next']) || isset($_POST['next']) || isset($_GET['name'])){
                if(!isset($_GET['next']) && isset($_POST['next'])){
                    $_GET['next'] = $_POST['next'];
                }
                if(!isset($_GET['name']) && isset($_POST['name'])){
                    $_GET['name'] = $_POST['name'];
                }
                $person = @Person::newFromName($_GET['name']);
                $roles = $person->getRoles();
                if(!isset($_GET['name'])){
                    $wgMessage->addError("A user was not provided.");
                    EditMember::generateMain();
                    return;
                }
                else if(!$user->isRoleAtLeast(STAFF) && ((($user->isPNI() || $user->isCNI()) && !$user->isProjectLeader() && !$user->isProjectCoLeader() && $person->isRoleAtLeast(CNI)) || // Handles regular PNI/CNI
                        ((($user->isProjectLeader() || $user->isProjectCoLeader()) && $person->isRoleAtLeast(RMC) && !$person->isRole(PNI) && !$person->isRole(CNI) && !$person->isRole(HQP))) || // Handles PL/COPL
                        (($user->isRoleAtLeast(RMC) && $user->isRoleAtMost(GOV) && $person->isRoleAtLeast(STAFF))))){ // Handles RMC-GOV
                    $wgMessage->addError("You do not have permissions to edit this user.");
                    EditMember::generateMain();
                    return;
                }
                EditMember::generateEditMemberFormHTML($wgOut);
            }
            else{
                EditMember::generateMain();
            }
        }
        else{
            // The Form has been entered
            $person = @Person::newFromName(str_replace(" ", ".", $_POST['name']));
            
            $p_current = array();
            $r_current = array();
            $projects = $person->getProjects(false, true);
            $roles = $person->getRoles();
            foreach($projects as $project){
                $p_current[] = $project->getId();
            }
            $p_current = implode(", ", $p_current);
            if(isset($_POST['p_wpNS'])){
                foreach($_POST['p_wpNS'] as $key => $proj){
                    $proj = Project::newFromName($proj);
                    if($proj != null){
                        $_POST['p_wpNS'][$key] = $proj->getId();
                    }
                }
                $p_nss = implode(", ", $_POST['p_wpNS']);
            }
            else{
                $p_nss = "";
                $_POST['p_wpNS'] = array();
            }
            foreach($roles as $role){
                $r_current[] = $role->getRole();
            }
            $r_current = implode(", ", $r_current);
            if(isset($_POST['r_wpNS'])){
                $r_nss = implode(", ", $_POST['r_wpNS']);
            }
            else{
                $r_nss = "";
                $_POST['r_wpNS'] = array();
            }
            
            $p_comments = @EditMember::varToString($_POST['p_comment'], $p_current, $p_nss, 'PROJECT', $person);
            $r_comments = @EditMember::varToString($_POST['r_comment'], $r_current, $r_nss, 'ROLE', $person);
            $p_effectiveDates = @EditMember::varToString($_POST['p_datepicker'], $p_current, $p_nss, 'PROJECT', $person);
            $r_effectiveDates = @EditMember::varToString($_POST['r_datepicker'], $r_current, $r_nss, 'ROLE', $person);

            $me = Person::newFromId($wgUser->getId());
            $message = "";
            // Project Request
            if(EditMember::roleDiff($person, $p_current, $p_nss, 'PROJECT') != ""){
                DBFunctions::insert('grand_role_request',
                                    array('effective_date' => EditMember::parse($p_effectiveDates),
                                          'requesting_user' => EditMember::parse($wgUser->getId()),
                                          'current_role' => EditMember::parse($p_current),
                                          'role' => EditMember::parse($p_nss),
                                          'comment' => EditMember::parse($p_comments),
                                          'user' => EditMember::parse($person->getId()),
                                          'type' => 'PROJECT',
                                          'created' => 0,
                                          '`ignore`' => 0));
                Notification::addNotification("", $me, "Project Change Pending", "{$person->getNameForForms()}'s projects have been requested to be changed.  Once an admin sees this request they will review and accept it", "");
                $message .= EditMember::roleDiff($person, $p_current, $p_nss, 'PROJECT');
            }
            
            // Roles Request
            $other = "";
            if(isset($_POST['reason']) && $_POST['reason'] == "graduated" && strstr($r_comments, HQP."::") !== false && isset($_POST['thesis'])){
                $other = array("reason" => $_POST['reason'], 
                               "thesisId" => $_POST['thesis'],
                               "thesisTitle" => Paper::newFromId($_POST['thesis'])->getTitle());
            }
            else if(isset($_POST['reason']) && $_POST['reason'] == "movedOn" && strstr($r_comments, HQP."::") !== false){
                $other = array("reason" => $_POST['reason'],
                               "where" => str_replace("'", "&#39;", $_POST['employer']));
            }
            $processOthers = true;
            if(EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE') != ""){
                $_POST['user'] = $person->getName();
                if($person->isRole(HQP) && strstr(EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE'), '-'.HQP) !== false){
                    // Skip the request, go straight to making the change, but still add the request for logging this history
                    
                    // Check whether the HQP is being 'promoted' or not
                    if(strstr(EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE'), "+") === false ){
                        // The only action is removing the HQP role, so go ahead and do the HQP Inactivation
                        EditMember::processHQPInactivation($person, $r_nss, $r_comments, $r_effectiveDates, $other);
                        $person = Person::newFromName($_POST['user']);
                        $processOthers = false;
                    }
                    EditMember::processHQPMovedOn();
                }
                if($processOthers && EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE') != "" && EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE') != "-".INACTIVE."<br />\n"){
                    DBFunctions::insert('grand_role_request',
                                        array('effective_date' => EditMember::parse($r_effectiveDates),
                                              'requesting_user' => EditMember::parse($wgUser->getId()),
                                              'current_role' => EditMember::parse($r_current),
                                              'role' => EditMember::parse($r_nss),
                                              'comment' => EditMember::parse($r_comments),
                                              'other' => serialize($other),
                                              'user' => EditMember::parse($person->getId()),
                                              'type' => 'ROLE',
                                              'created' => 0,
                                              '`ignore`' => 0));
                    Notification::addNotification("", $me, "Role Change Pending", "{$person->getNameForForms()}'s roles have been requested to be changed.  Once an admin sees this request they will review and accept it", "");
                }
            }
            if($processOthers && EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE') != "" && EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE') != "-".INACTIVE."<br />\n"){
                $message .= EditMember::roleDiff($person, $r_current, $r_nss, 'ROLE');
            }
            if($message != ""){
                $wgMessage->addSuccess("The user <b>{$person->getNameForForms()}</b> has been requested to have the following role changes:<br /><p style='margin-left:15px;'>".$message."</p>Once an admin sees this request they will review and accept it");
            }
            $wgOut->addHTML("<a href='$wgServer$wgScriptPath/index.php/Special:EditMember'>Click Here</a> to continue Editing Members.");
            if($user->isRoleAtLeast(STAFF)){
                // Project Leadership Changes
                $pl = array();
                $copl = array();
                $pm = array();
                if(isset($_POST['pl'])){
                    foreach($_POST['pl'] as $value){
                        $pl[$value] = $value;
                    }
                }
                if(isset($_POST['copl'])){
                    foreach($_POST['copl'] as $value){
                        $copl[$value] = $value;
                    }
                }
                if(isset($_POST['pm'])){
                    foreach($_POST['pm'] as $value){
                        $pm[$value] = $value;
                    }
                }
            
                $currentPL = array();
                $currentCoPL = array();
                // Removing Project Leaders
                foreach($person->getLeadProjects() as $project){
                    if(!$person->managementOf($project)){
                        if(!isset($pl[$project->getName()])){
                            // Remove Project Leadership
                            $_POST['co_lead'] = 'False';
                            $_POST['manager'] = 'False';
                            $_POST['role'] = $project->getName();
                            $_POST['user'] = $person->getName();
                            $_POST['comment'] = @str_replace("'", "", $_POST["pl_comment"][$project->getName()]);
                            $_POST['effective_date'] = @$_POST["pl_datepicker"][$project->getName()];
                            APIRequest::doAction('DeleteProjectLeader', true);
                            $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is no longer a project leader of {$project->getName()}");
                        }
                        $currentPL[$project->getName()] = $project->getName();
                    }
                }
                foreach($person->getCoLeadProjects() as $project){
                    if(!$person->managementOf($project)){
                        if(!isset($copl[$project->getName()])){
                            // Remove Project co-Leadership
                            $_POST['co_lead'] = 'True';
                            $_POST['manager'] = 'False';
                            $_POST['role'] = $project->getName();
                            $_POST['user'] = $person->getName();
                            $_POST['comment'] = @str_replace("'", "", $_POST["copl_comment"][$project->getName()]);
                            $_POST['effective_date'] = @$_POST["copl_datepicker"][$project->getName()];
                            APIRequest::doAction('DeleteProjectLeader', true);
                            $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is no longer a co-project leader of {$project->getName()}");
                        }
                        $currentCoPL[$project->getName()] = $project->getName();
                    }
                }
                
                // Adding Project Leaders
                foreach($pl as $project){
                    if(!isset($currentPL[$project])){
                        // Add Project Leadership
                        $_POST['co_lead'] = 'False';
                        $_POST['manager'] = 'False';
                        $_POST['role'] = $project;
                        $_POST['user'] = $person->getName();
                        APIRequest::doAction('AddProjectLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is now a project leader of {$project}");
                    }
                }
                foreach($copl as $project){
                    if(!isset($currentCoPL[$project])){
                        // Add Project co-Leadership
                        $_POST['co_lead'] = 'True';
                        $_POST['manager'] = 'False';
                        $_POST['role'] = $project;
                        $_POST['user'] = $person->getName();
                        APIRequest::doAction('AddProjectLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is now a co-project leader of {$project}");
                    }
                }
                
                $currentPM = array();
                // Removing Project Managers
                foreach($person->getManagerProjects() as $project){
                    if(!isset($pm[$project->getName()])){
                        // Remove Project Leadership
                        $_POST['co_lead'] = 'False';
                        $_POST['manager'] = 'True';
                        $_POST['role'] = $project->getName();
                        $_POST['user'] = $person->getName();
                        $_POST['comment'] = @str_replace("'", "", $_POST["pm_comment"][$project->getName()]);
                        $_POST['effective_date'] = @$_POST["pm_datepicker"][$project->getName()];
                        APIRequest::doAction('DeleteProjectLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is no longer a project manager of {$project->getName()}");
                    }
                    $currentPM[$project->getName()] = $project->getName();
                }
                
                // Adding Project Managers
                foreach($pm as $project){
                    if(!isset($currentPM[$project])){
                        // Add Project Leadership
                        $_POST['co_lead'] = 'False';
                        $_POST['manager'] = 'True';
                        $_POST['role'] = $project;
                        $_POST['user'] = $person->getName();
                        APIRequest::doAction('AddProjectLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is now a project manager of {$project}");
                    }
                }
                
                // Theme Leadership Changes
                $tl = array();
                $cotl = array();
                if(isset($_POST['tl'])){
                    foreach($_POST['tl'] as $value){
                        $tl[$value] = Theme::newFromId($value);
                    }
                }
                if(isset($_POST['cotl'])){
                    foreach($_POST['cotl'] as $value){
                        $cotl[$value] = Theme::newFromId($value);
                    }
                }
            
                $currentTL = array();
                $currentCoTL = array();
                // Removing Theme Leaders
                foreach($person->getLeadThemes() as $theme){
                    if(!isset($tl[$theme->getId()])){
                        // Remove Theme Leadership
                        $_POST['co_lead'] = 'False';
                        $_POST['theme'] = $theme->getId();
                        $_POST['name'] = $person->getName();
                        $_POST['comment'] = @str_replace("'", "", $_POST["tl_comment"][$theme->getId()]);
                        $_POST['effective_date'] = $_POST["tl_datepicker"][$theme->getId()];
                        APIRequest::doAction('DeleteThemeLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is no longer a theme leader of {$theme->getAcronym()}");
                    }
                    $currentTL[$theme->getId()] = $theme->getId();
                }
                foreach($person->getCoLeadThemes() as $theme){
                    if(!isset($cotl[$theme->getId()])){
                        // Remove Theme co-Leadership
                        $_POST['co_lead'] = 'True';
                        $_POST['theme'] = $theme->getId();
                        $_POST['name'] = $person->getName();
                        $_POST['comment'] = @str_replace("'", "", $_POST["cotl_comment"][$theme->getId()]);
                        $_POST['effective_date'] = $_POST["cotl_datepicker"][$theme->getId()];
                        APIRequest::doAction('DeleteThemeLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is no longer a co-theme leader of {$theme->getAcronym()}");
                    }
                    $currentCoTL[$theme->getId()] = $theme->getId();
                }
                
                // Adding Theme Leaders
                foreach($tl as $theme){
                    if(!isset($currentTL[$theme->getId()])){
                        // Add Theme Leadership
                        $_POST['co_lead'] = 'False';
                        $_POST['theme'] = $theme->getId();
                        $_POST['name'] = $person->getName();
                        APIRequest::doAction('AddThemeLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is now a theme leader of {$theme->getAcronym()}");
                    }
                }
                foreach($cotl as $theme){
                    if(!isset($currentCoTL[$theme->getId()])){
                        // Add Theme co-Leadership
                        $_POST['co_lead'] = 'True';
                        $_POST['theme'] = $theme->getId();
                        $_POST['name'] = $person->getName();
                        APIRequest::doAction('AddThemeLeader', true);
                        $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> is now a co-theme leader {$theme->getAcronym()}");
                    }
                }
            }
            
            // Process date changes
            if(isset($_POST['project_start_dates'])){
                $projectHistory = $person->getProjectHistory(true);
                foreach($_POST['project_start_dates'] as $id => $start_date){
                    $project = array();
                    foreach($projectHistory as $proj){
                        if($id == $proj['id']){
                            $project = $proj;
                        }
                    }
                    if(isset($project['id'])){
                        $proj = Project::newFromId($project['project_id']);
                        if(isset($_POST['project_end_dates'][$id])){
                            $end_date = $_POST['project_end_dates'][$id];
                            if(substr($project['start_date'], 0, 10) != $start_date || 
                               substr($project['end_date'], 0, 10) != $end_date){
                                DBFunctions::update('grand_project_members',
                                                    array('start_date' => $start_date,
                                                          'end_date' => $end_date),
                                                    array('id' => $id,
                                                          'user_id' => $person->getId()));
                                $wgMessage->addSuccess("Changed dates for the project <b>{$proj->getName()}</b>");
                            }
                        }
                        else{
                            if(@substr($project['start_date'], 0, 10) != $start_date){
                                DBFunctions::update('grand_project_members',
                                                    array('start_date' => $start_date),
                                                    array('id' => $id,
                                                          'user_id' => $person->getId()));
                                $wgMessage->addSuccess("Changed dates for the project <b>{$proj->getName()}</b>");
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Returns a string representation of the given variable containing role details
    private function varToString($var, $current, $nss, $type, $person){
        $diff = EditMember::roleDiff($person, $current, $nss, $type);
        $return = "";
        if(isset($var)){
            foreach($var as $key => $value){
                if($value != "" && ($nss == "" || strstr($nss, $key) === false) && strstr($diff, '-'.$key) !== false){
                    if($type == "PROJECT"){
                        $proj = Project::newFromName($key);
                        $key = $proj->getId();
                    }
                    $return .= "$key::".EditMember::parse($value)." ::";
                }
            }
        }
        return substr($return,0,-2);
    }
    
    // Performs the HQPInactivation
    // * Logs this action in the grand_role_request table
    // * Adds the request for the HQP to fill out their HQP Inactivation report
    private function processHQPInactivation($person, &$r_nss, &$r_comments, &$r_effectiveDates, &$other){
        global $wgUser, $wgOut, $wgServer, $wgScriptPath, $wgMessage;
        $me = Person::newFromId($wgUser->getId());
        DBFunctions::insert('grand_role_request',
                            array('effective_date' => EditMember::parse($r_effectiveDates),
                                  'staff' => 0,
                                  'requesting_user' => EditMember::parse($wgUser->getId()),
                                  'role' => EditMember::parse($r_nss),
                                  'comment' => EditMember::parse($r_comments),
                                  'other' => serialize($other), 
                                  'user' => EditMember::parse($person->getId()),
                                  'type' => 'ROLE',
                                  'created' => 1,
                                  '`ignore`' => 0));
        $comment = "";
        $exploded = explode("HQP::", $r_comments);
        if(isset($exploded[1])){
            $exploded = explode("::", $exploded[1]);
            if(isset($exploded[0])){
                $comment = $exploded[0];
                // Remove the HQP comment from the request
                $r_comments = str_replace(HQP."::".$comment." ::", "", $r_comments);
                $r_comments = str_replace(HQP."::".$comment."::", "", $r_comments);
                $r_comments = str_replace(HQP."::".$comment, "", $r_comments);
            }
        }
        $exploded = explode("HQP::", $r_effectiveDates);
        $date = "";
        if(isset($exploded[1])){
            $exploded = explode("::", $exploded[1]);
            if(isset($exploded[0])){
                $date = $exploded[0];
                // Remove the HQP dates from the request
                $r_effectiveDates = str_replace(HQP."::".$date." ::", "", $r_effectiveDates);
                $r_effectiveDates = str_replace(HQP."::".$date."::", "", $r_effectiveDates);
                $r_effectiveDates = str_replace(HQP."::".$date, "", $r_effectiveDates);
            }
        }
        // Remove the HQP removal information from the request
        $other = "";
        //unset($_POST['r_wpNS'][HQP]);
        //$r_nss = str_replace(HQP.", ", "", $r_nss);
        //$r_nss = str_replace(", ".HQP, "", $r_nss);
        //$r_nss = str_replace(HQP, "", $r_nss);
        $_POST['r_wpNS'][] = HQP;
        $r_nss = implode(", ", $_POST['r_wpNS']);
        $nss = array();
        foreach($person->getRoles() as $role){
            if($role->getRole() != HQP){
                $nss[] = $role->getRole();
            }
        }
        $_POST['current_role'] = HQP;
        $_POST['role'] = implode(", ", $nss);
        $_POST['comment'] = HQP.'::'.$comment;
        $_POST['effectiveDates'] = HQP.'::'.$date;
        $_POST['user'] = $person->getId();
        $_POST['requesting_user'] = $me->getId();
        $_POST['type'] = 'ROLE';
        $_POST['id'] = '-1';
        
        $wgUser = User::newFromId(4); // Pretend to be Admin for a second
        EditMember::handleAdminAccept();
        $wgUser = User::newFromId($me->getId()); // Reset to current user
        
        Person::$cache = array();
        $person = Person::newFromId($_POST['user']);
        $year = substr($date, 0, 4);
        if($year == YEAR){
            Notification::addNotification($me, $person, "HQP Report", "{$me->getNameForForms()} has inactivated your account.  You will still have access to your HQP report until the end of the year.  You should fill in this report as soon as possible.", "$wgServer$wgScriptPath/index.php/Special:Report?report=HQPReport", true);
            $wgOut->addHTML("{$person->getNameForForms()} has been sent a notification to fill out their HQP Report.  When they have completed the report, you will be notified.<br />");
        }
    }
    
    // Changes/Inserts the data in the moved on/thesis tables if needed
    private function processHQPMovedOn(){
        global $wgMessage;
        $person = Person::newFromId($_POST['user']);
        $_POST['id'] = "new";
        if(isset($_POST['where']) || 
           isset($_POST['studies']) || 
           isset($_POST['employer']) || 
           isset($_POST['city']) || 
           isset($_POST['country'])){
            $_POST['where'] = @str_replace("'", "&#39;", $_POST['where']);
            $_POST['studies'] = @str_replace("'", "&#39;", $_POST['studies']);
            $_POST['employer'] = @str_replace("'", "&#39;", $_POST['employer']);
            $_POST['city'] = @str_replace("'", "&#39;", $_POST['city']);
            $_POST['country'] = @str_replace("'", "&#39;", $_POST['country']);
            $_POST['effective_date'] = @str_replace("'", "&#39;", $_POST['r_datepicker'][HQP]);
            APIRequest::doAction('AddHQPMovedOn', true);
            $wgMessage->addInfo("<b>{$person->getNameForForms()}</b>'s moved on information added.");
        }
        if(isset($_POST['thesis'])){
            APIRequest::doAction('AddHQPThesis', true);
            $wgMessage->addInfo("<b>{$person->getNameForForms()}</b>'s thesis added.");
        }
    }
    
    // Generates a more human readable form for the string used to add/remove roles
    function roleDiff($person, $current, $string, $type, $date=false){
        $output = "";
        //$date = "2013-11-28 10:24:25";
        //echo $date."<br />";
        if($type == "PROJECT"){
            $projects = explode(", ", $string);
            if(!is_null($current)){
                if($current == ""){
                    $current = array();
                }
                else{
                    $current = explode(", ", $current);
                }
            }
            else{
                $current = array();
                $projs = $person->getProjects($date, true);
                if($projs == null){
                    $projs = array();
                }
                foreach($projs as $project){
                    $current[] = $project->getName();
                }
            }
            foreach($current as $project){
                $id = array_search($project, $projects);
                if($id !== false){
                    // No Change
                    unset($projects[$id]);
                }
                else{
                    if(is_numeric($project)){
                        $project = Project::newFromId($project)->getName();
                    }
                    $output .= "-{$project}<br />\n";
                }
            }
            foreach($projects as $project){
                if($project != ""){
                    if(is_numeric($project)){
                        $project = Project::newFromId($project)->getName();
                    }
                    $output .= "+{$project}<br />\n";
                }
            }
        }
        if($type == "ROLE"){
            $roles = explode(", ", $string);
            if(!is_null($current)){
                $current = explode(", ", $current);
                foreach($current as $role){
                    $id = array_search($role, $roles);
                    if($id !== false){
                        // No Change
                        unset($roles[$id]);
                    }
                    else{
                        $output .= "-{$role}<br />\n";
                    }
                }
            }
            else{
                if(count($person->getRoles($date)) > 0){
                    foreach($person->getRoles($date) as $role){
                        $id = array_search($role->getRole(), $roles);
                        if($id !== false){
                            // No Change
                            unset($roles[$id]);
                        }
                        else{
                            $output .= "-{$role->getRole()}<br />\n";
                        }
                    }
                }
            }
            
            foreach($roles as $role){
                if($role != ""){
                    $output .= "+{$role}<br />\n";
                }
            }
        }
        return $output;
    }
    
    function generateMain(){
        global $wgOut, $wgUser, $wgServer, $wgScriptPath, $wgTitle;
        $user = Person::newFromId($wgUser->getId());
        $wgOut->addScript('<script type="text/javascript">
                            var sort = "first";
                            var allPeople = new Array(');
        $allPeople = Person::getAllPeople('all');
        $i = 0;
        $names = array();
        foreach($allPeople as $person){
            if(!$user->isRoleAtLeast(STAFF) && ((($user->isPNI() || $user->isCNI()) && !$user->isProjectLeader() && !$user->isProjectCoLeader() && $person->isRoleAtLeast(CNI)) || // Handles regular PNI/CNI
            ((($user->isProjectLeader() || $user->isProjectCoLeader()) && $person->isRoleAtLeast(RMC) && !$person->isRole(PNI) && !$person->isRole(CNI) && !$person->isRole(HQP))) || // Handles PL/COPL
            (($user->isRoleAtLeast(RMC) && $user->isRoleAtMost(GOV) && $person->isRoleAtLeast(STAFF)))  // Handles RMC-GOV
            )){ 
                // User does not have permission for this person
                continue;
            }
            $names[] = $person->getName();
        }
        if($user->isRoleAtLeast(STAFF)){
            foreach(Person::getAllStaff() as $person){
                $names[] = $person->getName();
            }
        }
        $wgOut->addScript('\''.implode("','", $names).'\');
        var oldOptions = Array();

        function filterResults(value){
            $.each($("#names").children().not("#no"), function(index, value){
                var valSelect = value.id;
                oldOptions[valSelect] = $("#" + valSelect).detach();
            });
            if(value == ""){
                var no = $("#no").detach();
                if(no.length > 0){
                    oldOptions["no"] = no;
                }
            }
            var n = 0;
            for(i = 0; i < allPeople.length; i++){
                var val = allPeople[i];
                var valSelect = "";
                if(sort == "last" && val.indexOf(".") != -1){
                    if(val.split(".").length == 2){
                        var firstName = val.split(".")[0];
                        var lastName = val.split(".")[1];
                    }
                    else{
                        var firstName = val.substring(0, val.lastIndexOf("."));
                        var lastName = val.split(".")[val.split(".").length - 1];
                    }
                    valSelect = lastName + firstName;
                    valSelect = valSelect.replace(/\./g, "");
                }
                else{
                    valSelect = val.replace(/\./g, "");
                }
                if(unaccentChars(val.replace(/\./g, " ")).regexIndexOf(unaccentChars(value)) != -1 || (typeof oldOptions[valSelect] != "undefined" && unaccentChars(oldOptions[valSelect].attr("class")).regexIndexOf(unaccentChars(value)) != -1)){
                    if(typeof oldOptions[valSelect] != "undefined"){
                        oldOptions[valSelect].appendTo($("#names"));
                    }
                    n++;
                }
            }
            if(n == 0){
                if(typeof oldOptions["no"] != "undefined"){
                    oldOptions["no"].appendTo($("#names"));
                }
            }
            else{
                var no = $("#no").detach();
                if(no.length > 0){
                    oldOptions["no"] = no;
                }
            }
        }
        
        function sortBy(type){
            var newAllPeople = Array();
            for(i = 0; i < allPeople.length; i++){
                var fullName = allPeople[i];
                var firstName = "";
                var lastName = "";
                if(fullName.indexOf(".") != -1){
                    if(type == "last" && sort == "first" || type == "first" && sort == "last"){
                        firstName = fullName.split(".")[0];
                        if(fullName.split(".").length == 2){
                            lastName = fullName.split(".")[1];
                        }
                        else{
                            lastName = fullName.substring(fullName.indexOf(".") + 1);
                        }
                    }
                    else{
                        if(fullName.split(".").length == 2){
                            firstName = fullName.split(".")[1];
                        }
                        else{
                            firstName = fullName.substring(fullName.indexOf(".") + 1);
                        }
                        lastName = fullName.split(".")[0];
                    }
                    newAllPeople[i] = lastName + "." + firstName;
                }
                else{
                    newAllPeople[i] = fullName;
                }
            }
            sort = type;
            allPeople = newAllPeople;
            allPeople.sort();
            filterResults($("#search").val());
        }
        
        $(document).ready(function(){
            var no = $("#no").detach();
            if(no.length > 0){
                oldOptions["no"] = no;
            }
            filterResults($("#search").val());
            
            $("#search").keypress(function(event) {
                if(event.keyCode == 40){        //DOWN
                    $.each($("#names").children(":selected").not("#no"), function(index, value){
                        if($(value).next().length > 0){
                            $(value).attr("selected", false);
                            $(value).next().attr("selected", true);
                        }
                    });
                }
                else if(event.keyCode == 38){   //UP
                    $.each($("#names").children(":selected").not("#no"), function(index, value){
                        if($(value).prev().length > 0){
                            $(value).attr("selected", false);
                            $(value).prev().attr("selected", true);
                        }
                    });
                }
            });
            
            $("#search").keyup(function(event) {
                if(event.keyCode != 40 && event.keyCode != 38){
                    filterResults(this.value);
                }
            });
            sortBy("first");
        });
        </script>');
        if($user->isRoleAtLeast(STAFF)){
            $wgOut->addHTML("<b><a href='$wgServer$wgScriptPath/index.php/Special:EditMember?action=view'>View Requests</a></b><br /><br />");
            foreach(Person::getAllStaff() as $person){
                $allPeople[] = $person;
            }
        }
        $wgOut->addHTML("This page can be used to edit the roles and projects of members on the forum.<br />
                         Select a user from the list below, and then click the 'Next' button.  You can filter out the selection box by searching a name or project below.<table>
                            <tr><td>
                                <a href='javascript:sortBy(\"first\");'>Sort by First Name</a> | <a href='javascript:sortBy(\"last\");'>Sort by Last Name</a><br />
                                <b>Search:</b> <input style='width:100%;' id='search' type='text' onKeyUp='filterResults(this.value);' />
                            </td></tr>
                            <tr><td>
                            <form action='$wgServer$wgScriptPath/index.php/Special:EditMember' method='post'>
                                <select id='names' name='name' size='10' style='width:100%'>
                                    <option id='no' disabled>Search did not match anyone</option>\n");
        foreach($allPeople as $person){
            $projects = $person->getProjects(false, true);
            $projs = array();
            foreach($projects as $project){
                $projs[] = $project->getName();
            }
            if(!$user->isRoleAtLeast(STAFF) && ((($user->isPNI() || $user->isCNI()) && !$user->isProjectLeader() && !$user->isProjectCoLeader() && $person->isRoleAtLeast(CNI)) || // Handles regular PNI/CNI
            ((($user->isProjectLeader() || $user->isProjectCoLeader()) && $person->isRoleAtLeast(RMC) && !$person->isRole(PNI) && !$person->isRole(CNI) && !$person->isRole(HQP))) || // Handles PL/COPL
            (($user->isRoleAtLeast(RMC) && $user->isRoleAtMost(GOV) && $person->isRoleAtLeast(STAFF)))  // Handles RMC-GOV
            )){
                // User does not have permission for this person
                continue;           
            }
            $wgOut->addHTML("<option class='".implode(" ", $projs)."' id='".str_replace(".", "", $person->getName())."'>".str_replace(".", " ", $person->getName())."</option>\n");
        }
        $wgOut->addHTML("</select>
                </td></tr>
                <tr><td>
            <input type='submit' name='next' value='Next' />
        </form></td></tr></table>");
    }
    
    function generateViewHTML($wgOut){
        global $wgScriptPath, $wgServer;
        $history = false;
        if(isset($_GET['history']) && $_GET['history'] == true){
            $history = true;
        }
        if($history){
            $wgOut->addHTML("<a href='$wgServer$wgScriptPath/index.php/Special:EditMember?action=view'>View New Requests</a><br /><br />
                        <table id='requests' style='display:none;background:#ffffff;text-align:center;' cellspacing='1' cellpadding='3' frame='box' rules='all'>
                        <thead><tr bgcolor='#F2F2F2'>
                            <th>Requesting User</th> <th>User Name</th> <th>Timestamp</th> <th>Effective Dates</th> <th>Staff</th> <th>Role/Project Diff</th> <th>Comment</th> <th>Other</th> <th>Type</th> <th>Status</th>
                        </tr></thead><tbody>\n");
        }
        else{
            $wgOut->addHTML("<a href='$wgServer$wgScriptPath/index.php/Special:EditMember?action=view&history=true'>View History</a><br /><br />
                        <table id='requests' style='display:none;background:#ffffff;text-align:center;' cellspacing='1' cellpadding='3' frame='box' rules='all'>
                        <thead><tr bgcolor='#F2F2F2'>
                            <th>Requesting User</th> <th>User Name</th> <th>Timestamp</th> <th>Effective Dates</th> <th>Role/Project Diff</th> <th>Comment</th> <th>Other</th> <th>Type</th> <th>Accept</th> <th>Ignore</th>
                        </tr></thead><tbody>\n");
        }
        if($history){
            $rows = DBFunctions::select(array('grand_role_request'),
                                        array('*'),
                                        array('created' => EQ(1),
                                              WHERE_OR('`ignore`') => EQ(1)),
                                        array('last_modified' => 'DESC'));
        }
        else{
            $rows = DBFunctions::select(array('grand_role_request'),
                                        array('*'),
                                        array('created' => EQ(0),
                                              '`ignore`' => EQ(0)));
        }
        foreach($rows as $row){
            $otherData = unserialize($row['other']);
            if(isset($otherData['thesisTitle'])){
                $other = "<b>Thesis:</b> {$otherData['thesisTitle']}";
            }
            else if(isset($otherData['where'])){
                $other = "<b>Now At:</b> {$otherData['where']}";
            }
            else{
                $other = "";
            }
            $req_user = Person::newFromId($row['requesting_user']);
            $staff = Person::newFromId($row['staff']);
            $person = Person::newFromId($row['user']);
            $projects = $req_user->getProjects(false, true);
            $projs = array();
            if(count($projects) > 0){
                foreach($projects as $project){
                    if(!$project->isSubProject()){
                        $subprojs = array();
                        foreach($project->getSubProjects() as $subproject){
                            if($req_user->isMemberOf($subproject)){
                                $subprojs[] = "<a href='{$subproject->getUrl()}'>{$subproject->getName()}</a>";
                            }
                        }
                        $subprojects = "";
                        if(count($subprojs) > 0){
                            $subprojects = "(".implode(", ", $subprojs).")";
                        }
                        $projs[] = "<a href='{$project->getUrl()}'>{$project->getName()}</a> $subprojects";
                    }
                }
            }
            $roles = array();
            if($req_user->getName() != null){
                foreach($req_user->getRoles() as $role){
                    $roles[] = $role->getRole();
                }
            }
            if($history){
                $diff = EditMember::roleDiff(Person::newFromId($row['user']), $row['current_role'], $row['role'], $row['type'], $row['last_modified']);
            }
            else{
                $diff = EditMember::roleDiff(Person::newFromId($row['user']), $row['current_role'], $row['role'], $row['type']);
            }
            $dates = explode("::", $row['effective_date']);
            foreach($dates as $key => $date){
                if($key % 2 == 0 && is_numeric($date)){
                    $proj = Project::newFromId($date);
                    $dates[$key] = $proj->getName();
                }
            }
            $wgOut->addHTML("<tr bgcolor='#FFFFFF'>
                        <td align='left'>
                            <a target='_blank' href='{$req_user->getUrl()}'><b>{$req_user->getName()}</b></a> (".implode(",", $roles).")<br /><a onclick='$(\"#{$row['id']}\").slideToggle();$(this).remove();' style='cursor:pointer;'>Show Projects</a>
                            <div id='{$row['id']}' style='display:none;padding-left:15px;'>".implode("<br />", $projs)."</div>
                        </td> 
                        <td align='left'><a target='_blank' href='{$person->getUrl()}'>{$person->getName()}</a></td> <td>{$row['last_modified']}</td> <td>".str_replace(" ::", "<br />", implode("::", $dates))."</td>");
            if($history){
                $wgOut->addHTML("<td>{$staff->getName()}</td>");
            }
            $comments = explode("::", $row['comment']);
            foreach($comments as $key => $comment){
                if($key % 2 == 0 && is_numeric($comment)){
                    $proj = Project::newFromId($comment);
                    $comments[$key] = $proj->getName();
                }
            }
            $wgOut->addHTML("<td align='left'>".$diff."</td> <td align='left'>".str_replace(" ::", "<br />", implode("::", $comments))."</td> <td align='left'>".$other."</td> <td align='left'>{$row['type']}</td>
                        <form action='$wgServer$wgScriptPath/index.php/Special:EditMember?action=view&sub' method='post'>
                            <input type='hidden' name='current_role' value='{$row['current_role']}' />
                            <input type='hidden' name='role' value='{$row['role']}' />
                            <input type='hidden' name='comment' value='{$row['comment']}' />
                            <input type='hidden' name='effectiveDates' value='{$row['effective_date']}' />
                            <input type='hidden' name='user' value='{$row['user']}' />
                            <input type='hidden' name='requesting_user' value='{$row['requesting_user']}' />
                            <input type='hidden' name='type' value='{$row['type']}' />
                            <input type='hidden' name='id' value='{$row['id']}' />");
            if(isset($otherData['thesisTitle'])){
                $wgOut->addHTML("<input type='hidden' name='thesis' value='{$otherData['thesisId']}' />");
            }
            else if(isset($otherData['where'])){
                $wgOut->addHTML("<input type='hidden' name='where' value='{$otherData['where']}' />");
            }
            if($history){
                if($row['created']){
                    $wgOut->addHTML("<td>Accepted</td>");
                }
                else{
                    $wgOut->addHTML("<td>Ignored</td>");
                }
            }
            else{
                $wgOut->addHTML("<td><input type='submit' name='submit' value='Accept' /></td> <td><input type='submit' name='submit' value='Ignore' /></td>");
            }
            $wgOut->addHTML("
                        </form>
                    </tr>");
        }
        $wgOut->addHTML("</tbody></table><script type='text/javascript'>
                                            $('#requests').dataTable().fnSort([[2,'desc']]);
                                            $('#requests').css('display', 'table');
                                         </script>");
    }
    
    function generateEditMemberFormHTML($wgOut){
        global $wgServer, $wgScriptPath, $wgUser;
        $me = Person::newFromId($wgUser->getId());
        $person = Person::newFromName(str_replace(" ", ".", $_GET['name']));
        $wgOut->addHTML("<form action='$wgServer$wgScriptPath/index.php/Special:EditMember?project' method='post'>
        <p>Select the Roles and Projects to which <b>{$person->getReversedName()}</b> should be a member of.  Deselecting a role or project will prompt further questions, relating to the reason why they are leaving that role.  Removing an HQP from the HQP role, the action will not require any approval, however all other actions will need to be approved by an Administrator.</p>");
        $wgOut->addHTML("<div id='tabs'>
                    <ul>
                        <li><a id='RolesTab' href='#tabs-1'>Roles</a></li>
                        <li><a id='ProjectsTab' href='#tabs-2'>Projects</a></li>");
        if($me->isRoleAtLeast(STAFF)){
            $wgOut->addHTML("<li><a id='LeadershipTab' href='#tabs-3'>Project Leadership</a></li>
                             <li><a id='ManagerTab' href='#tabs-4'>Project Manager</a></li>
                             <li><a id='ThemesTab' href='#tabs-5'>Theme Leaders</a></li>");
        }
        $wgOut->addHTML("
                    </ul>");
             
        $wgOut->addHTML("<div id='tabs-1'>");
        EditMember::generateRoleFormHTML($wgOut);
        $wgOut->addHTML("</div>");
        
        if(!$me->isRoleAtLeast(STAFF) && (($me->isProjectLeader() || $me->isProjectCoLeader()) && $person->isRoleAtLeast(RMC))){
            $wgOut->addHTML("<script type='text/javascript'>
                $('#RolesTab').parent().hide();
                $('#tabs-1').hide();
                $(document).ready(function(){
                    $('#tabs').tabs('select', 1);
                });
            </script>");
        }
        
        $wgOut->addHTML("<div id='tabs-2'>");
                        EditMember::generateProjectFormHTML($wgOut);
        $wgOut->addHTML("</div>");
        if($me->isRoleAtLeast(STAFF)){
            $wgOut->addHTML("<div id='tabs-3'>");
                                EditMember::generatePLFormHTML($wgOut);
            $wgOut->addHTML("</div>
                             <div id='tabs-4'>");
                                EditMember::generatePMFormHTML($wgOut);
            $wgOut->addHTML("</div>
                             <div id='tabs-5'>");
                                EditMember::generateTLFormHTML($wgOut);
            $wgOut->addHTML("</div>");
        }
        $wgOut->addHTML("</div>");
        $wgOut->addHTML("<br />
                         <input type='hidden' name='name' value='{$_GET['name']}' />
                         <input type='submit' name='submit' value='Submit Request' />
                         </form>");
    }
    
    function generateRoleFormHTML($wgOut){
        global $wgUser, $wgServer, $wgScriptPath, $wgRoles;
        $user = Person::newFromId($wgUser->getId());
        if(!isset($_GET['name'])){
            return;
        }
        $person = Person::newFromName(str_replace(" ", ".", $_GET['name']));
        $wgOut->addHTML("<table><tr>
                        <td class='mw-input'>");
        $boxes = "";
        if($person->isHQP()){
            $wgOut->addScript("<script type='text/javascript'>
                var theses = Array();\n");
            $theses = $person->getPapers();
            foreach($theses as $thesis){
                $title = $thesis->getTitle();
                if(strlen($thesis->getTitle()) > 50){
                    $title = substr($title, 0, 50)."...";
                }
                $wgOut->addScript("theses[{$thesis->getId()}] = '$title';\n");
            }
            $partners = array();
            foreach(Partner::getAllPartners() as $partner){
                $partners[] = $partner->getOrganization();
            }
            $universities = array();
            foreach(Person::getAllUniversities() as $uni){
                $universities[] = $uni;
            }
            $wgOut->addScript("
                var partners = [\"".implode("\",\n\"", $partners)."\"];
                var universities = [\"".implode("\",\n\"", $universities)."\"];
                
                function updateStep2(){
                    var reason = $('input[name=reason]:checked').val();
                    if(reason == 'graduated'){
                        var options = '<option value=\"No Thesis\">No Thesis</option>';
                        for(index in theses){
                            if(index != 'indexOf'){
                                options += '<option value=\"' + index + '\">' + theses[index] + '</option>';
                            }
                        }
                        var text = \"<tr><td valign='top' align='right'>Thesis:</td><td><select name='thesis'>\" + options + \"</select><br /><small>If The thesis is not in the list, then you can <a target='_blank' href='$wgServer$wgScriptPath/index.php/Special:AddPublicationPage'>add it</a> and then <a href='javascript:history.go(0);'>reload</a> this page.</small></td></tr>\" +
                                   \"<tr><td align='right'>Further&nbsp;Studies&nbsp;at:</td><td><input type='text' id='studies' size='25' name='studies' /></td></tr>\" +
                                   \"<tr><td align='right'>Employed&nbsp;by:</td><td><input type='text' id='employer' name='employer' size='25' /></td></tr>\" +
                                   \"<tr><td align='right' valign='top'>Location:</td><td></tr><tr><td align='right'>City:</td><td><input type='text' id='city' name='city' size='25' /></td></tr><td align='right'>Country:</td><td><input type='text' id='country' name='country' size='25' /></td></tr>\";
                        $('#step2').html(text);
                        $('#employer').autocomplete({
                            source: partners
                        });
                        $('#country').autocomplete({
                            source: countries
                        });
                        $('#studies').autocomplete({
                            source: universities
                        });
                    }
                    else if(reason == 'movedOn'){
                        var text = \"<tr><td align='right'>Further&nbsp;Studies&nbsp;at:</td><td><input type='text' id='studies' size='25' name='studies' /></td></tr>\" +
                                   \"<tr><td align='right'>Employed&nbsp;by:</td><td><input type='text' id='employer' name='employer' size='25' /></td></tr>\" +
                                   \"<tr><td align='right' valign='top'>Location:</td><td></tr><tr><td align='right'>City:</td><td><input type='text' id='city' name='city' size='25' /></td></tr><td align='right'>Country:</td><td><input type='text' id='country' name='country' size='25' /></td></tr>\";
                        $('#step2').html(text);
                        $('#employer').autocomplete({
                            source: partners
                        });
                        $('#country').autocomplete({
                            source: countries
                        });
                        $('#studies').autocomplete({
                            source: universities
                        });
                    }
                    $('#step3').show();
                }
                
                $(document).ready(function(){
                    updateStep2();
                });                           
            </script>");
        }
        foreach($wgRoles as $role){
            if(($role != ISAC || $user->isRoleAtLeast(STAFF)) && 
               ($role != NCE || $user->isRoleAtLeast(MANAGER)) && 
               ($user->isRoleAtLeast($role) || ($role == CHAMP && $user->isRoleAtLeast(COPL)))){
                $boxes .= "&nbsp;<input id='role_$role' type='checkbox' name='r_wpNS[]' value='".$role."' ";
                if(($user->isPNI() || $user->isCNI()) && $role == HQP && $person->isHQP() && !$user->relatedTo($person,"Supervises") && count($person->getSupervisors()) > 0 ){
                    $boxes .= "checked onChange='addComment(this, true)' class='already'"; //Prevent un-check
                }
                else if($person->isRole($role)){
                    $boxes .= "checked onChange='addComment(this, false)' class='already'";
                }
                if($role == HQP){
                    $boxes .= " /> $role<div style='display:none; padding-left:30px;'>
                                            <fieldset><legend>Reasoning</legend>
                                            <table>
                                            <tr id='step1'>
                                                <td colspan='2'><input type='radio' name='reason' value='graduated' onChange='updateStep2()' /> Graduated</td>
                                            </tr>
                                            <tr>
                                                <td colspan='2'><input type='radio' name='reason' value='movedOn' onChange='updateStep2()' /> Moved On</td>
                                            </tr>
                                            <tbody id='step2'>
                                                
                                            </tbody>
                                            <tr style='display:none;' id='step3'>
                                                <td align='right' style='width:0px;'>Date&nbsp;Effective:</td><td><input type='text' class='datepicker' id='datepicker{$role}' name='r_datepicker[$role]' /></td>
                                            </tr>
                                            <tr>
                                                <td colspan='2'>Additional Comments:<br /><textarea name='r_comment[".$role."]' cols='15' rows='4' style='height:auto;' ></textarea></td>
                                            </tr>
                                        </table>
                                        </fieldset>
                                        </div><br />";
                }
                else{
                    $boxes .= " /> $role<div style='display:none; padding-left:30px;'><fieldset><legend>Reasoning</legend><p>Date&nbsp;Effective:<input type='text' class='datepicker' id='datepicker{$role}' name='r_datepicker[$role]' /></p>Additional Comments:<br /><textarea name='r_comment[".$role."]' cols='15' rows='4' style='height:auto;' ></textarea></fieldset></div><br />";
                }
            }
            else{
                if($person->isRole($role)){
                    $boxes .= "<input type='hidden' name='r_wpNS[]' value='".$role."' />";
                }
            }
        }
        $wgOut->addHTML($boxes);
        $wgOut->addHTML("</td></tr></table>\n");
    }
    
    function generateProjectFormHTML($wgOut){
        global $wgUser, $wgServer, $wgScriptPath;
        $me = Person::newFromWgUser();
        $user = Person::newFromId($wgUser->getId());
        $myProjects = $user->getProjects(false, true);
        if(!isset($_GET['name'])){
            return;
        }
        $person = Person::newFromName(str_replace(" ", ".", $_GET['name']));
        $projects = Project::getAllProjects();

        $hidden_checkboxes = "";
        $projs = array();
        $myProjs = array();
        foreach($projects as $project){
            if($user->isRoleAtLeast(STAFF)){
                $skip = false;
            }
            else{
                $skip = true;
                foreach($myProjects as $myProject){
                    if($myProject != null && $project->getName() == $myProject->getName()){
                        $skip = false;
                        break;
                    }
                }
            }
            if(!$skip){
                $projs[] = $project;
                if($person->isMemberOf($project)){
                    $myProjs[] = $project->getName();
                }
                
                foreach($project->getSubProjects() as $subProj){
                    if($person->isMemberOf($subProj)){
                        $myProjs[] = $subProj->getName();
                    }
                }
            }
            else{
                if($person->isMemberOf($project)){
                    $hidden_checkboxes .= "<input type='hidden' name='p_wpNS[]' value='{$project->getName()}' checked='checked' />";
                    foreach($project->getSubProjects() as $subProj){
                        if($person->isMemberOf($subProj)){
                            $hidden_checkboxes .= "<input type='hidden' name='p_wpNS[]' value='{$subProj->getName()}' checked='checked' />";
                        }
                    }
                }
            }
        }
        $projList = new ProjectList("p_wpNS", "Projects", $myProjs, $projs);
        $projList->attr('expand', false);
        $wgOut->addHTML($projList->render());
        $wgOut->addHTML("<script type='text/javascript'>
            $('input.p_wpNS.already').change(function(){
                addComment(this, false);
            });
        </script>");
        $wgOut->addHTML($hidden_checkboxes);
        
        $projects = $person->getProjectHistory(true);
        if(!$me->isRoleAtLeast(MANAGER)){
            // Manager can see all projects, but if not Manager then filter out some projects
            foreach($projects as $key => $proj){
                $project = Project::newFromId($proj['project_id']);
                if($project->isDeleted()){
                    unset($projects[$key]);
                }
            }
            $foundSup = false;
            foreach($person->getSupervisors(true) as $sup){
                if($sup->getId() == $me->getId()){
                    $foundSup = true;
                }
            }
            if(!$foundSup){
                foreach($projects as $key => $proj){
                    $project = Project::newFromId($proj['project_id']);
                    if(!($me->leadershipOf($project) || 
                        ($project->isSubProject() && $me->leadershipOf($project->getParent())))){
                        // Access not allowed
                        unset($projects[$key]);
                    }
                }
            }
        }
        if(count($projects) > 0){
            $wgOut->addHTML("<h2>Project Membership Dates</h2>You can change the project membership start and end dates for <b>{$person->getReversedName()}</b>.  You can change the start dates for all projects, however you can only change the end dates for projects that <b>{$person->getReversedName()}</b> has already been removed from.  The dates are in the format YYYY-MM-DD.");
            
            $wgOut->addHTML("<table class='dashboard' id='project_membership_dates' cellspacing='1' cellpadding='3' rules='all' frame='box'>
                <thead><tr>
                    <th>Project</th><th>Start Date</th><th>End Date</th>
                </tr></thead><tbody>");
            foreach($projects as $project){
                $proj = Project::newFromId($project['project_id']);
                if($proj != null){
                    $start = substr($project['start_date'], 0, 10);
                    $end = substr($project['end_date'], 0, 10);
                    $start = "<input data-id='{$project['id']}' name='project_start_dates[{$project['id']}]' class='datepicker_start' type='text' value='$start' size='10' />";
                    $end = ($end != "0000-00-00") ? "<input data-id='{$project['id']}' name='project_end_dates[{$project['id']}]' class='datepicker_end' type='text' value='$end' size='10' />" : "";
                    $name = $proj->getName();
                    if($proj->isSubProject()){
                        $name = "<span style='margin-left:15px;float:right;'><i>$name</i></span>";
                    }
                    $wgOut->addHTML("<tr><td>{$name}</td><td>{$start}</td><td>{$end}</td></tr>");
                }
            }
            $wgOut->addHTML("</tbody></table>
            <script type='text/javascript'>
                $('.datepicker_start').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    onClose: function(selectedDate){
                        $('.datepicker_end[data-id=' + $(this).attr('data-id') + ']').datepicker('option', 'minDate', selectedDate);
                    }
                });
                $('.datepicker_end').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    onClose: function(selectedDate){
                        $('.datepicker_start[data-id=' + $(this).attr('data-id') + ']').datepicker('option', 'maxDate', selectedDate);
                    }
                });
            </script>");
        }
    }

    function generatePLFormHTML($wgOut){
        global $wgUser, $wgServer, $wgScriptPath;
        $user = Person::newFromId($wgUser->getId());
        if(!isset($_GET['name'])){
            return;
        }
        $person = Person::newFromName(str_replace(" ", ".", $_GET['name']));
        $projects = Project::getAllProjects();
        
        $leadProjects = new Collection($person->getLeadProjects());
        $coLeadProjects = new Collection($person->getCoLeadProjects());
        $myLeadProjects = $leadProjects->pluck('name');
        $myCoLeadProjects = $coLeadProjects->pluck('name');

        $wgOut->addHTML("<h2>Project Leader</h2>");
        
        $projList = new ProjectList("pl", "Projects", $myLeadProjects, $projects);
        $projList->attr('expand', true);
        $wgOut->addHTML($projList->render());
        $wgOut->addHTML("<script type='text/javascript'>
            $('input.pl.already').change(function(){
                addComment(this, false);
            });
        </script>");
        
        $wgOut->addHTML("<h2>Project co-Leader</h2>");
        
        $projList = new ProjectList("copl", "Projects", $myCoLeadProjects, $projects);
        $projList->attr('expand', true);
        $wgOut->addHTML($projList->render());
        $wgOut->addHTML("<script type='text/javascript'>
            $('input.copl.already').change(function(){
                addComment(this, false);
            });
        </script>");
    }
    
    function generateTLFormHTML($wgOut){
        global $wgUser, $wgServer, $wgScriptPath;
        $user = Person::newFromId($wgUser->getId());
        if(!isset($_GET['name'])){
            return;
        }
        $person = Person::newFromName(str_replace(" ", ".", $_GET['name']));
        $wgOut->addHTML("<h2>Theme Leader</h2>");
        $wgOut->addHTML("<table border='0' cellspacing='2'>");
        $leadThemes = $person->getLeadThemes();
        $themes = Theme::getAllThemes(PROJECT_PHASE);
        foreach($themes as $theme){
            $themeId = $theme->getId();
            $isLead = false;
            foreach($leadThemes as $t){
                if($t->getId() == $themeId){
                    $isLead = true;
                    break;
                }
            }
            if($isLead){
                $wgOut->addHTML("<tr><td style='min-width:150px;' valign='top'><input type='checkbox' name='tl[]' value='$themeId' checked='checked' class='already' onChange='addComment(this, false);' />{$theme->getAcronym()}<div style='display:none; padding-left:30px;'><fieldset><legend>Reasoning</legend><p>Date Effective:<input type='text' class='datepicker' id='tl_datepicker{$themeId}' name='tl_datepicker[$themeId]' /></p>Additional Comments:<br /><textarea name='tl_comment[$themeId]' cols='15' rows='4' style='height:auto;'></textarea></fielset></div><br /></td></tr>\n");
            }
            else {
                $wgOut->addHTML("<tr><td style='min-width:150px;' valign='top'><input type='checkbox' name='tl[]' value='$themeId' />{$theme->getAcronym()}</td></tr>\n");
            }
        }
        $wgOut->addHTML("</table>");
        
        $wgOut->addHTML("<h2>Theme Co-Leader</h2>");
        $wgOut->addHTML("<table border='0' cellspacing='2'>");
        $coLeadThemes = $person->getCoLeadThemes();
        foreach($themes as $theme){
            $themeId = $theme->getId();
            $isLead = false;
            foreach($coLeadThemes as $t){
                if($t->getId() == $themeId){
                    $isLead = true;
                    break;
                }
            }
            if($isLead){
                $wgOut->addHTML("<tr><td style='min-width:150px;' valign='top'><input type='checkbox' name='cotl[]' value='$themeId' checked='checked' class='already' onChange='addComment(this, false);' />{$theme->getAcronym()}<div style='display:none; padding-left:30px;'><fieldset><legend>Reasoning</legend><p>Date Effective:<input type='text' class='datepicker' id='cotl_datepicker{$themeId}' name='cotl_datepicker[$themeId]' /></p>Additional Comments:<br /><textarea name='cotl_comment[$themeId]' cols='15' rows='4' style='height:auto;'></textarea></fielset></div><br /></td></tr>\n");
            }
            else {
                $wgOut->addHTML("<tr><td style='min-width:150px;' valign='top'><input type='checkbox' name='cotl[]' value='$themeId' />{$theme->getAcronym()}</td></tr>\n");
            }
        }
        $wgOut->addHTML("</table>");
    }
    
    function generatePMFormHTML($wgOut){
        global $wgUser, $wgServer, $wgScriptPath;
        $user = Person::newFromId($wgUser->getId());
        if(!isset($_GET['name'])){
            return;
        }
        $person = Person::newFromName(str_replace(" ", ".", $_GET['name']));
        $projects = Project::getAllProjects();
        
        $leadProjects = new Collection($person->getManagerProjects());
        $myLeadProjects = $leadProjects->pluck('name');
        
        $wgOut->addHTML("<h2>Project Manager</h2>");
        
        $projList = new ProjectList("pm", "Projects", $myLeadProjects, $projects);
        $projList->attr('expand', true);
        $wgOut->addHTML($projList->render());
        $wgOut->addHTML("<script type='text/javascript'>
            $('input.pm.already').change(function(){
                addComment(this, false);
            });
        </script>");
    }
    
    function handleAdminAccept(){
        global $wgOut, $wgUser, $wgServer, $wgScriptPath, $wgTitle, $wgMessage;
        $user = Person::newFromId($wgUser->getId());
        // Admin Accepted
        $person = Person::newFromId($_POST['user']);
        //Process Project Changes
        MailingList::subscribeAll($person);
        if($_POST['type'] == "PROJECT"){
            $unsubscribed = array();
            $comments = explode("::", $_POST['comment']);
            $effectiveDates = explode("::", $_POST['effectiveDates']);
            $current = explode(", ", $_POST['current_role']);
            $roles = explode(", ", $_POST['role']);
            foreach($current as $project){
                // Unsubscribe user from all of his project lists(defer until later loop)
                $unsubscribed[$project] = true;
            }
            foreach($roles as $role){
                if($role == ""){
                    //Probably don't need to continue, but to be on the safe side it will
                    continue;
                }
                if(isset($unsubscribed[$role])){
                    unset($unsubscribed[$role]);
                }
                else{
                    if(is_numeric($role)){
                        $proj = Project::newFromId($role);
                        $role = $proj->getName();
                    }
                    $_POST['role'] = $role;
                    APIRequest::doAction('AddProjectMember', true);
                    $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> added to $role");
                }
            }
            foreach($unsubscribed as $key => $unsub){
                $id1 = array_search($key, $comments);
                $id2 = array_search($key, $effectiveDates);
                $comment = "";
                $effectiveDate = "";
                $role = $key;
                if($id1 !== false && isset($comments[$id1+1])){
                    $comment = $comments[$id1+1];
                }
                if($id2 !== false && isset($effectiveDates[$id2+1])){
                    $effectiveDate = $effectiveDates[$id2+1];
                }
                $_POST['comment'] = $comment;
                $_POST['effective_date'] = $effectiveDate;
                if(is_numeric($key)){
                    $proj = Project::newFromId($key);
                    $key = $proj->getName();
                }
                $_POST['role'] = $key;
                if(Project::newFromName($key) != null){
                    APIRequest::doAction('DeleteProjectMember', true);
                    $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> removed from $key");
                }
            }
        }
        // Process Role Changes
        else if($_POST['type'] == "ROLE"){
            $unsubscribed = array();
            $comments = explode("::", $_POST['comment']);
            $effectiveDates = explode("::", $_POST['effectiveDates']);
            $current = explode(", ", $_POST['current_role']);
            $roles = explode(", ", $_POST['role']);
            foreach($current as $role){
                // Unsubscribe user from all of his project lists
                $unsubscribed[$role] = true;
            }
            foreach($roles as $role){
                if($role == ""){
                    //Probably don't need to continue, but to be safe it will
                    continue;
                }
                if(isset($unsubscribed[$role])){
                    unset($unsubscribed[$role]);
                }
                else{
                    $_POST['role'] = $role;
                    APIRequest::doAction('AddRole', true);
                    $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> added to $role");
                }
            }
            foreach($unsubscribed as $key => $unsub){
                $id1 = array_search($key, $comments);
                $id2 = array_search($key, $effectiveDates);
                $comment = "";
                $effectiveDate = "";
                $role = $key;
                if($id1 !== false && isset($comments[$id1+1])){
                    $comment = $comments[$id1+1];
                }
                if($id2 !== false && isset($effectiveDates[$id2+1])){
                    $effectiveDate = $effectiveDates[$id2+1];
                }
                $_POST['comment'] = $comment;
                $_POST['effective_date'] = $effectiveDate;
                $_POST['role'] = $key;
                APIRequest::doAction('DeleteRole', true);
                $wgMessage->addSuccess("<b>{$person->getReversedName()}</b> removed from $key");
            }
        }
        DBFunctions::update('grand_role_request',
                            array('last_modified' => EQ(COL('SUBDATE(CURRENT_TIMESTAMP, INTERVAL 5 SECOND)')),
                                  'staff' => $user->getId(),
                                  'created' => 1),
                            array('id' => $_POST['id']));
        MailingList::subscribeAll($person);
    }
    
    function parse($text){
        $text = str_replace("'", "&#39;", $text);
        $text = str_replace("\"", "&quot;", $text); 
        return $text;
    }
    
    static function createToolboxLinks($toolbox){
        global $wgServer, $wgScriptPath;
        $me = Person::newFromWgUser();
        if($me->isRoleAtLeast(CNI)){
            $toolbox['People']['links'][1] = TabUtils::createToolboxLink("Edit Roles", "$wgServer$wgScriptPath/index.php/Special:EditMember");
        }
        return true;
    }
}

?>
