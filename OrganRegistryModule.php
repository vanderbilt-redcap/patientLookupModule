<?php
namespace RedcapAfrica\OrganRegistryModule;

class OrganRegistryModule extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
        include_once(__DIR__."/vendor/autoload.php");
	}

	public function getRecordSurveyURL($recordId)
    {
        global $redcap_version;
        
        return rtrim(APP_PATH_WEBROOT_FULL,"/")."/redcap_v".$redcap_version."/DataEntry/record_home.php?pid=".$this->getProjectId()."&id=".$recordId;
    }
	
}