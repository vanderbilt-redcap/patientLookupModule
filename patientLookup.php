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
	var delayTimer;
	function lookupPatient(string) {
		clearTimeout(delayTimer);
		delayTimer = setTimeout(function() {
			$.ajax({
				method:"POST",
				url: "<?php echo $module->getUrl("patientSearchAjax.php"); ?>",
				data: { searchValue: string }
			}).done(function(html) {
				$('#patient_results').html(html);
			});
		}, 500);
	}
</script>

<?php
require_once \ExternalModules\ExternalModules::getProjectFooterPath();