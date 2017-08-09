<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

$module = $_POST['module'];

if (empty($module)) {
	echo 'You must specify a module to disable';
	return;
}

$version = ExternalModules::getModuleVersionByPrefix($module);

if (isset($_GET["pid"])) {
	ExternalModules::setProjectSetting($module, $_GET['pid'], ExternalModules::KEY_ENABLED, false);
} else {
	ExternalModules::disable($module);
}

// Log this event
$logText = "Disable external module \"{$module}_{$version}\" for " . (!empty($_GET['pid']) ? "project" : "system");
\REDCap::logEvent($logText);

echo 'success';
