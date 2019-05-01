<?php

$project = $_GET['pid'];

if($project == "") {
	throw new Exception("No project selected");
}


/* @var $module RedcapAfrica\OrganRegistryModule\OrganRegistryModule */
require_once \ExternalModules\ExternalModules::getProjectHeaderPath();

echo "<link rel=\"stylesheet\" href=\"".$module->getUrl(__DIR__."/css/style.css")."\" />";
echo "<span>Search for Organ Recipient</span><br />";

$lookupFields = $module->getProjectSetting("matching-fields");
$metadata = $module->getMetadata($project);

foreach($lookupFields as $thisField) {
	echo "<div class='configDiv'>";
	echo "<h4>".$metadata[$thisField]["field_label"]."</h4>";

	if($metadata[$thisField]["field_type"] == "checkbox") {
		$options = $module->getChoiceLabels($thisField);

		foreach($options as $value => $label) {
			echo "<span>$label</span> <input type='checkbox' value='$value' name='$thisField-$value' /><br />";
		}
	}
	else if(in_array($metadata[$thisField]["field_type"],["radio","select","yesno","truefalse","sql"])) {
		switch($metadata[$thisField]["field_type"]) {
			case "radio":
			case "select":
				$options = $module->getChoiceLabels($thisField);
				break;
			case "yesno":
				$options = [1 => "yes", 0 => "no"];
				break;
			case "truefalse":
				$options = [1 => "true", 0 => "false"];
				break;
			case "sql":
				$options = [];
				break;
		}

		echo "<select name='$thisField'>";

		foreach($options as $value => $label) {
			echo "<option value='$value'>$label</option>";
		}

		echo "</select>";
	}
	else {
	    echo "<input type='text' name='$thisField' />";
    }
	
	echo "</div>";
}

?>
<div id='patient_results'>
</div>

<script type='text/javascript'>
	var delayTimer;
	function lookupPatient() {
		var searchData = [];

		$('.searchField').each(function() {
			searchData[$(this).attr('name')] = (($(this).attr('type') != 'checkbox' || $(this).prop('checked')) ? $(this).val() : "");
		});

		$.ajax({
			method:"POST",
			url: "<?php echo $module->getUrl("patientSearchAjax.php"); ?>",
			data: { searchValue: searchData }
		}).done(function(html) {
			$('#patient_results').html(html);
		});
	}
</script>

<?php
require_once \ExternalModules\ExternalModules::getProjectFooterPath();