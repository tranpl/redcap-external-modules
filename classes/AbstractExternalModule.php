<?php
namespace ExternalModules;

use Exception;

class AbstractExternalModule
{
	function selectData($some, $params)
	{
		self::checkPermissions(__FUNCTION__);

		return 'this could be some data from the database';
	}

	function updateData($some, $params)
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	function deleteData($some, $params)
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	function updateUserPermissions($some, $params)
	{
		self::checkPermissions(__FUNCTION__);

		# TODO - Implement this!

		return true;
	}

	function checkPermissions($methodName)
	{
		# Convert from camel to snake case.
		# Taken from the second solution here: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
		$permissionName = ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $methodName)), '_');

		if (!$this->hasPermission($permissionName)) {
			throw new Exception("This module must request the \"$permissionName\" permission in order to call the $methodName() method.");
		}
	}

	function hasPermission($permissionName)
	{
		return in_array($permissionName, $this->getConfig()->permissions);
	}

	private function getConfig()
	{
		if(!$this->config){
			$moduleName = str_replace('ExternalModules\\', '', get_class($this));
			$moduleName = str_replace('ExternalModule', '', $moduleName);
			$modulePath = ExternalModules::$INSTALLED_MODULES_PATH . $moduleName;
			$this->config = json_decode(file_get_contents("$modulePath/config.json"));
		}

		return $this->config;
	}
}