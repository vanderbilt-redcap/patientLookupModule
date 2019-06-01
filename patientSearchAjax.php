<?php
$project = $_GET['pid'];

if($project == "") {
	throw new Exception("No project selected");
}

/* @var $module RedcapAfrica\OrganRegistryModule\OrganRegistryModule */
$lookupFields = $module->getProjectSetting("matching-fields");
$displayFields = $module->getProjectSetting("display-fields");
$logicTypes = $module->getProjectSetting("matching-logic");

$searchValue = strtolower($_POST['searchValue']);

if(count($lookupFields) == 0 && $searchValue == "") die();

$sql = "SELECT d.record,d.field_name,d.value
		FROM redcap_data d
		WHERE d.field_name IN (";

foreach($lookupFields as $fieldKey => $fieldName) {
	$sql .= ($fieldKey == 0 ? "" : ", ")."'".$fieldName."'";
}

$sql .= ")";

$q = db_query($sql);

$recordIds = [];
while($row = db_fetch_assoc($q)) {
	$recordIds[] = $row["record"];
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
		$displayString .= $metadata[$thisField]["field_label"]." : ".$displayData[$thisField]."<br />";
	}

	## Add button to edit record to results
	$displayString .= "<button onclick='window.location.href=app_path_webroot_full+app_path_webroot+\"DataEntry/record_home.php?pid=\"+pid+\"&id=".$recordId."'>Go to Record</button><Br />";

	echo "<div style='border:solid black 1px; width:200px' >$displayString</div>";
}

if(count($recordIds) == 0 ) {
	echo "<button onclick='alert(app_path_webroot_full+app_path_webroot+page+\"?pid=\"+pid+\"&id=672&auto=1&arm=\"+($(\"#arm_name_newid\").length))' >Add New Record</button>";
}