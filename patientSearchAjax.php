<?php

require_once('base.php');


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
$user = $_SESSION['username'];
$savedQueryParams = array_combine($searchFields, $searchData);
$logParams = [
    'user' => $user,
    'searchParams' => json_encode(array_combine($searchFields, $searchData)),
];

if($_SESSION['debug_logging'] == "on") {
	echo "Search Data:<br />";
	echo "<pre>";var_dump($searchData);echo "</pre>";
}

$sql = "SELECT d.record,d.field_name,d.value,d.instance
		FROM redcap_data d
		WHERE d.field_name IN (";

foreach($lookupFields as $fieldKey => $fieldName) {
	if($fieldName == "") continue;

	$sql .= ($fieldKey == 0 ? "" : ", ")."'".db_escape($fieldName)."'";
}

$sql .= ") AND d.project_id = '".db_escape($project)."'";

$q = db_query($sql);

if($e = db_error()) {
	var_dump($e);
	die();
}

$moduleProject = $module->framework->getProject();

$checkboxFields = [];
$recordData = [];
while($row = db_fetch_assoc($q)) {
	$isCheckbox = $moduleProject->getField($row["field_name"])->getType() == "checkbox";
	if($isCheckbox) {
		$checkboxFields[$row["field_name"]] = 1;
	}
	
	if(array_key_exists($row["field_name"],$recordData[$row["record"]])) {
		if(!is_array($recordData[$row["record"]][$row["field_name"]])) {
			$recordData[$row["record"]][$row["field_name"]] = [$recordData[$row["record"]][$row["field_name"]]];
		}
		$recordData[$row["record"]][$row["field_name"]][] = $row["value"];
	}
	else {
		if($isCheckbox) {
			$recordData[$row["record"]][$row["field_name"]] = [$row["value"]];
		}
		else {
			$recordData[$row["record"]][$row["field_name"]] = $row["value"];
		}
	}
}

if($_SESSION['debug_logging'] == "on") {
	echo "<br /><pre>";
	var_dump($checkboxFields);
	echo "</pre><br />";
	echo "Lookup Field Data:<br />";
	echo "<pre>";var_dump($recordData);echo "</pre>";
}


## Now need to do the matching here
$recordIds = [];
$skippedFields = [];
$skippedRecordMessages = [];

foreach($recordData as $recordId => $recordDetails) {
	$recordMatches = true;

	## Sometimes lots of options show up for a given antibody, so repeating instead of single

	## Loop through this record's searchable fields and compare to the form data submitted
	foreach($searchData as $fieldKey => $searchValue) {
		foreach($searchValue as $actualValue) {
			if($actualValue == "") {
				$skippedFields[$lookupFields[$fieldKey]] = 1;
				continue;
			}

			$lookupField = $lookupFields[$fieldKey];
			$logicType = $logicTypes[$fieldKey];

			if($lookupField == "") continue;

			## All search fields need to have a value for the record or else skip
			if(!array_key_exists($lookupField,$recordDetails)) {
				$recordMatches = false;
				$skippedRecordMessages[] = "Skipping record $recordId as has blank values for $lookupField<br />";
				break;
			}

			if(is_array($recordDetails[$lookupField])) {
				$fieldMatches = in_array($actualValue,$recordDetails[$lookupField]);
			}
			else {
				$fieldMatches = ($actualValue == $recordDetails[$lookupField]);
			}

			if(($logicType == "not" && $fieldMatches) || ($logicType == "equals" && !$fieldMatches)) {
				$skippedRecordMessages[] = "Excluding record $recordId because $lookupField has $logicType $actualValue - ".var_export($recordDetails[$lookupField],true)."<br />";
				$recordMatches = false;
				break;
			}
		}
	}

	if($recordMatches) {
		$recordIds[] = $recordId;
	}
}

## Log the records that were skipped and why
$module->log("Ran Query",["skipped-messages" => implode("\n",$skippedRecordMessages)]);

$repeatingFields = [];
$recordCount = 0;

$recordOutputs = [];
$headerFields = [];
foreach($recordIds as $recordId) {
	$recordCount++;
	
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
		if (!array_key_exists($thisField, $headerFields)) {
            $headerFields[$thisField] = $metadata[$thisField]["field_label"];
        }
        $recordOutputs[$recordId]['fields'][$thisField] = $displayData[$thisField];
//		$displayString .= $metadata[$thisField]["field_label"]." : ".$displayData[$thisField]."<br />";
	}
	global $redcap_version;
	## Add button to edit record to results
    $recordOutputs[$recordId]['url'] = $module->getRecordSurveyURL($recordId);
}

$logParams['searchResults'] = json_encode(array_combine($recordIds, array_column($recordOutputs, 'fields')));
$logParams['resultCount'] = count($recordIds);
//Debug to remove old search history
//$module->removeLogs("message = 'searchHistory'");
$module->log('searchHistory',$logParams);
if(count($recordIds) == 0 ) {
	echo "No matching records found";
	die();
}
$vars['records'] = $recordOutputs;
$vars['headers'] = $headerFields;

echo $twig->render('patientSearch.twig', $vars);