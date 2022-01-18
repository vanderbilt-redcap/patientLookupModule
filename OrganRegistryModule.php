<?php
namespace RedcapAfrica\OrganRegistryModule;

class OrganRegistryModule extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
        include_once(__DIR__."/vendor/autoload.php");
	}

	public function getRecordSurveyURL($recordId)
    {
        global $redcap_version;
        
        return rtrim(APP_PATH_WEBROOT_FULL,"/")."/redcap_v".$redcap_version."/DataEntry/record_home.php?pid=".$this->getProjectId()."&id=".$recordId;
    }
    
    public function getDisplayHeaders($project) {
        $headerFields = [];
        $displayFields = $this->getProjectSetting("display-fields");
        $metadata = $this->getMetadata($project);
        foreach($displayFields as $thisField) {
            if (!array_key_exists($thisField, $headerFields)) {
                $headerFields[$thisField] = $metadata[$thisField]["field_label"];
            }
        }
        
        return $headerFields;
    }
    
    public function getDisplayDataforRecord($project, $recordId, $filterRecords = true) {
        $displayFields = $this->getProjectSetting("display-fields");
    
        $displayString = "";
        $recordDetails = $this->getData($project,$recordId);
    
        $tempDetails = $recordDetails[$recordId];
        unset($tempDetails['repeat_instances']);
        $tempDetails = reset($tempDetails);
        if ($filterRecords && ($tempDetails['rec_listing_status'] == 7)) {
            return false;
        }
    
        $displayData = [];
        foreach($recordDetails as $record => $eventDetails) {
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
    
        $metadata = $this->getMetadata($project);
    
        foreach($displayFields as $thisField) {
            if($thisField == "") continue;
        
            if($repeatingFields[$thisField] == 1) {
                $displayData[$thisField] = end($displayData[$thisField]);
            }
        
            if($metadata[$thisField]["field_type"] == "checkbox") {
                $tempString = "";
            
                $options = $this->getChoiceLabels($thisField);
            
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
                        $options = $this->getChoiceLabels($thisField);
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
            $recordOutputs['fields'][$thisField] = $displayData[$thisField];
//		$displayString .= $metadata[$thisField]["field_label"]." : ".$displayData[$thisField]."<br />";
        }
        global $redcap_version;
        ## Add button to edit record to results
        $recordOutputs['url'] = $this->getRecordSurveyURL($recordId);
        
        return $recordOutputs;
    }
	
}