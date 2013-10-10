<?php
require_once('../commandLine.inc');

    //Let's do it

    $current_evals = Person::getAllPeople(RMC); 
    $lois = LOI::getAllLOIs();

    $csv = 'Evaluator';
    
    foreach ($lois as $l){
        $loi_name = $l->getName();
        $csv .= ','.$loi_name;
    }
    $csv .="\n";

    $cur_year = date('Y');
    foreach($current_evals as $eval){
        $eval_id = $eval->getId();
        $eval_name = $eval->getName(); 
        
        $csv .= $eval_name;

        foreach ($lois as $loi) {
            $loi_id = $loi->getId();

            $sql = "SELECT * FROM grand_eval_conflicts WHERE eval_id = {$eval_id} AND sub_id = {$loi_id} AND type='LOI' AND year={$cur_year}";
            $data = execSQLStatement($sql);
            $conflict = "0";
            if(isset($data[0])){
                if(isset($data[0]['user_conflict']) && $data[0]['user_conflict'] == 1){
                    $conflict = "-10000";
                }
                else if(isset($data[0]['preference']) && $data[0]['preference'] == 1){
                    $conflict = "5000";
                }
            }
            $csv .= ','.$conflict;
        }
        $csv .= "\n";
    }
    //$csv .= "\n";


    echo $csv;
   


function execSQLStatement($sql, $update=false){
	if($update == false){
		$dbr = wfGetDB(DB_SLAVE);
	}
	else {
		$dbr = wfGetDB(DB_MASTER);
		return $dbr->query($sql);
	}
	$result = $dbr->query($sql);
	$rows = null;
	if($update == false){
		$rows = array();
		while ($row = $dbr->fetchRow($result)) {
			$rows[] = $row;
		}
	}
	return $rows;
}

?>
