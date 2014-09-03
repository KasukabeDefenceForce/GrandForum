<?php

class AllSubProjectLeadersReportItemSet extends ReportItemSet {

    function getData(){
        $data = array();
        $project = Project::newFromId($this->projectId);
        if($project != null){
            $projects = $project->getSubProjects();
            $people = array();
            foreach($projects as $project){
                $leaders = array_merge($project->getLeaders(), $project->getCoLeaders());
                foreach($leaders as $lead){
                    $people[$lead->getId()] = $lead;
                }
            }
            if(is_array($people)){
                foreach($people as $person){
                    $tuple = self::createTuple();
                    $tuple['person_id'] = $person->getId();
                    $data[] = $tuple;
                }
            }
        }
        return $data;
    }

}

?>
