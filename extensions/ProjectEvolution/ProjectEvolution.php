<?php

autoload_register('ProjectEvolution/Tabs');

$dir = dirname(__FILE__) . '/';
$wgSpecialPages['ProjectEvolution'] = 'ProjectEvolution'; # Let MediaWiki know about the special page.
$wgExtensionMessagesFiles['ProjectEvolution'] = $dir . 'ProjectEvolution.i18n.php';
$wgSpecialPageGroups['ProjectEvolution'] = 'network-tools';

$wgHooks['SubLevelTabs'][] = 'ProjectEvolution::createSubTabs';

function runProjectEvolution($par){
    ProjectEvolution::run($par);
}

class ProjectEvolution extends SpecialPage {
    
    function ProjectEvolution(){
        wfLoadExtensionMessages('ProjectEvolution');
		SpecialPage::SpecialPage("ProjectEvolution", STAFF.'+', true, 'runProjectEvolution');
    }    
    
    function run(){
        global $wgOut;
        $tabbedPage = new TabbedPage("project");
        $tabbedPage->addTab(new CreateProjectTab());
        $tabbedPage->addTab(new EvolveProjectTab());
        $tabbedPage->addTab(new InactivateProjectTab());
        $tabbedPage->showPage();
        $wgOut->addHTML("<script type='text/javascript'>
            $('h1.custom-title').hide();
        </script>");
        $wgOut->output();
        $wgOut->disable();
        return true;
    }
    
    static function createSubTabs($tabs){
	    global $wgServer, $wgScriptPath, $wgTitle, $wgUser;
	    $person = Person::newFromWgUser();
	    if($person->isRoleAtLeast(MANAGER)){
	        $selected = @($wgTitle->getText() == "ProjectEvolution") ? "selected" : false;
	        $tabs["Manager"]['subtabs'][] = TabUtils::createSubTab("Project Evolution", "$wgServer$wgScriptPath/index.php/Special:ProjectEvolution", $selected);
	    }
	    return true;
    }
}
?>
