<?php

class ProgressReportItem extends StaticReportItem {

	function render(){
	    global $wgOut;
        $details = $this->getTableHTML();
        $item = "$details";
        $item = $this->processCData($item);
		$wgOut->addHTML($item);
	}
	
	function renderForPDF(){
	    global $wgOut;
        $details = $this->getTableHTML();
        $item = str_replace("≈","","$details");
        $item = $this->processCData($item);
		$wgOut->addHTML($item);
	}
	
	function getTableHTML(){
	    $reportType = $this->getAttr('reportType', 'HQPReport');
	    $showCompleted = strtolower($this->getAttr('showCompleted', "true"));
        $person = Person::newFromId($this->personId);
        $project = Project::newFromId($this->projectId);
        $report = new DummyReport($reportType, $person, $project);
        $nEditableSections = 0;
        $nComplete = 0;
        $nFields = 0;
        $limit = 0;
        $actualChars = 0;
        $nExceeding = 0;
        $nEmpty = 0;
        $nTextareas = 0;
        $sections = array();
        foreach($report->sections as $section){
            if($section instanceof EditableReportSection && !$section->private){
                if($section->reportCharLimits){
                    if(count($section->number) > 0){
                        $numbers = array();
                        foreach($section->number as $n){
                            $numbers[] = AbstractReport::rome($n);
                        }
                        $sections[] = implode(', ', $numbers);
                    }
                    $nEditableSections++;
                    $limit += $section->getLimit();
                    $actualChars += $section->getActualNChars();
                    $nExceeding += $section->getExceedingFields();
                    $nEmpty += $section->getEmptyFields();
                    $nTextareas += $section->getNTextareas();
                    $nComplete += $section->getNComplete();
                    $nFields += $section->getNFields();
                }
            }
        }
        $rowspan = 0;
        if($limit > 0){
            $percentChars = number_format(($actualChars/max(1, $limit)*100), 0);
        }

        $errorChars = array();
        if($nExceeding > 0){
            $rowspan++; 
            $errorChars[] = "<td><span class='inlineError'>{$nExceeding} of the {$nTextareas}</span> ".Inflect::smart_pluralize($nTextareas, "field")." ".Inflect::smart_pluralize($nExceeding, "exceeds")." the maximum allowed characters\n</td>";
        }
        if($nEmpty > 0){
            $rowspan++;
            $errorChars[] = "<td><span class='inlineWarning'>{$nEmpty} of the {$nTextareas}</span> ".Inflect::smart_pluralize($nTextareas, "field")." ".Inflect::smart_pluralize($nEmpty, "contains")." no text\n</td>";
        }
        $details = "";
        if($rowspan > 0){
            $details = "<tr valign='top'><td rowspan='$rowspan' style='white-space:nowrap;width:1%;'><b>Report Status</b></td>";
            if($limit > 0){
                //$details .= "<tr><td>≈$percentChars% of maximum allowable characters (overall)\n</td></tr>";
            }
            $details .= implode("</tr><tr>", $errorChars)."</tr>";
        }
        else if($showCompleted == "true"){
            $details = "<tr valign='top'><td rowspan='1' style='white-space:nowrap;width:1%;'><b>Report Status</b></td>";
            $details .= "<td><span class='inlineSuccess'>All narative sections of the report have text and do not exceed character limits</span>\n</td></tr>";
        }
        return $details;
	}
}

?>
