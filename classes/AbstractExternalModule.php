<?php
namespace ExternalModules;

if (class_exists('ExternalModules\AbstractExternalModule')) {
	return;
}

use Exception;

class AbstractExternalModule
{
	public $PREFIX;
	public $VERSION;

	# constructor
	function __construct()
	{
		list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion($this->getModuleDirectoryName());

		$this->PREFIX = $prefix;
		$this->VERSION = $version;

		// Disallow illegal configuration options at module instantiation (and enable) time.
		self::checkSettings();
	}

	# checks the config.json settings for validity of syntax
	protected function checkSettings()
	{
		$config = $this->getConfig();
		$systemSettings = $config['system-settings'];
		$projectSettings = $config['project-settings'];

		$handleDuplicate = function($key, $type){
			throw new Exception("The \"" . $this->PREFIX . "\" module defines the \"$key\" $type setting multiple times!");
		};

		$systemSettingKeys = array();
		foreach($systemSettings as $details){
			$key = $details['key'];
			self::checkSettingKey($key);

			if(isset($systemSettingKeys[$key])){
				$handleDuplicate($key, 'system');
			}
			else{
				$systemSettingKeys[$key] = true;
			}
		}

		$projectSettingKeys = array();
		foreach($projectSettings as $details){
			$key = $details['key'];
			self::checkSettingKey($key);

			if(array_key_exists($key, $systemSettingKeys)){
				throw new Exception("The \"" . $this->PREFIX . "\" module defines the \"$key\" setting on both the system and project levels.  If you want to allow this setting to be overridden on the project level, please remove the project setting configuration and set 'allow-project-overrides' to true in the system setting configuration instead.  If you want this setting to have a different name on the project management page, specify a 'project-name' under the system setting.");
			}

			if(array_key_exists('default', $details)){
				throw new Exception("The \"" . $this->PREFIX . "\" module defines a default value for the the \"$key\" project setting.  Default values are only allowed on system settings.");
			}

			if(isset($projectSettingKeys[$key])){
				$handleDuplicate($key, 'project');
			}
			else{
				$projectSettingKeys[$key] = true;
			}
		}
	}

	# checks a config.json setting key $key for validity
	# throws an exception if invalid
	private function checkSettingKey($key)
	{
		if(!self::isSettingKeyValid($key)){
			throw new Exception("The " . $this->PREFIX . " module has a setting named \"$key\" that contains invalid characters.  Only lowercase characters, numbers, and dashes are allowed.");
		}
	}

	# validity check for a setting key $key
	# returns boolean
	protected function isSettingKeyValid($key)
	{
		// Only allow lowercase characters, numbers, dashes, and underscores to ensure consistency between modules (and so we don't have to worry about escaping).
		return !preg_match("/[^a-z0-9-_]/", $key);
	}

	function selectData($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		return 'this could be some data from the database';
	}

	function updateData($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	function deleteData($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	function updateUserPermissions($some, $params=array())
	{
		self::checkPermissions(__FUNCTION__);

		throw new Exception('Not yet implemented!');
	}

	# check whether the current External Module has permission to call the requested method $methodName
	private function checkPermissions($methodName)
	{
		# Convert from camel to snake case.
		# Taken from the second solution here: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
		$permissionName = ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $methodName)), '_');

		if (!$this->hasPermission($permissionName)) {
			throw new Exception("This module must request the \"$permissionName\" permission in order to call the $methodName() method.");
		}
	}

	# checks whether the current External Module has permission for $permissionName
	function hasPermission($permissionName)
	{
		return ExternalModules::hasPermission($this->PREFIX, $this->VERSION, $permissionName);
	}

	# get the config for the current External Module
	# consists of config.json and filled-in values
	function getConfig()
	{
		return ExternalModules::getConfig($this->PREFIX, $this->VERSION);
	}

	# get the directory name of the current external module
	function getModuleDirectoryName()
	{
		$reflector = new \ReflectionClass(get_class($this));
		return basename(dirname($reflector->getFileName()));
	}

	# a GLOBAL/SYSTEM setting is a value to be used on all projects. It can be overridden by a particular project
	# a PROJECT setting is a value set by each project. It may be a value that overrides a system setting
	#      or it may be a value set for that project alone with no suggested System-level value.
	#      the project_id corresponds to the value in REDCap
	#      if a project_id (pid) is null, then it becomes a global/system value 

	# Set the setting specified by the key to the specified value
	# globally/systemwide (shared by all projects).
	function setSystemSetting($key, $value)
	{
		ExternalModules::setSystemSetting($this->PREFIX, $key, $value);
	}

	# Get the value stored globally/systemwide for the specified key.
	function getSystemSetting($key)
	{
		return ExternalModules::getSystemSetting($this->PREFIX, $key);
	}

	# Remove the value stored globally/systemwide for the specified key.
	function removeSystemSetting($key)
	{
		ExternalModules::removeSystemSetting($this->PREFIX, $key);
	}

	# Set the setting specified by the key to the specified value for
	# this project (override the global/system setting).  In most cases
	# the project id can be detected automatically, but it can
	# optionaly be specified as the third parameter instead.
	function setProjectSetting($key, $value, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		ExternalModules::setProjectSetting($this->PREFIX, $pid, $key, $value);
	}

	# Returns the value stored for the specified key for the current
	# project if it exists.  If this setting key is not set (overriden)
	# for the current project, the global value for this key is
	# returned.  In most cases the project id can be detected
	# automatically, but it can optionaly be specified as the third
	# parameter instead.
	function getProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		return ExternalModules::getProjectSetting($this->PREFIX, $pid, $key);
	}

	# returns an array of the project-level settings (all values for the given project, including
	# any global/system values that were not overridden)
	function getAllProjectSettings($pid = null)
	{
		$pid = self::requireProjectId($pid);
		return ExternalModules::getSettings($this->PREFIX, $pid);
	}

	# Remove the value stored for this project and the specified key.
	# In most cases the project id can be detected automatically, but
	# it can optionaly be specified as the third parameter instead.
	function removeProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		ExternalModules::removeProjectSetting($this->PREFIX, $pid, $key);
	}

	function getUrl($path)
	{
        	$pid = self::detectProjectId();
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        	$url = '';
		if($extension != 'php'){
			// This must be a resource, like an image or css/js file.
			// Go ahead and return the version specific url.
            		$url =  ExternalModules::getModuleDirectoryUrl($this->PREFIX, $this->VERSION) . '/' . $path;
		}else {
            		$url = ExternalModules::getUrl($this->PREFIX, $path);
		    	if(!empty($pid)){
		        	$url .= '&pid='.$pid;
            		}
        	}
		return $url;
	}

	public function getModuleName()
	{
		return $this->getConfig()['name'];
	}

	# function to enforce that a pid is required for a particular function
	private function requireProjectId($pid)
	{
		$pid = self::detectProjectId($pid);

		if(!isset($pid)){
			throw new Exception("You must supply a project id (pid) either as a GET parameter or as the last argument to this method!");
		}

		return $pid;
	}

	# if $pid is empty/null, can get the pid from $_GET if it exists
	private function detectProjectId($pid=null)
	{
		if($pid == null){
			$pid = @$_GET['pid'];
		}

		return $pid;
	}

	# pushes the execution of the module to the end of the queue
	# helpful to wait for data to be processed by other modules
	# execution of the module will be restarted from the beginning
	# For example:
	# 	if ($data['field'] === "") {
	#		delayModuleExecution();
	#		return;       // the module will be restarted from the beginning
	#	}
	public function delayModuleExecution() {
		ExternalModules::delayModuleExecution();
	}
}
