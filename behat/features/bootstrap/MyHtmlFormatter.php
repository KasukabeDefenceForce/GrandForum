<?php

namespace Behat\Behat\Formatter;
use Behat\Behat\Formatter\HtmlFormatter;
use FeatureContext;

class MyHtmlFormatter extends HtmlFormatter {

    protected function printSuiteHeader($logger){
        $this->parameters->set('decorated', false);

        $template = $this->getHtmlTemplate();
        $header         = mb_substr($template, 0, mb_strpos($template, '{{content}}'));
        $this->footer   = mb_substr($template, mb_strpos($template, '{{content}}') + 11);
        $this->footer   = str_replace("<script type=\"text/javascript\">", "<script type='text/javascript' src='../../scripts/colorbox/jquery.colorbox-min.js'></script><script type=\"text/javascript\">", $this->footer);

        $header = str_replace("<head>", "<head>
            <link rel='stylesheet' type='text/css' href='../../scripts/colorbox/colorbox.css' />\n", $header);
        $this->writeln($header);
    }

    protected function getHtmlTemplateScript(){
        $script = parent::getHtmlTemplateScript();
        $script .= "$(document).ready(function(){
            $('.colorbox').colorbox({maxHeight: '100%'});
        });";
        return $script;
    }
    
    protected function printTimeSummary($logger){
        $this->writeln('');
        $time       = $logger->getTotalTime();
        $minutes    = floor($time / 60);
        $seconds    = round($time - ($minutes * 60), 3);

        $this->writeln($minutes . 'm' . $seconds . 's' .' ('.@date('D, j M Y g:i:s a').')');
        $this->writeln('');
    }

    protected function printStep($step, $result, $definition = null, $snippet = null, $exception = null){
        $this->writeln('<li class="' . $this->getResultColorCode($result) . '">');
        $this->writeln("<a title='".str_replace("'", "&#39;", $step->getText())."' rel='scenario".FeatureContext::$scenarioId."' class='colorbox ".$this->getResultColorCode($result)."' href='../screenshots/".FeatureContext::$scenarioId."_".(FeatureContext::$stepId-1).".png'>");
        PrettyFormatter::printStep($step, $result, $definition, $snippet, $exception);
        $this->writeln("</a></li>");
    }
    
}

?>
