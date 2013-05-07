<?php

require_once('../commandLine.inc');
require_once($dir . '../../Classes/PHPExcel/IOFactory.php');

if(count($args) > 0){
    if($args[0] == "help"){
        showHelp();
        exit;
    }
}

exportRelations();
//getNIs();
//getSurveyNIs();

function getNIs(){
	$cnis = Person::getAllPeopleDuring('CNI', "2012-01-01 00:00:0", "2013-03-10 23:59:59");
	$pnis = Person::getAllPeopleDuring('PNI', "2012-01-01 00:00:0", "2013-03-10 23:59:59");
	$nis = array_merge($cnis, $pnis);
	$ni_names = array();
	foreach($nis as $ni){
		if(!in_array($ni->getName(), $ni_names)){
			$ni_names[] = $ni->getName();
		}
	}
	
	print_r($ni_names);
	echo "\n". count($ni_names) ."\n";
}

function getSurveyNIs(){
	$sql = "SELECT sr.* FROM survey_results sr";
    $data = execSQLStatement($sql);
    $count = 0;
    foreach($data as $row){
        $user_id = $row['user_id'];
        $person = Person::newFromId($user_id);

        if(!($person->isRoleDuring('CNI', "2012-01-01 00:00:0", "2013-03-10 23:59:59") || $person->isRoleDuring('PNI', "2012-01-01 00:00:0", "2013-03-10 23:59:59")) ){
        	continue;
        }
        
        $submitted = ($row['submitted'] == 1)? "Yes" : "No";
	    if ($submitted == "No"){
	    	continue;
	    }
	    
        $count++;
    }
    echo $count ."\n";
}

function exportRelations(){
	$sql = "SELECT sr.* FROM survey_results sr";
    $data = execSQLStatement($sql);

    //EXCEL
    $phpExcel = new PHPExcel();
	$styleArray = array('font' => array('bold' => true));

	$cnis = Person::getAllPeopleDuring('CNI', "2012-01-01 00:00:0", "2013-03-10 23:59:59");
	$pnis = Person::getAllPeopleDuring('PNI', "2012-01-01 00:00:0", "2013-03-10 23:59:59");
	$nis = array_merge($cnis, $pnis);
	$ni_names = array();
	foreach($nis as $ni){
		if(!in_array($ni->getName(), $ni_names)){
			$ni_names[] = $ni->getName();
		}
	}
	sort($ni_names);

	$sheets = array(
	 	"People you know"=>"connected", 
	 	"Worked with"=>"work_with", 
	 	"Gave advice"=>"gave_advice", 
	 	"Received advice"=>"received_advice", 
	 	"Aquaintance"=>"acquaintance", 
	 	"Friend"=>"friend", 
	 	"In Person communication"=>"inperson", 
	 	"Email communication"=>"email", 
	 	"Phone communication"=>"phone", 
	 	"GRAND Forum communication"=>"forum", 
	 	"Other form of communication"=>"other");

	$sheetId = 0;
	foreach($sheets as $sheet=>$var_name){

		$phpExcel->createSheet(NULL, $sheetId);
		$phpExcel->setActiveSheetIndex($sheetId);
		$phpExcel->getActiveSheet()->setTitle($sheet);
		$sheetId++;

		$foo = $phpExcel->getActiveSheet();
		
		//add column headers, set the title and make the text bold
		$foo
		->setCellValue("A1", "Person1")
		->setCellValue("B1", "Person2")
		->setCellValue("C1", "Weight");
		$foo->getStyle("A1:B1:C1")->applyFromArray($styleArray);
		

		$row_count = 2;
	    foreach($data as $row){
	    	$user_id = $row['user_id'];
		    $person = Person::newFromId($user_id);

		    if(!$person->isCNI() && !$person->isPNI()){
		    	continue;
		    }

		    $submitted = ($row['submitted'] == 1)? "Yes" : "No";
		    if ($submitted == "No"){
		    	continue;
		    }

		    $f_name = $row['first_name'];
		    $l_name = $row['last_name'];
		    $name = $l_name."_".$f_name;

		    $connections = json_decode($row['grand_connections'], true);
	        $connections = ($connections)? $connections : array(); 
	      	$connections_flat = array();
	      	foreach($connections as $con){
	      		$connections_flat = array_merge($connections_flat, $con);
	      	}

	      	foreach($ni_names as $con_name){

	            $cname = explode('.', $con_name); 
	            $cnamef = $cname[0];
	            $cnamel = implode(' ', array_slice($cname, 1));
	            $cname = $cnamel."_".$cnamef;

	            $connected = 0;
	      		$con_data = array();
	      		if(isset($connections_flat[$con_name])){
	      			$connected = 1;
	      			$con_data = $connections_flat[$con_name];
	      		}

	            $work_with = (isset($con_data['work_with']))? $con_data['work_with'] : 0;
	            $gave_advice = (isset($con_data['gave_advice']))? $con_data['gave_advice'] : 0;
	            $received_advice = (isset($con_data['received_advice']))? $con_data['received_advice'] : 0;
	            $acquaintance = (isset($con_data['acquaintance']))? $con_data['acquaintance'] : 0;
	            $friend = (isset($con_data['friend']))? $con_data['friend'] : 0;

	            $com_weights = array(
		                        "moredaily"=>7, 
		                        "daily"=>6, 
		                        "weekly"=>5, 
		                        "monthly"=>4, 
		                        "moreyearly"=>3, 
		                        "yearly"=>2, 
		                        "lessyearly"=>1);

	            $com = (isset($con_data['communications']))? $con_data['communications'] : array();

	            $inperson = (isset($com['inperson']))? 
	            	(isset($com_weights[$com['inperson']])? $com_weights[$com['inperson']] : 0) : 0;
	            $email = (isset($com['email']))? 
	            	(isset($com_weights[$com['email']])? $com_weights[$com['email']] : 0) : 0;
	            $phone = (isset($com['phone']))? 
	            	(isset($com_weights[$com['phone']])? $com_weights[$com['phone']] : 0) : 0;
	            $forum = (isset($com['forum']))? 
	            	(isset($com_weights[$com['forum']])? $com_weights[$com['forum']] : 0) : 0;
	            
	            $other = 0;   
	    		$other_ind = key(preg_grep('/other/', array_keys($com)));
	    		if($other_ind){
		            $other = (isset($com[$other_ind]))? $com_weights[$com[$other_ind]] : 0;
		        }

		        $weight = $$var_name;

			    $foo
			    ->setCellValue("A{$row_count}", $name)
			    ->setCellValue("B{$row_count}", $cname)
			    ->setCellValue("C{$row_count}", $weight);

			    $row_count++;

		   	}
	        //break;
			//$row_count++;

		}
	}

	$phpExcel->setActiveSheetIndex(0);
	$objWriter = PHPExcel_IOFactory::createWriter($phpExcel, "Excel5");
	$objWriter->save('Survey-network.xls');
}


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
