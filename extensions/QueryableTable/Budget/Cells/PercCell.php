<?php

class PercCell extends Cell{
    
    var $target = -1;
    
    function PercCell($cellType, $params, $cellValue, $rowN, $colN, $table){
        $value = "";
        if(isset($params[0])){
            $this->target = intval($params[0]);
        }
        if($cellValue != ""){
            if(is_numeric($cellValue)){
                $value = $cellValue;
            }
            else{
                $value = $cellValue;
                $this->error = "&#39;$cellValue&#39; is not a valid percent value.";
            }
        }
        $this->value = $value;
    }
    
    function rasterize(){
        return array(PERC, $this);
    }
    
    function toString(){
        return $this->value."%";
    }
    
    function render(){
        $str = "";
        if($this->value != ""){
            if(strstr($this->style, "text-align:right;font-family: monospace !important;") === false){
                $this->style .= "text-align:right;font-family: monospace !important;";
            }
            $str = number_format($this->value*100, 0)."%";
        }
        if($this->target != -1){
            if($str != ""){
                $length = 6 - strlen("({$this->target}%)");
                for($i = 0; $i < $length; $i++){
                    $str .= "&nbsp;";
                }
                $str .= "({$this->target}%)";
            }
        }
        return $str;
    }
}

?>
