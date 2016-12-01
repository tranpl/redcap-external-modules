<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success',
	'settings' => ExternalModules::getGlobalAndProjectSettingsAsArray($_POST['moduleDirectoryName'], @$_POST['pid'])
));