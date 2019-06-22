<?php

$project = $_GET['pid'];

if($project == "") {
	throw new Exception("No project selected");
}


/* @var $module RedcapAfrica\OrganRegistryModule\OrganRegistryModule */
require_once \ExternalModules\ExternalModules::getProjectHeaderPath();

echo "<link rel=\"stylesheet\" href=\"".$module->getUrl(__DIR__."/css/style.css")."\" />";
echo "<span>Search for Organ Recipient</span><br />";

$lookupFields = $module->getProjectSetting("search-fields");
$metadata = $module->getMetadata($project);

echo "<form id='searchForm'>";

foreach($lookupFields as $thisField) {
	echo "<div class='configDiv row'>";
	echo "<div class='col-md-4'><h4>".$metadata[$thisField]["field_label"]."</h4></div>";
	echo "<div class='col-md-8'>";

	if($metadata[$thisField]["field_type"] == "checkbox") {
		$options = $module->getChoiceLabels($thisField);

		foreach($options as $value => $label) {
			echo "<span>$label</span> <input type='checkbox' class='searchField' value='$value' name='$thisField-$value' /><br />";
		}
	}
	else if(in_array($metadata[$thisField]["field_type"],["radio","dropdown","yesno","truefalse","sql"])) {
		switch($metadata[$thisField]["field_type"]) {
			case "radio":
			case "dropdown":
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

		echo "<select name='$thisField' class='searchField'><option value=''></option>";

		foreach($options as $value => $label) {
			echo "<option value='$value'>$label</option>";
		}

		echo "</select>";
	}
	else {
	    echo "<input type='text' class='searchField' name='$thisField' />";
    }
	
	echo "</div></div>";
}

echo "<input type='button' onclick='lookupPatient();' value='Submit' />";

?>
<div id='patient_results'>
</div>

<script type='text/javascript'>
	function lookupPatient() {
		var searchData = {};

		$('.searchField').each(function() {
			searchData[$(this).attr('name')] = (($(this).attr('type') != 'checkbox' || $(this).prop('checked')) ? $(this).val() : "");
		});

		$.ajax({
			type:"POST",
			url: "<?php echo $module->getUrl("patientSearchAjax.php"); ?>",
			data: searchData
		}).done(function(html) {
			$('#patient_results').html(html);
		});
	}
</script>

<?php
require_once \ExternalModules\ExternalModules::getProjectFooterPath();