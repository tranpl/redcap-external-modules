<?php
require_once '../../classes/ExternalModules.php';
require_once APP_PATH_DOCROOT.'Classes/Files.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];
$version = $_GET['moduleDirectoryVersion'];

if(empty($pid) && !ExternalModules::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
	die("You don't have permission to save global settings!");
}

$config = ExternalModules\ExternalModules::getConfig($moduleDirectoryPrefix, $version, $pid);
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

foreach($_FILES as $key=>$value){
	if (isExternalModuleFile($key, $files) && $value) { 
		# use REDCap's uploadFile
		$edoc = Files::uploadFile($_FILES[$key]);

		if ($edoc) {
			if(!empty($pid) && !ExternalModules\ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)) {
				die("You don't have permission to save the following project setting: $key");
			}
			ExternalModules\ExternalModules::setFileSetting($moduleDirectoryPrefix, $pid, $key, $edoc);
		} else {
			die("You could not save a file properly.");
		}
	 }
}

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success'
));
