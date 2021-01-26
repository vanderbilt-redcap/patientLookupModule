<?php

require_once('base.php');
$metadata = $module->getMetadata($project);
if (array_key_exists('log_id', $_POST)) {
    $vars     = [];
    /** @var mysqli_result $result */
    $result = $module->queryLogs("select searchResults
                                 where log_id = ?", $_POST['log_id']);
    
    if ($result->num_rows != 1) {
        echo "Incorrect number of entries found";
        die();
    }
    $row           = $result->fetch_assoc();
    $recordOutputs = [];
    $headerFields  = [];
    $results       = json_decode($row['searchResults'], true);
    foreach ($results as $recordId => $fields) {
        $recordOutputs[$recordId]['fields'] = $fields;
        $recordOutputs[$recordId]['url']    = $module->getRecordSurveyURL($recordId);
        foreach ($fields as $field => $value) {
            if (!array_key_exists($field, $headerFields)) {
                $headerFields[$field] = trim($metadata[$field]["field_label"]);
            }
        }
    }

//if(count($recordIds) == 0 ) {
//    echo "No matching records found";
//    die();
//}
    $vars['records'] = $recordOutputs;
    $vars['headers'] = $headerFields;
    
    echo $twig->render('patientSearch.twig', $vars);
} else {
    
    /** @var mysqli_result $result */
    $result = $module->queryLogs("select log_id, user, timestamp, resultCount, searchParams
                                 where message = 'searchHistory'");
    
    $historyEntries = [];
    $searchHeaders = ['Timestamp', 'User', 'Number of Results'];
    $initialHeaderCount = count($searchHeaders);
    while ($row = $result->fetch_assoc()) {
        $historyEntries[$row['log_id']]['timestamp'] = $row['timestamp'];
        $historyEntries[$row['log_id']]['user'] = $row['user'];
        $historyEntries[$row['log_id']]['resultCount'] = $row['resultCount'];
        $historyEntries[$row['log_id']]['params'] = json_decode($row['searchParams'], true);
        $historyEntries[$row['log_id']]['paramsEncoded'] = $row['searchParams'];
        foreach ($historyEntries[$row['log_id']]['params'] as $field => $value) {
            if (in_array($metadata[$field]['field_type'], ['dropdown', 'checkbox', 'radio'])) {
                $options = $module->getChoiceLabels($field);
                if (is_array($value)) {
                    foreach ($value as $key => $item) {
                        $historyEntries[$row['log_id']]['params'][$field][$key] = $options[$item];
                    }
                } else {
                    $historyEntries[$row['log_id']]['params'][$field] = $options[$value];
                }
            }
        }
        if ((count($historyEntries[$row['log_id']]['params']) + $initialHeaderCount) > count($searchHeaders)) {
            foreach ($historyEntries[$row['log_id']]['params'] as $field => $value) {
                $searchHeaders[] = $metadata[$field]["field_label"];
            }
        }
    }
    
    krsort($historyEntries);
    $vars['historyEntries'] = $historyEntries;
    $vars['searchHeaders'] = $searchHeaders;
    $vars['searchHistoryLookupLink'] = $module->getUrl("searchHistoryLookup.php");
    $vars['patientSearchLink'] = $module->getUrl("patientSearchAjax.php");
    echo $twig->render('searchHistory.twig', $vars);
}