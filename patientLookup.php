<?php

require_once('base.php');

/* @var $module RedcapAfrica\OrganRegistryModule\OrganRegistryModule */
require_once \ExternalModules\ExternalModules::getProjectHeaderPath();

if($_GET['debug_logging'] == "on") {
    $_SESSION['debug_logging'] = "on";
}
if($_GET['debug_logging'] == "off") {
    $_SESSION['debug_logging'] = "off";
}

$vars['styles'][] = $module->getUrl("css/style.css");
$vars['styles'][] = $module->getUrl("css/datatables.min.css");
//$vars['scripts'][] = $module->getUrl("js/jquery-1.12.4.min.js");
$vars['scripts'][] = $module->getUrl("js/datatables.min.js");

$lookupFields = $module->getProjectSetting("search-fields");
$repeatingFields = $module->getProjectSetting("repeating-field");
$metadata = $module->getMetadata($project);

$lookupDetails = [];
foreach($lookupFields as $fieldKey => $thisField) {
    $lookupDetails[$thisField]['label'] = $metadata[$thisField]["field_label"];
    $options = [];
    if($metadata[$thisField]["field_type"] == "checkbox") {
        $options = $module->getChoiceLabels($thisField);
        $lookupDetails[$thisField]['type'] = 'checkbox';
    }
    else if(in_array($metadata[$thisField]["field_type"],["radio","dropdown","yesno","truefalse","sql"])) {
        $lookupDetails[$thisField]['type'] = 'select';
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
    }
    else {
        $lookupDetails[$thisField]['type'] = 'text';
    }
    $lookupDetails[$thisField]['options'] = $options;
    
    if($repeatingFields[$fieldKey]) {
        $lookupDetails[$thisField]['repeating'] = true;
    }
}


$vars['lookupDetails'] = $lookupDetails;
$vars['searchHistoryLink'] = $module->getUrl("searchHistoryLookup.php");
$vars['patientSearchLink'] = $module->getUrl("patientSearchAjax.php");
echo $twig->render('patientLookup.twig', $vars);

require_once \ExternalModules\ExternalModules::getProjectFooterPath();