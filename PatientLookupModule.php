<?php
namespace RedcapAfrica\PatientLookupModule;

class PatientLookupModule extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function redcap_every_page_top( int $project_id ) {
		if(strpos($_SERVER['REQUEST_URI'],"DataEntry/record_home.php") !== false) {
			header("Location: ".$this->getUrl("patientLookup.php"));
		}
	}

	
}