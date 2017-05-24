<?php

require_once '../../classes/ExternalModules.php';
require_once APP_PATH_DOCROOT.'Classes/Files.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];
$version = $_GET['moduleDirectoryVersion'];

if(empty($pid) && !ExternalModules\ExternalModules::hasSystemSettingsSavePermission($moduleDirectoryPrefix)){
	header('Content-type: application/json');
	echo json_encode(array(
		'status' => 'You do not have permission to save system settings!'
	));
}

$config = ExternalModules\ExternalModules::getConfig($moduleDirectoryPrefix, $version, $pid);
$files = array();
foreach(['system-settings', 'project-settings'] as $settingsKey){
	 foreach($config[$settingsKey] as $row) {
         if($row['type'] && ($row['type'] == "sub_settings") && $row['sub_settings']){
             foreach ($row['sub_settings'] as $r){
                 if ($r['type'] && ($r['type'] == "file")) {
                     $files[] = $r['key'];
                 }
             }
         }else if ($row['type'] && ($row['type'] == "file")) {
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

if(empty($pid)) {
	$pidPossiblyWithNullValue = null;
} else {
	$pidPossiblyWithNullValue = $pid;
}

$edoc = null;
$myfiles = array();
foreach($_FILES as $key=>$value){
	$myfiles[] = $key;
	if (isExternalModuleFile($key, $files) && $value) {
		# use REDCap's uploadFile
		$edoc = Files::uploadFile($_FILES[$key]);

		if ($edoc) {
			if(!empty($pid) && !ExternalModules\ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)) {
				header('Content-type: application/json');
				echo json_encode(array(
					'status' => "You don't have permission to save the following project setting: $key!"
				));
			}
			ExternalModules\ExternalModules::setFileSetting($moduleDirectoryPrefix, $pidPossiblyWithNullValue, $key, $edoc);
		} else {
			throw new Exception("You could not save a file properly.");
			header('Content-type: application/json');
			echo json_encode(array(
				'status' => "You could not save a file properly."
			));
		}
	 }
}

if ($edoc) {
	header('Content-type: application/json');
	echo json_encode(array(
		'status' => 'success'
	));
} else {
	header('Content-type: application/json');
	echo json_encode(array(
		'myfiles' => json_encode($myfiles),
		'_POST' => json_encode($_POST),
		'status' => 'You could not find a file.'
	));
}
