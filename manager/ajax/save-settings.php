<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

ExternalModules::saveSettingsFromPost($moduleDirectoryPrefix, $pid);

// Log this event
$version = ExternalModules::getModuleVersionByPrefix($moduleDirectoryPrefix);
$logText = "Modify configuration for external module \"{$moduleDirectoryPrefix}_{$version}\" for " . (!empty($_GET['pid']) ? "project" : "system");
\REDCap::logEvent($logText);

header('Content-type: application/json');
echo json_encode(array('status' => 'success'));
