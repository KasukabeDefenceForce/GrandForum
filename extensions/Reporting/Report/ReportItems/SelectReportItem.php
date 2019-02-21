<?php

class SelectReportItem extends AbstractReportItem {

	function render(){
		global $wgOut;
        $options = $this->parseOptions();
        $value = $this->getBlobValue();
        $width = (isset($this->attributes['width'])) ? $this->attributes['width'] : "";
        $inline = (strtolower($this->getAttr("inline", "false")) == "true");
        $items = array();
        $items[] = "<option value='' style='display:none;' disabled selected hidden>---</option>";
		foreach($options as $key => $option){
		    $selected = "";
		    if($value == $option){
		        $selected = "selected";
		    }
		    $option = str_replace("'", "&#39;", $option);
		    $items[] = "<option value='{$option}' $selected >{$option}</option>";
		}

        $output = "<select style='text-overflow:ellipsis;width:{$width};' name='{$this->getPostId()}'>".implode("\n", $items)."</select>";
        if(!$inline){
            $output = $this->processCData("<div>{$output}</div>");
        }
        else{
            $output = $this->processCData("<div style='display:inline-block;vertical-align:middle;'>{$output}</div>");
        }
		$wgOut->addHTML($output);
	}
	
	function parseOptions(){
	    $options = @explode("|", $this->attributes['options']);
	    return $options;
	}
	
	function renderForPDF(){
	    global $wgOut;
	    $attr = strtolower($this->getAttr("onlyShowIfNotEmpty"));
	    $val = $this->getBlobValue();
	    if($attr == "true" && empty($val)){
	        return "";
	    }
	    else if(empty($val)){
	    	$val = "N/A";
	    }

	    $item = $this->processCData("<i>{$val}</i>");
		$wgOut->addHTML($item);
	}
}

?>
