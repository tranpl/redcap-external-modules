<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

$module = $_POST['module'];

if (empty($module)) {
	echo 'You must specify a module to disable';
	return;
}

ExternalModules::disable($module);

echo 'success';