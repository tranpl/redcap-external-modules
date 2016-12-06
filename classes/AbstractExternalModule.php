<?php
namespace ExternalModules;

use Exception;

class AbstractExternalModule
{
	private $PREFIX;
	private $VERSION;
	protected $CONFIG;

	function __construct($prefix, $version)
	{
		$this->PREFIX = $prefix;
		$this->VERSION = $version;

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
				throw new Exception("The \"" . $this->PREFIX . "\" module defines the \"$key\" setting on both the global and project levels.  If you want to allow this setting to be overridden on the project level, please remove the project setting configuration and set 'allow-project-overrides' to true in the global setting configuration instead.  If you want this setting to have a different name on the project management page, specify a 'project-name' under the global setting.");
			}

			if(array_key_exists('default', $details)){
				throw new Exception("The \"" . $this->PREFIX . "\" module defines a default value for the the \"$key\" project setting.  Default values are only allowed on global settings.");
			}
		}
	}

	private function checkSettingKey($key)
	{
		if(!self::isSettingKeyValid($key)){
			throw new Exception("The " . $this->PREFIX . " module has a setting named \"$key\" that contains invalid characters.  Only lowercase characters, numbers, and dashes are allowed.");
		}
	}

	protected function isSettingKeyValid($key)
	{
		// Only allow lowercase characters, numbers, and dashes to ensure consistency between modules (and so we don't have to worry about escaping).
		return !preg_match("/[^a-z0-9-]/", $key);
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
			$this->CONFIG = ExternalModules::getConfig($this->PREFIX, $this->VERSION);
		}

		return $this->CONFIG;
	}

	function setGlobalSetting($key, $value)
	{
		ExternalModules::setGlobalSetting($this->PREFIX, $key, $value);
	}

	function getGlobalSetting($key)
	{
		return ExternalModules::getGlobalSetting($this->PREFIX, $key);
	}

	function removeGlobalSetting($key)
	{
		ExternalModules::removeGlobalSetting($this->PREFIX, $key);
	}

	function setProjectSetting($key, $value, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		ExternalModules::setProjectSetting($this->PREFIX, $pid, $key, $value);
	}

	function getProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		return ExternalModules::getProjectSetting($this->PREFIX, $pid, $key);
	}

	function removeProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		ExternalModules::removeProjectSetting($this->PREFIX, $pid, $key);
	}

	// Returns the project level setting if it exists, and returns the global setting if not.
	function getSetting($key, $pid = null)
	{
		$pid = self::detectProjectId($pid);
		if(isset($pid)){
			$value = self::getProjectSetting($key, $pid);
		}

		if(!isset($value)){
			$value = self::getGlobalSetting($key);
		}

		return $value;
	}

	private function requireProjectId($pid)
	{
		$pid = self::detectProjectId($pid);

		if(!isset($pid)){
			throw new Exception("You must supply a project id (pid) either as a GET parameter or as the last argument to this method!");
		}

		return $pid;
	}

	private function detectProjectId($pid)
	{
		if($pid == null){
			$pid = @$_GET['pid'];
		}

		return $pid;
	}
}