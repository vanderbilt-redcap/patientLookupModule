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
	if((is_array($_POST[$thisField]) && count($thisField) > 0) || (!is_array($_POST[$thisField] && $_POST[$thisField] != ""))) {
		$searchData[$fieldKey] = $_POST[$thisField];
	}
}

$sql = "SELECT d.record,d.field_name,d.value
		FROM redcap_data d
		WHERE d.field_name IN (";

foreach($lookupFields as $fieldKey => $fieldName) {
	$sql .= ($fieldKey == 0 ? "" : ", ")."'".$fieldName."'";
}

$sql .= ")";

$q = db_query($sql);

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

## Now need to do the matching here
$recordIds = [];

foreach($recordData as $recordId => $recordDetails) {
	$recordMatches = true;

	## Loop through this record's searchable fields and compare to the form data submitted
	foreach($searchData as $fieldKey => $searchValue) {
		if($searchValue == "") continue;

		$lookupField = $lookupFields[$fieldKey];
		$logicType = $logicTypes[$fieldKey];

		if(is_array($recordDetails[$lookupField])) {
			$fieldMatches = in_array($searchValue,$recordDetails[$lookupField]);
		}
		else {
			$fieldMatches = ($searchValue == $recordDetails[$lookupField]);
		}

		if(($logicType == "not" && $fieldMatches) || ($logicType == "equals" && !$fieldMatches)) {
			$recordMatches = false;
			break;
		}
	}

	if($recordMatches) {
		$recordIds[] = $recordId;
	}
}


foreach($recordIds as $recordId) {
	$displayString = "";
	$recordDetails = $module->getData($project,$recordId);

	$displayData = [];
	foreach($recordDetails as $recordId => $eventDetails) {
		foreach($eventDetails as $eventId => $details) {
			foreach($displayFields as $thisField) {
				if($details[$thisField] != "") {
					$displayData[$thisField] = $details[$thisField];
				}
			}
		}
	}

	$metadata = $module->getMetadata($project);

	foreach($displayFields as $thisField) {
		if($metadata[$thisField]["field_type"] == "checkbox") {
			$displayString = "";

			$options = $module->getChoiceLabels($thisField);

			foreach($options as $value => $label) {
				if(in_array($value,$displayData[$thisField])) {
					$displayString .= ($displayString != "" ? ", " : "").$label;
				}
			}

			$displayData[$thisField] = $displayString;
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

	## Add button to edit record to results
	$displayString .= "<button onclick='window.location.href=app_path_webroot_full+app_path_webroot+\"DataEntry/record_home.php?pid=\"+pid+\"&id=".$recordId."'>Go to Record</button><Br />";

	echo "<div style='border:solid black 1px; width:200px' >$displayString</div>";
}

if(count($recordIds) == 0 ) {
	echo "<button onclick='alert(app_path_webroot_full+app_path_webroot+page+\"?pid=\"+pid+\"&id=672&auto=1&arm=\"+($(\"#arm_name_newid\").length))' >Add New Record</button>";
}