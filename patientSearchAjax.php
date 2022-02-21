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
		## Support non-array search fields
		if(!is_array($searchValue)) {
			$searchValue = [$searchValue];
		}
		
		foreach($searchValue as $actualValue) {
			if($actualValue == "") {
				$skippedFields[$lookupFields[$fieldKey]] = 1;
				continue;
			}

			$lookupField = $lookupFields[$fieldKey];
			$logicType = $logicTypes[$fieldKey];

			if($lookupField == "") continue;
            sort($recordDetails[$lookupField]);
            
			## All search fields need to have a value for the record or else skip
			if(!array_key_exists($lookupField,$recordDetails)) {
				$recordMatches = false;
                ## Log the records that were skipped and why
                $skippedRecordMessages['skipped_record_'.$recordId] = "Skipping record $recordId as has blank values for $lookupField";
                break;
			}

			if(is_array($recordDetails[$lookupField])) {
				$fieldMatches = in_array($actualValue,$recordDetails[$lookupField]);
			}
			else {
				$fieldMatches = ($actualValue === $recordDetails[$lookupField]);
			}
			
			if(($logicType == "not" && $fieldMatches) || ($logicType == "equals" && !$fieldMatches)) {
//                $skippedRecordMessages[] = "Excluding record $recordId because $lookupField has $logicType $actualValue - ".var_export($recordDetails[$lookupField],true)."<br />";
                ## Log the records that were skipped and why
//                $fieldMessage = "";
//                foreach ($recordDetails[$lookupField] as $value) {
//                    $fieldMessage .= $value . "\n";
//                }
                $fieldMessage = '[' .implode(", ", $recordDetails[$lookupField]) . ']';
                if ($logicType == 'not') {
                    $skippedRecordMessages['skipped_record_'.$recordId] = "Excluding record $recordId because $actualValue was found in $lookupField - \n$fieldMessage";
                } else if ($logicType == 'equals') {
                    $skippedRecordMessages['skipped_record_'.$recordId] = "Excluding record $recordId because $actualValue was not found in $lookupField - \n$fieldMessage";
                }
                
                $recordMatches = false;
				break;
			}
		}
	}

	if($recordMatches) {
		$recordIds[] = $recordId;
	}
}
$module->log("Ran Query", $skippedRecordMessages);

$repeatingFields = [];
$recordCount = 0;

$recordOutputs = [];
$headerFields = [];
foreach($recordIds as $recordId) {
    $recordOutputs[$recordId] = $module->getDisplayDataforRecord($project, $recordId);
}

$headerFields = $module->getDisplayHeaders($project);

//remove empty entries
$recordOutputs = array_filter($recordOutputs);

//$logParams['searchResults'] = json_encode(array_combine($recordIds, array_column($recordOutputs, 'fields')));
$logParams['searchResults'] = json_encode(array_keys($recordOutputs));
$logParams['resultCount'] = count($recordOutputs);
$module->log('searchHistory',$logParams);
if(count($recordIds) == 0 ) {
	echo "No matching records found";
	die();
}
$vars['records'] = $recordOutputs;
$vars['headers'] = $headerFields;

echo $twig->render('patientSearch.twig', $vars);