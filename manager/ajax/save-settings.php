<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];
$version = $_GET['moduleDirectoryVersion'];

if(empty($pid) && !ExternalModules::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
	die("You don't have permission to save global settings!");
}

# for screening out files below
$config = ExternalModules::getConfig($moduleDirectoryPrefix, $version, $pid);
$files = array();
foreach(['global-settings', 'project-settings'] as $settingsKey){
	foreach($config[$settingsKey] as $row) {
		 if ($row['type'] && ($row['type'] == "file")) {
			  $files[] = $row['key'];
		 }
	}
}

# returns boolean
function isExternalModuleFile($key, $fileKeys) {
	if (in_array($key, $fileKeys)) {
		 return true;
	}
	foreach ($fileKeys as $fileKey) {
		 if (preg_match('/^'.$fileKey.'____\d+$/', $key)) {
			  return true;
		 }
	}
	return false;
}

foreach($_POST as $key=>$value){
	# files are stored in a separate $.ajax call
	# numeric value signifies a file present
	# empty strings signify non-existent files (globalValues or empty)
	if (!isExternalModuleFile($key, $files) || !is_numeric($value)) { 
		if($value == '') {
			$value = null;
		}

		if(empty($pid)){
			ExternalModules::setGlobalSetting($moduleDirectoryPrefix, $key, $value);
		} else {
			if(!ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)) {
				die("You don't have permission to save the following project setting: $key");
			}
	
			ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
		}
	}
}

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success'
));
