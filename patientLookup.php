<?php

$project = $_GET['pid'];

if($project == "") {
	throw new Exception("No project selected");
}

require_once \ExternalModules\ExternalModules::getProjectHeaderPath();



require_once \ExternalModules\ExternalModules::getProjectFooterPath();