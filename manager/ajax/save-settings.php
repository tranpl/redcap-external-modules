<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

ExternalModules::saveSettingsFromPost($moduleDirectoryPrefix, $pid);

header('Content-type: application/json');
echo json_encode(array('status' => 'success'));
