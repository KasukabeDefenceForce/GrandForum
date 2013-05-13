<?php

require_once('../commandLine.inc');


$cnis = array(); //Person::getAllPeople('CNI');
$pnis = Person::getAllPeople('PNI');

$all_people = array_merge($cnis, $pnis);
$unique = array();
$ni_errors = array();

foreach($all_people as $person){
	//echo $person->getName() . "\n";
	$name = $person->getName();
	$name_normal = $person->getNameForForms();
	
	if($person->isActive() && !in_array($person->getId(), $unique)){
		$unique[] = $person->getId();
		
		//Allocated Budget Upload
		//$person->getAllocatedBudget(2012);
		$year = 2012;
		$uid = $person->getId();
        $blob_type=BLOB_EXCEL;
        $rptype = RP_RESEARCHER;
    	$section = RES_ALLOC_BUDGET;
    	$item = 0;
    	$subitem = 0;
        $rep_addr = ReportBlob::create_address($rptype,$section,$item,$subitem);
        $budget_blob = new ReportBlob($blob_type, ($year-1), $uid, 0);
        $budget_blob->load($rep_addr);
        $data = $budget_blob->getData();
        if(is_null($data)){
        	$ni_errors["{$name}"]['budget_errors'] = array("Failed to upload revised budget.");
        }

		//Product completeness
		$papers = $person->getPapersAuthored("all", "2012-01-01 00:00:00", "2013-05-01 00:00:00", false);
		$person_paper_errors = array();

		foreach($papers as $paper){
			$paper_id = $paper->getId();
			$paper_title = $paper->getTitle();

			$errors = array();
			$completeness = $paper->getCompleteness();
			if(!$completeness['venue']){
				$errors[] = "Does not have a venue.";
			}

			if(!$completeness['pages']){
				$errors[] = "Does not have page information.";
			}

			if(!$completeness['publisher']){
				$errors[] = "Does not have a publisher.";
			}

			if(!empty($errors)){
				$person_paper_errors["{$paper_title}"] = $errors;
			}

		}
		
		$ni_errors["{$name}"]['paper_errors'] = $person_paper_errors;
		
		
		//Students moved on vs thesis
		$student_errors = array();
		$students = $person->getStudents('all', true);
		foreach($students as $s){
			$student_name = $s->getName();
			$position = $s->getPosition();
			$errors = array();
			$ishqp = $s->isHQP();
			
			//Check for Ethics tutorial completion
			$ethics = $s->getEthics();
			if($ethics['completed_tutorial'] == 0 && $ishqp){
				$errors[] = "Student has not indicated whether they have completed TCPS2 tutorial.";
			}

			//Acknowledgements
			if($ishqp){
				$acks = $s->getAcknowledgements();
				if(count($acks) > 0){
					$ack_found = false;
					foreach ($acks as $a){
						$supervisor = $a->getSupervisor();
						if($supervisor == $name_normal){
							$ack_found = true;
							break;
						}
					}
					if(!$ack_found){
						$errors[] = "There is no Acknowledgements found for this student.";
					}
				}
				else{
					$errors[] = "There is no Acknowledgements found for this student.";
				}
			}

			//Only care about Masters and PhDs for thesis errors
			if(!($position == "Masters Student" || $position == "PhD Student")){
				continue;
			}


			//Check for thesis and no exit data
			$thesis = $s->getThesis();
			if(!is_null($thesis)){
				$moved = $s->getMovedOn();
				if(empty($moved['studies']) && empty($moved['city']) && empty($moved['works']) && empty($moved['employer']) && empty($moved['country'])){
					$errors[] = "Student has a thesis, but is not marked as moved on.";
				}
			}
			else if(is_null($thesis) && !$ishqp){
				$moved = $s->getMovedOn();
				if(empty($moved['studies']) && empty($moved['city']) && empty($moved['works']) && empty($moved['employer']) && empty($moved['country'])){
					$errors[] = "Past student is no longer an HQP, however has no thesis records, and is not marked as moved on.";
				}
				else{
					$errors[] = "Past student is marked as moved on, however has no thesis record.";
				}
			}

			if(!empty($errors)){
				$student_errors["{$student_name}"] = $errors;
			}
		}

		$ni_errors["{$name}"]['student_errors'] = $student_errors;

	}
}

foreach($ni_errors as $name => $errors){
	if(!empty($errors['student_errors']) || !empty($errors['paper_errors']) ){
		echo "{$name}:\n";

		if(!empty($errors['budget_errors'])){
			echo " BUDGET ERRORS:\n";
			foreach ($errors['budget_errors'] as $name => $es){
				echo "    -{$es}\n";
			}
		}

		if(!empty($errors['paper_errors'])){
			echo " PAPER ERRORS:\n";
			foreach ($errors['paper_errors'] as $name => $es){
				echo "   {$name}:\n";
				foreach ($es as $e){
					echo "    -{$e}\n";
				}
			}
		}

		if(!empty($errors['student_errors'])){
			echo " STUDENT ERRORS:\n";
			foreach ($errors['student_errors'] as $name => $es){
				echo "   {$name}:\n";
				foreach ($es as $e){
					echo "    -{$e}\n";
				}
			}
		}
		echo "\n\n";
	}
}

//print_r($ni_errors);


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
