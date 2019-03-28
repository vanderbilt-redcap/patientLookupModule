<?php

$project = $_GET['pid'];

if($project == "") {
	throw new Exception("No project selected");
}


/* @var $module RedcapAfrica\PatientLookupModule\PatientLookupModule */
require_once \ExternalModules\ExternalModules::getProjectHeaderPath();
?>
<span>Search for Patient</span> <input id='patient_search' size='40' type='text' onchange='lookupPatient($(this).val());' /><Br />

<div id='patient_results'>
</div>

<script type='text/javascript'>
	function lookupPatient(string) {
		$.ajax({
			method:"POST",
			url: "<?php echo $module->getUrl("patientSearchAjax.php"); ?>",
			data: { searchValue: string }
		}).done(function(html) {
			$('#patient_results').html(html);
		});
	}
</script>

<?php
require_once \ExternalModules\ExternalModules::getProjectFooterPath();