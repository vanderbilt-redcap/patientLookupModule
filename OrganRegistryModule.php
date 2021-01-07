<?php
namespace RedcapAfrica\OrganRegistryModule;

class OrganRegistryModule extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
        include_once(__DIR__."/vendor/autoload.php");
	}

	
}