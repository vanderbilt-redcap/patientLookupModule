<?php
$project = $_GET['pid'];

if($project == "") {
	throw new Exception("No project selected");
}

/* @var $module RedcapAfrica\PatientLookupModule\PatientLookupModule */
$lookupFields = $module->getProjectSetting("matching-fields");

$searchValue = strtolower($_POST['searchValue']);

if(count($lookupFields) == 0 && $searchValue == "") die();

$sql = "SELECT d0.record
		FROM ";

foreach($lookupFields as $fieldKey => $fieldName) {
	$sql .= ($fieldKey == 0 ? "" : ",")."redcap_data d".$fieldKey;
}

$sql .= " WHERE ";

foreach($lookupFields as $fieldKey => $fieldName) {
	if($fieldKey < (count($lookupFields) - 1)) {
		$sql .= ($fieldKey == 0 ? "" : " AND ")."d".$fieldKey.".record = d".($fieldKey + 1).".record";
	}
}

$sql .= " AND ";

foreach($lookupFields as $fieldKey => $fieldName) {
	$sql .= ($fieldKey == 0 ? "" : " AND ")."(d".$fieldKey.".project_id = ".db_escape($project).
			" AND d".$fieldKey.".field_name = '".$fieldName."')";
}
$sql .= " AND (";

foreach($lookupFields as $fieldKey => $fieldName) {
	$sql .= ($fieldKey == 0 ? "" : " OR ")."LOWER(d".$fieldKey.".value) LIKE '%".$searchValue."%'";
}

$sql .= ")";

$q = db_query($sql);

$recordIds = [];
while($row = db_fetch_assoc($q)) {
	$recordIds[] = $row["record"];
}

var_dump($recordIds);