<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

ExternalModules::enable($_POST['prefix'], $_POST['version']);

echo 'success';
