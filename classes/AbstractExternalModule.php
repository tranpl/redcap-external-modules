<?php
namespace ExternalModules;

use Exception;

class AbstractExternalModule
{
	protected $CONFIG;

	function __construct()
	{
		// Disallow illegal configuration options at module instantiation (and enable) time.
		self::checkSettings();
	}

	protected function checkSettings()
	{
		$config = $this->getConfig();
		$globalSettings = @$config['global-settings'];
		$projectSettings = @$config['project-settings'];

		if(isset($globalSettings)){
			foreach($globalSettings as $key=> $details){
				self::checkSettingKey($key);
			}
		}

		if(isset($projectSettings)){
			foreach($projectSettings as $key=> $details){
				self::checkSettingKey($key);
			}

			if(array_key_exists($key, $globalSettings)){
				throw new Exception("The \"" . self::getModuleDirectoryName() . "\" module defines the \"$key\" setting on both the global and project levels.  If you want to allow this setting to be overridden on the project level, please remove the project setting configuration and set 'allow-project-overrides' to true in the global setting configuration instead.");
			}
		}
	}

	private function checkSettingKey($key)
	{
		if(strpos($key, '"') !== FALSE || strpos($key, "'") !== FALSE){
			throw new Exception("The " . self::getModuleDirectoryName() . " module has a setting named \"$key\" that contains quote characters.  These are not allowed in setting names so that they are always html field name an attribute friendly (without requiring escaping).");
		}

		if(strpos($key, '_') !== FALSE){
			throw new Exception("The " . self::getModuleDirectoryName() . " module has a setting named \"$key\" that contains an underscore.  Underscores are not allowed in setting names because they are used for internal settings.");
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