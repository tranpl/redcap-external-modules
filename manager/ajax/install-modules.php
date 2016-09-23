<?php
namespace ExternalModules;

die('Module installation has been disabled until we create configuration steps for a writable installed modules folder.');

require_once '../../classes/Modules.php';

foreach ($_POST['modules'] as $module) {
	ExternalModules::install($module);
}

echo 'success';
