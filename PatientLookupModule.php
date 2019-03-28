<?php
namespace RedcapAfrica\PatientLookupModule;

class PatientLookupModule extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function redcap_every_page_top( int $project_id ) {
		
	}

	
}