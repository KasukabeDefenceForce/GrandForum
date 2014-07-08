<?php

class ProjectBudgetTab extends AbstractTab {

    var $project;
    var $visibility;

    function ProjectBudgetTab($project, $visibility){
        parent::AbstractTab("Budget");
        $this->project = $project;
        $this->visibility = $visibility;
    }
    
    function generateBody(){
        global $wgUser, $wgServer, $wgScriptPath;
        $project = $this->project;
        $me = Person::newFromId($wgUser->getId());
        
        $this->showBudget();
        
        return $this->html;
    }

    function showBudget(){
        global $wgServer, $wgScriptPath, $wgUser, $wgOut, $config;
        $isLead = $this->visibility['isLead'];
        $project = $this->project;
        
        if($isLead){
            $wgOut->addScript("<script type='text/javascript'>
                $(document).ready(function(){
                    $('#budgetAccordion').accordion({autoHeight: false,
                                                     collapsible: true});
                });
            </script>");
            $this->html .= "<div id='budgetAccordion'>";
            $startYear = YEAR;
            if($project->deleted){
                $startYear = substr($project->getDeleted(), 0, 4)-1;
            }
            $phaseDates = $config->getValue("projectPhaseDates");
            for($i=$startYear; $i >= max((substr($phaseDates[1], 0, 4)+1), substr($project->getCreated(), 0, 4)); $i--){
                $this->html .= "<h3><a href='#'>".$i."</a></h3>";
                $this->html .= "<div style='overflow: auto;'>";

                $budget = $project->getAllocatedBudget($i-1);
                if($budget != null){
                    $this->html .= $budget->render();
                }
                else {
                    $this->html .= "No budget could be found for $i";
                }
                $this->html .="</div>";
            }
            if($i == $startYear){
                $this->html .= "No Allocated Budgets have been created yet for this project.";
            }
            $this->html .= "</div>";

        }
    }
}    
    
?>
