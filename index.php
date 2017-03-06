<?php
namespace ExternalModules;

define('NOAUTH',true);
require_once dirname(__FILE__) . '/classes/ExternalModules.php';

$id = $_GET['id'];
$page = $_GET['page'];
$pid = @$_GET['pid'];

$prefix = ExternalModules::getPrefixForID($id);
if(empty($prefix)){
	die("A module with id $id could not be found!");
}

$version = ExternalModules::getGlobalSetting($prefix, ExternalModules::KEY_VERSION);
if(empty($version)){
	die("The requested module is currently disabled globally.");
}

$configuration = ExternalModules::getConfig($prefix, $version);
if($configuration["no-auth-links"][$page] != true) {
	ExternalModules::reauthorize();
}

if($pid != null){
	$enabled = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED);
	if(!$enabled){
		die("The requested module is currently disabled on this project.");
	}
}

if (preg_match("/^https:\/\//", $page) || preg_match("/^http:\/\//", $page)) {
	header( 'Location: '.$page ) ;
}

$pagePath = ExternalModules::$MODULES_PATH . ExternalModules::getModuleDirectoryName($prefix, $version) . "/$page.php";
if(!file_exists($pagePath)){
	die("The specified page does not exist for this module.");
}

require_once $pagePath;
