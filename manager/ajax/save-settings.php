<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryName = $_GET['moduleDirectoryName'];

if(empty($pid) && !ExternalModules::hasGlobalSettingsSavePermission($moduleDirectoryName)){
	die("You don't have permission to save global settings!");
}

foreach($_POST as $key=>$value){
	if($value == ''){
		$value = null;
	}

	if(empty($pid)){
		ExternalModules::setGlobalSetting($moduleDirectoryName, $key, $value);
	}
	else{
		// The following call currently requires the module instance to be created and the config to be parsed repeatedly for each setting.
		// Ideally we should cache module instances to prevent this, but realistically this a minor performance optimization that is not currently justifiable.
		if(!ExternalModules::hasProjectSettingSavePermission($moduleDirectoryName, $key)){
			die("You don't have permission to save the following project setting: $key");
		}

		ExternalModules::setProjectSetting($moduleDirectoryName, $pid, $key, $value);
	}
}

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success'
));