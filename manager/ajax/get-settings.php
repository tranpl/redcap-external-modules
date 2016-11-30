<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

$moduleDirectoryName = $_POST['moduleDirectoryName'];

$pid = @$_POST['pid'];
if(isset($pid)){
	$result = ExternalModules::getProjectSettings($moduleDirectoryName, $pid);
}
else{
	$result = ExternalModules::getGlobalSettings($moduleDirectoryName);
}

$settings = array();
while($row = db_fetch_assoc($result)){
	$key = $row['key'];
	$value = $row['value'];

	$settings[$key] = $value;
}

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success',
	'settings' => $settings
));