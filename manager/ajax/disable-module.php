<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

$module = $_POST['module'];

if (empty($module)) {
	echo 'You must specify a module to disable';
	return;
}

if (isset($_GET["pid"])) {
	ExternalModules::setProjectSetting($module, $_GET['pid'], ExternalModules::KEY_ENABLED, false);
} else {
	ExternalModules::disable($module);
}

echo 'success';
