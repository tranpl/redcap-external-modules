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

$systemValue = ExternalModules\ExternalModules::getSystemSetting($prefix, $key);
if ($systemValue == $edoc) {
	# set the setting as ""
	ExternalModules\ExternalModules::setProjectSetting($prefix, $pid, $key, "");
	$type = "Set $edoc to ''";
} else {
	# delete the edoc
	$num_rows = 0;
	if (($edoc) && (is_numeric($edoc))) {
		$sql = "UPDATE `redcap_edocs_metadata`
				SET `delete_date` = NOW()
				WHERE doc_id = $edoc;";
		$q = db_query($sql);
		$num_rows = db_num_rows($q);
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
