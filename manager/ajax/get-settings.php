<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

header('Content-type: application/json');
if (isset($_POST['pid']) && $_POST['pid']) {
	echo json_encode(array(
		'status' => 'success',
		'settings' => ExternalModules::getProjectSettingsAsArray($_POST['moduleDirectoryPrefix'], @$_POST['pid'])
	));
} else {
	echo json_encode(array(
		'status' => 'success',
		'settings' => ExternalModules::getSystemSettingsAsArray($_POST['moduleDirectoryPrefix'])
	));
}
