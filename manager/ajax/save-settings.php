<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

if(empty($pid) && !ExternalModules::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
	die("You don't have permission to save global settings!");
}

foreach($_POST as $key=>$value){
	if($value == ''){
		$value = null;
	}

	if(empty($pid)){
		ExternalModules::setGlobalSetting($moduleDirectoryPrefix, $key, $value);
	}
	else{
		if(!ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)){
			die("You don't have permission to save the following project setting: $key");
		}

		ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
	}
}

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success'
));