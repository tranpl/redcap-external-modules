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
        $return_data['error_message'] .= "Module  ".$config['name']." is missing a description. Fill in the config.json to ENABLE it.<br/>";
    }

    if(empty($config['authors'])){
        $return_data['error_message'] .= "Module  ".$config['name']." is missing its authors. Fill in the config.json to ENABLE it.<br/>";
    }

    if(empty($return_data['error_message'])) {
        ExternalModules::enable($_POST['prefix'], $_POST['version']);
    }
}

echo json_encode($return_data);
