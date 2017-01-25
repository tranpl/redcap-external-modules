<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

if (isset($_GET['pid'])) {
	 ExternalModules::setProjectSetting($_POST['prefix'], $_GET['pid'], ExternalModules::KEY_ENABLED, true);
}
else {
	 ExternalModules::enable($_POST['prefix'], $_POST['version']);
}

echo 'success';
