<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/classes/ExternalModules.php';

use Exception;

$id = $_GET['id'];
$page = $_GET['page'];
$pid = @$_GET['pid'];

$prefix = ExternalModules::getPrefixForID($id);
if(empty($prefix)){
	throw new Exception("A module with id $id could not be found!");
}

$version = ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_VERSION);
if(empty($version)){
	throw new Exception("The requested module is currently disabled systemwide.");
}

if($pid != null){
	$enabled = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED);
	if(!$enabled){
		throw new Exception("The requested module is currently disabled on this project.");
	}
}

if (preg_match("/^https:\/\//", $page) || preg_match("/^http:\/\//", $page)) {
	header( 'Location: '.$page ) ;
}

$pagePath = ExternalModules::getModuleDirectoryPath($prefix, $version) . "/$page.php";
if(!file_exists($pagePath)){
	throw new Exception("The specified page does not exist for this module. $pagePath");
}

require_once $pagePath;
