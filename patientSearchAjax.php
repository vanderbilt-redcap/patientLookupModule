<?php
$project = $_GET['pid'];

if($project == "") {
	throw new Exception("No project selected");
}

/* @var $module RedcapAfrica\OrganRegistryModule\OrganRegistryModule */
$searchFields = $module->getProjectSetting("search-fields");
$lookupFields = $module->getProjectSetting("matching-fields");
$logicTypes = $module->getProjectSetting("matching-logic");
$displayFields = $module->getProjectSetting("display-fields");

if(count($lookupFields) == 0 || count($searchFields) == 0) die();

## Get submitted form data

$searchData = [];
foreach($searchFields as $fieldKey => $thisField) {
	if($thisField == "") continue;

	if((is_array($_POST[$thisField]) && count($thisField) > 0) || (!is_array($_POST[$thisField] && $_POST[$thisField] != ""))) {
		$searchData[$fieldKey] = $_POST[$thisField];
	}
}

if($_SESSION['debug_logging'] == "on") {
	echo "Search Data:<br />";
	echo "<pre>";var_dump($searchData);echo "</pre>";
}

$sql = "SELECT d.record,d.field_name,d.value,d.instance
		FROM redcap_data d
		WHERE d.field_name IN (";

foreach($lookupFields as $fieldKey => $fieldName) {
	if($fieldName == "") continue;

	$sql .= ($fieldKey == 0 ? "" : ", ")."'".$fieldName."'";
}

$sql .= ") AND d.project_id = '".db_escape($project)."'";

$q = db_query($sql);

if($e = db_error()) {
	var_dump($e);
	die();
}

$recordData = [];
while($row = db_fetch_assoc($q)) {
	if(array_key_exists($row["field_name"],$recordData[$row["record"]])) {
		if(!is_array($recordData[$row["record"]][$row["field_name"]])) {
			$recordData[$row["record"]][$row["field_name"]] = [$recordData[$row["record"]][$row["field_name"]]];
		}
		$recordData[$row["record"]][$row["field_name"]][] = $row["value"];
	}
	else {
		$recordData[$row["record"]][$row["field_name"]] = $row["value"];
	}
}

if($_SESSION['debug_logging'] == "on") {
	echo "Lookup Field Data:<br />";
	echo "<pre>";var_dump($recordData);echo "</pre>";
}


## Now need to do the matching here
$recordIds = [];

foreach($recordData as $recordId => $recordDetails) {
	$recordMatches = true;

	## Sometimes lots of options show up for a given antibody, so repeating instead of single

	## Loop through this record's searchable fields and compare to the form data submitted
	foreach($searchData as $fieldKey => $searchValue) {
		foreach($searchValue as $actualValue) {
			if($actualValue == "") continue;

			$lookupField = $lookupFields[$fieldKey];
			$logicType = $logicTypes[$fieldKey];

			if($lookupField == "") continue;

			## All search fields need to have a value for the record or else skip
			if(!array_key_exists($lookupField,$recordDetails)) {
				$recordMatches = false;
				break;
			}

			if(is_array($recordDetails[$lookupField])) {
				$fieldMatches = in_array($actualValue,$recordDetails[$lookupField]);
			}
			else {
				$fieldMatches = ($actualValue == $recordDetails[$lookupField]);
			}

			if(($logicType == "not" && $fieldMatches) || ($logicType == "equals" && !$fieldMatches)) {
				$recordMatches = false;
				break;
			}
		}
	}

	if($recordMatches) {
		$recordIds[] = $recordId;
	}
}

$repeatingFields = [];
$recordCount = 0;

foreach($recordIds as $recordId) {
	$recordCount++;
	## Don't display more than 10 records
	if($recordCount > 10) {
		break;
	}
	$displayString = "";
	$recordDetails = $module->getData($project,$recordId);

	if($_SESSION['debug_logging'] == "on" && $recordCount == 1) {
		echo "Get Data Results:<br />";
		echo "<pre>";var_dump($recordDetails);echo "</pre>";
	}

	$displayData = [];
	foreach($recordDetails as $recordId => $eventDetails) {
		if(array_key_exists("repeat_instances",$eventDetails)) {
			foreach($eventDetails["repeat_instances"] as $eventId => $details) {
				foreach($details as $formName => $instances) {
					foreach($instances as $instanceId => $fieldDetails) {
						foreach($displayFields as $thisField) {
							## This is a repeating field
							if(!empty($fieldDetails[$thisField])) {
								if(!array_key_exists($thisField,$displayData)) {
									$repeatingFields[$thisField] = 1;
									$displayData[$thisField] = [];
								}
								$displayData[$thisField][$instanceId] = $fieldDetails[$thisField];
							}
						}
					}
				}
			}
		}
		foreach($eventDetails as $eventId => $details) {
			foreach($displayFields as $thisField) {
				if(array_key_exists($thisField,$displayData)) {
					continue;
				}
				if($details[$thisField] != "") {
					$displayData[$thisField] = $details[$thisField];
				}
			}
		}
	}

	$metadata = $module->getMetadata($project);

	foreach($displayFields as $thisField) {
		if($thisField == "") continue;

		if($repeatingFields[$thisField] == 1) {
			$displayData[$thisField] = end($displayData[$thisField]);
		}

		if($metadata[$thisField]["field_type"] == "checkbox") {
			$tempString = "";

			$options = $module->getChoiceLabels($thisField);

			foreach($options as $value => $label) {
				if(in_array($value,$displayData[$thisField])) {
					$tempString .= ($tempString != "" ? ", " : "").$label;
				}
			}

			$displayData[$thisField] = $tempString;
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

			foreach($options as $value => $label) {
				if($value == $displayData[$thisField]) {
					$displayData[$thisField] = $label;
					break;
				}
			}
		}

		$displayString .= $metadata[$thisField]["field_label"]." : ".$displayData[$thisField]."<br />";
	}
	global $redcap_version;
	## Add button to edit record to results
	$displayString .= "<button onclick='window.location.href=\"".rtrim(APP_PATH_WEBROOT_FULL,"/")."/redcap_v".$redcap_version."/DataEntry/record_home.php?pid=".$project."&id=".$recordId."\";return false;'>Go to Record</button><Br />";

	echo "<div style='border:solid black 1px; width:200px' >$displayString</div>";
}

if(count($recordIds) == 0 ) {
	echo "No matching records found";
}