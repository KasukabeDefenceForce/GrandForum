<?php

BackbonePage::register('PharmacyMap', 'PharmacyMap', 'network-tools', dirname(__FILE__));

$wgHooks['TopLevelTabs'][] = 'PharmacyMap::createTab';
$wgHooks['SubLevelTabs'][] = 'PharmacyMap::createSubTabs';

class PharmacyMap extends BackbonePage {
    
    function isListed(){
        return false;
    }
    
    function userCanExecute($user){
        return true;
    }
    
    static function getCategoryJSON(){
        $dir = dirname(__FILE__) . '/';
        $json = json_decode(file_get_contents("{$dir}categories.json"));
        return $json;
    }
    
    static function getCategoryLeaves($categoryJSON=null){
        $categories = array();
        if($categoryJSON == null){
            $categoryJSON = PharmacyMap::getCategoryJSON();
            foreach($categoryJSON as $category){
                $categories = array_merge($categories, PharmacyMap::getCategoryLeaves($category));
            }
        }
        else {
            if(isset($categoryJSON->children)){
                // Category has children
                foreach($categoryJSON->children as $category){
                    $categories = array_merge($categories, PharmacyMap::getCategoryLeaves($category));
                }
            }
            else{
                // Leaf found
                $categories[] = $categoryJSON;
            }
        }
        return $categories;
    }
    
    function getTemplates(){
        global $wgOut;
        $me = Person::newFromWgUser();
        if(isset($_GET['clickedProgram'])){
            $dcs = DataCollection::allFromUserId($me->getId(), "ProgramLibrary-*");
            $clicks = 0;
            foreach($dcs as $dc){
                $clicks += $dc->getField('websiteClicks', 0);
            }
            if($clicks >= 5){
                Gamification::log("5CommunitySupports");
            }
            exit;
        }
        $json = self::getCategoryJSON();
        $wgOut->addHTML("<script type='text/javascript'>
            var cat_json = ".json_encode($json).";
        </script>");
        return array('Backbone/*',
		     'pharmacy_map',
		     'pharmacy_add',
		     'community_row',
		     'category_buttons',
		    );
    }
    
    function getViews(){
        return array('Backbone/*',
		     'PharmacyMapView',
		     'PharmacyAddView',
		     'CommunityRowView',
		     'CategoryButtonsView',
		    );
    }
    
    function getModels(){
        return array('Backbone/*');
    }
    
    static function createTab(&$tabs){
        $tabs["Map"] = TabUtils::createTab("<en>Community Programs</en><fr>Répertoire des ressources</fr>", "", "");
        return true;
    }
    
    static function createSubTabs(&$tabs){
        global $wgServer, $wgScriptPath, $wgUser, $wgTitle;
        if($wgUser->isLoggedIn()){
            if(AVOIDDashboard::checkAllSubmissions($wgUser->getId())){
                $selected = @($wgTitle->getText() == "PharmacyMap") ? "selected" : false;
                $tabs["Map"]['subtabs'][] = TabUtils::createSubTab("Community Programs", "{$wgServer}{$wgScriptPath}/index.php/Special:PharmacyMap", $selected);
            }
        }
        return true;
    }

}

?>
