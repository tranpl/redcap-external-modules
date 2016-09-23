<?php
/**
 * Created by PhpStorm.
 * User: mceverm
 * Date: 9/20/2016
 * Time: 9:02 AM
 */
namespace Modules;

require_once '../../classes/Modules.php';

$module = $_POST['module'];

if (empty($module)) {
	echo 'You must specify a module to remove';
	return;
}

// TODO - Remove this this mocked module is no longer listed.
if ($module == 'doggy-daycare') {
	die('success');
}

# TODO - need better security here (perhaps check for '..' or '/')
Modules::remove($module);

echo 'success';