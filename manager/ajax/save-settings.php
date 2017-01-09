<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

if(empty($pid) && !ExternalModules::hasSystemSettingsSavePermission($moduleDirectoryPrefix)){
	die("You don't have permission to save system settings!");
}

$keys = array();
foreach($_POST as $key=>$value){
	if($value == ''){
		$value = null;
	}

	if(empty($pid)){
		ExternalModules::setSystemSetting($moduleDirectoryPrefix, $key, $value);
	}
	else{
		if(!ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)){
			die("You don't have permission to save the following project setting: $key");
		}
		$keys[$key] = ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
	}
}

header('Content-type: application/json');
$rv = array(
	'status' => 'success',
        'keys' => $keys,
);
echo json_encode($rv);

