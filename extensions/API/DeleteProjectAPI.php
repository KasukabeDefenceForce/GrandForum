<?php

class DeleteProjectAPI extends API{

    function DeleteProjectAPI(){
        $this->addPOST("project",true,"The name of the project to delete", "MEOW");
	    $this->addPOST("effective_date",true, "The date that this action should take place", "2012-10-15");
    }

    function processParams($params){
        $_POST['project'] = @mysql_real_escape_string($_POST['project']);
        $_POST['effective_date'] = @mysql_real_escape_string($_POST['effective_date']);
    }

	function doAction($noEcho=false){
	    global $wgUser;
	    $me = Person::newFromUser($wgUser);
	    $project = Project::newFromName($_POST['project']);
	    if(!$me->isRoleAtLeast(STAFF) && 
	       (!$me->leadershipOf($project) ||
	        ($project->isSubProject() && !$me->leadershipOf($project->getParent()))
	       )){
	        return;
	    }
	    $theme1 = $project->getTheme(1);
	    $theme2 = $project->getTheme(2);
	    $theme3 = $project->getTheme(3);
	    $theme4 = $project->getTheme(4);
	    $theme5 = $project->getTheme(5);
		
	    $nsId = $project->getId();
	        
	    $status = $project->getStatus();
	    
	    $type = $project->getType();
	    $bigbet = $project->isBigBet();
	    $effective_date = (isset($_POST['effective_date'])) ? $_POST['effective_date'] : 'CURRENT_TIMESTAMP';
	    DBFunctions::begin();
	    $stat = true;
	    $sql = "INSERT INTO `grand_project_evolution` (`last_id`,`project_id`,`new_id`,`action`,`effective_date`)
	            VALUES ('{$project->evolutionId}','{$project->getId()}','{$nsId}','DELETE','{$effective_date}')";
	    $stat = DBFunctions::execSQL($sql, true, true);
	    if($stat){
	        $sql = "INSERT INTO `grand_project_status` (`evolution_id`,`project_id`,`status`,`type`,`bigbet`)
	            VALUES ((SELECT MAX(id) FROM grand_project_evolution),'{$nsId}','Ended','{$type}',{$bigbet})";
	        $stat = DBFunctions::execSQL($sql, true, true);
	    }
	    if($stat){
	        Project::$cache = array();
	        $project = Project::newFromId($nsId);
	        $_POST['project'] = $project->getName();
	        $_POST['description'] = $project->getDescription();
	        $_POST['problem'] = $project->getProblem();
	        $_POST['solution'] = $project->getSolution();
	        //$_POST['themes'] = "{$theme1},{$theme2},{$theme3},{$theme4},{$theme5}";
	        APIRequest::doAction('ProjectDescription', true);
	        //MailingList::removeMailingList($project);
	    }
	    DBFunctions::commit();
	    return $stat;
	}
	
	function isLoginRequired(){
		return true;
	}
}
?>
