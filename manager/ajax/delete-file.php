<?php
require_once '../../classes/ExternalModules.php';
require_once APP_PATH_DOCROOT.'Classes/Files.php';

if(empty($pid) && !ExternalModules\ExternalModules::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
//	die("You don't have permission to save global settings!");
	header('Content-type: application/json');
	echo json_encode(array(
			'status' => 'You do not have permission to save global settings!'
	));
}

$pid = @$_GET['pid'];
$edoc = $_POST['edoc'];
$key = $_POST['key'];
$prefix = $_POST['moduleDirectoryPrefix'];

$num_rows = 0;
if (($edoc) && (is_numeric($edoc))) {
	$sql = "UPDATE `redcap_edocs_metadata`
			SET `delete_date` = NOW()
			WHERE doc_id = $edoc;";
	$q = db_query($sql);
	$num_rows = db_num_rows($q);
	ExternalModules\ExternalModules::removeFileSetting($prefix, $pid, $key);
}

header('Content-type: application/json');
echo json_encode(array(
        'status' => 'success'
));

?>
