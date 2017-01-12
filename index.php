<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/classes/ExternalModules.php';

$id = $_GET['id'];
$page = $_GET['page'];
$pid = @$_GET['pid'];

$prefix = ExternalModules::getPrefixForID($id);
if(empty($prefix)){
	die("A module with id $id could not be found!");
}

$version = ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_VERSION);
if(empty($version)){
	die("The requested module is currently disabled system-wide.");
}

if($pid != null){
	$enabled = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED);
	if(!$enabled){
		die("The requested module is currently disabled on this project.");
	}
}

$pagePath = ExternalModules::$MODULES_PATH . ExternalModules::getModuleDirectoryName($prefix, $version) . "/$page.php";
if(!file_exists($pagePath)){
	die("The specified page does not exist for this module.");
}

require_once $pagePath;
