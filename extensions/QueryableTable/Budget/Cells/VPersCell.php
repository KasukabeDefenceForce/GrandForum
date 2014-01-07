<?php

class VPersCell extends Cell {
    
    function VPersCell($cellType, $params, $cellValue, $rowN, $colN, $table){
        if($cellValue != ''){
            $origCellValue = $cellValue;
            $names = explode(',', $origCellValue);
            $person = null;
            if(count($names) > 1){
                $name = $names[1].' '.$names[0];
                $person = Person::newFromNameLike($name);
                if($person == null || $person->getName() == null){
                    try{
                        $person = Person::newFromAlias($name);
                    }
                    catch(Exception $e){

                    }
                }
            }
            if($person == null || $person->getName() == null){
                $person = Person::newFromNameLike($origCellValue);
            }
            if($person == null || $person->getName() == null){
                try{
                    $person = Person::newFromAlias($origCellValue);
                }
                catch(Exception $e){
                
                }
            }
            if($person == null || $person->getName() == null){
                // FAIL
                $this->error = "&#39;$cellValue&#39; is not a valid researcher.";
            }
        }
        $this->value = $cellValue;
    }
    
    function rasterize(){
        return array(V_PERS, $this);
    }
    
    function toString(){
        return $this->value;
    }
    
    function render(){
        return $this->value;
    }
}

?>
