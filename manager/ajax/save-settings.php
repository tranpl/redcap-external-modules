<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

if(empty($pid) && !ExternalModules::hasSystemSettingsSavePermission($moduleDirectoryPrefix)){
	die("You don't have permission to save system settings!");
}

# for screening out files below
$config = ExternalModules::getConfig($moduleDirectoryPrefix, $version, $pid);
$files = array();
foreach(['system-settings', 'project-settings'] as $settingsKey){
	foreach($config[$settingsKey] as $row) {
		if ($row['type'] && ($row['type'] == "file")) {
			$files[] = $row['key'];
		}
	}
}

$instances = array();   # for repeatable elements, you must save them after the original is saved
			# if not, the value is overwritten by a string/int/etc. - not a JSON

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

# store everything BUT files and multiple instances (after the first one)
foreach($_POST as $key=>$value){
	# files are stored in a separate $.ajax call
	# numeric value signifies a file present
	# empty strings signify non-existent files (systemValues or empty)
	if (!isExternalModuleFile($key, $files) || !is_numeric($value)) { 
		if($value == '') {
			$value = null;
		}

		if(empty($pid)){
			ExternalModules::setSystemSetting($moduleDirectoryPrefix, $key, $value);
		} else {
			ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
		}
		if (preg_match("/____/", $key)) {
			$instances[$key] = $value;
		} else if (empty($pid)) {
			ExternalModules::setSystemSetting($moduleDirectoryPrefix, $key, $value);
		} else {
			ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
		}
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

