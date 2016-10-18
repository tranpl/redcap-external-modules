<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

foreach ($_POST['modules'] as $module) {
	ExternalModules::enable($module);
}

echo 'success';
