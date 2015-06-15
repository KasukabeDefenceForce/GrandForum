<?php

    class ProjectNINoLeadersArray extends GroupByArray {
        
        function ProjectNINoLeadersArray($table, $params){
            $project = $table->obj;
            if(isset($params[1]) && isset($params[2])){
                $start = $params[1];
                $end = $params[2];
                $people = $project->getAllPeopleDuring(null, $start, $end);
            }
            else{
                $people = $project->getAllPeople();
            }
            foreach($people as $person){
                if((isset($params[1]) && isset($params[2]) && $person->isRoleDuring(NI, $start, $end)) || 
                   ($person->isRole(NI))){
                    if(!$person->leadershipOf($project->getName()) && !$person->isRole(CHAMP)){
                        $this->array[] = $person->getName();
                    }
                }
            }
        }

    }

?>
