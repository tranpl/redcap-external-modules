<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

$return_data['message'] = "success";

if (isset($_GET['pid'])) {
	 ExternalModules::enableForProject($_POST['prefix'], $_POST['version'], $_GET['pid']);
}
else {
    $config = ExternalModules::getConfig($_POST['prefix'], $_POST['version']);
    $return_data['error_message'] = "";
    if(empty($config['description'])){
        $return_data['error_message'] .= "The module named  ".$config['name']." is missing a description. Fill in the config.json to ENABLE it.<br/>";
    }

    if(empty($config['authors'])){
        $return_data['error_message'] .= "The module named  ".$config['name']." is missing its authors. Fill in the config.json to ENABLE it.<br/>";
    }else{
        $error_email = true;
        foreach ($config['authors'] as $author){
            if(!empty( $author['email'])){
                $error_email = false;
                break;
            }
        }

        if($error_email){
            $return_data['error_message'] .= "The module named  ".$config['name']." needs at least one email inside the authors portion of the configuration.  Please fill an email for at least one author in the config.json.<br/>";
        }
    }

    if(empty($return_data['error_message'])) {
		$exception = ExternalModules::enableAndCatchExceptions($_POST['prefix'], $_POST['version']);
		if($exception){
			$return_data['error_message'] = $exception->getMessage();
		}
    }
}

// Log this event
$logText = "Enable external module \"{$_POST['prefix']}_{$_POST['version']}\" for " . (!empty($_GET['pid']) ? "project" : "system");
\REDCap::logEvent($logText, "", "", null, null, $_GET['pid']);

echo json_encode($return_data);
