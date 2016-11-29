<?php
namespace ExternalModules;

use Exception;

class AbstractExternalModule
{
	private $CONFIG;

	function __construct()
	{
		self::checkSettings();
	}

	private function checkSettings()
	{
		$config = self::getConfig();

		foreach($config['global-settings'] as $name=>$details){
			self::checkSettingName($name);
		}

		foreach($config['project-settings'] as $name=>$details){
			self::checkSettingName($name);
		}
	}

	private function checkSettingName($name)
	{
		if(strpos($name, '"') !== FALSE || strpos($name, "'") !== FALSE){
			// Disallow quote characters in setting names at module enable/instantiation time so that
			// setting names need can be included in html attributes without worrying about escaping.
			throw new Exception("The " . self::getModuleDirectoryName() . " module contains a setting named \"$name\" that contains quote characters which are not allowed in setting names.");
		}
	}

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

		throw new Exception('Not yet implemented!');
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
		return in_array($permissionName, $this->getConfig()['permissions']);
	}

	function getConfig()
	{
		if(!isset($this->CONFIG)){
			$this->CONFIG = ExternalModules::getConfig(self::getModuleDirectoryName());
		}

		return $this->CONFIG;
	}

	function getModuleDirectoryName()
	{
		$reflector = new \ReflectionClass(get_class($this));
		return basename(dirname($reflector->getFileName()));
	}
}