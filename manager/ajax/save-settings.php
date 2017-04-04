<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];
$version = $_GET['moduleDirectoryVersion'];

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

		if (preg_match("/____/", $key)) {
			$instances[$key] = $value;
		} else if (empty($pid)) {
		    $saved[$key] = $value;
			ExternalModules::setGlobalSetting($moduleDirectoryPrefix, $key, $value);
		} else {
            $saved[$key] = $value;
			ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
		}
	}
}

# instances must come after the initial settings have been saved
foreach($instances as $key => $value) {
	# allow the last match to be blank and not put into the database
	$last = true;
	$a = preg_split("/____/", $key);
	$shortKey = $a[0];
	$n = $a[1];

	# check if the current element is the last in the repeatable element
	foreach ($_POST as $key2 => $value2) {
		$a2 = preg_split("/____/", $key2);
		if (($a2[0] == $shortKey) && ($a2[1] > $n)) {
			$last = false;
			break;
		}
	}

	# do not put in database if last and value is blank
//	if (!$last || $value != "") {
		$data = ExternalModules::setInstance($moduleDirectoryPrefix, $pid, $shortKey, (int) $n, $value);
//	}
}
header('Content-type: application/json');
echo json_encode(array('status' => 'success'));
