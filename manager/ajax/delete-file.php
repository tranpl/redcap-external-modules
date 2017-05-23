<?php
require_once '../../classes/ExternalModules.php';
require_once APP_PATH_DOCROOT.'Classes/Files.php';

if(empty($pid) && !ExternalModules\ExternalModules::hasSystemSettingsSavePermission($moduleDirectoryPrefix)){
//	die("You don't have permission to save system settings!");
	header('Content-type: application/json');
	echo json_encode(array(
			'status' => 'You do not have permission to save system settings!'
	));
}

$pid = @$_GET['pid'];
$edoc = $_POST['edoc'];
$key = $_POST['key'];
$prefix = $_POST['moduleDirectoryPrefix'];

# Three states for external modules database
# 1. no entry: The edoc is the system default value; do not delete the system default
# 2. value = "": The edoc is empty file; no file is specified
# 3. value = ##: Edoc is uploaded in the edocs database under the numeric id

# Check if you are deleting the system default value
$systemValue = ExternalModules\ExternalModules::getSystemSetting($prefix, $key);
if ($systemValue == $edoc) {
	# set the setting as "" - this denotes an empty file space
	# if you deleted the actual database entry, then you would go to the system default value
	ExternalModules\ExternalModules::setProjectSetting($prefix, $pid, $key, "");
	$type = "Set $edoc to ''";
} else {
	# delete the edoc
	if (($edoc) && (is_numeric($edoc))) {
		ExternalModules\ExternalModules::deleteEDoc($edoc);
		ExternalModules\ExternalModules::removeFileSetting($prefix, $pid, $key);
		$type = "Delete $edoc";
	}
}

header('Content-type: application/json');
echo json_encode(array(
	'type' => $type,
        'status' => 'success'
));

?>
