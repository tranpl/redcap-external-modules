<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

if (isset($_GET['pid'])) {
	 ExternalModules::enableForProject($_POST['prefix'], $_POST['version'], $_GET['pid']);
}
else {
	 ExternalModules::enable($_POST['prefix'], $_POST['version']);
}

echo 'success';
