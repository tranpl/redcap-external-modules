<?php
namespace ExternalModules;

if (!defined(__DIR__)){
	define(__DIR__, dirname(__FILE__));
}

require_once __DIR__ . "/AbstractExternalModule.php";

if(PHP_SAPI == 'cli'){
	// This is required for redcap when running on the command line (including unit testing).
	define('NOAUTH', true);
}

if(!defined('APP_PATH_WEBROOT')){
	// Only include redcap_connect.php if it hasn't been included at some point before.
	// Upgrades crash without this check.
	// Perhaps it has something to do with loading both the new and old version of redcap_connect.php......
	require_once __DIR__ . "/../../redcap_connect.php";
}

if (class_exists('ExternalModules\ExternalModules')) {
	return;
}

use \Exception;

class ExternalModules
{
	const GLOBAL_SETTING_PROJECT_ID = 'NULL';
	const KEY_VERSION = 'version';
	const KEY_ENABLED = 'enabled';

	const TEST_MODULE_PREFIX = 'UNIT-TESTING-PREFIX';

	const DISABLE_EXTERNAL_MODULE_HOOKS = 'disable-external-module-hooks';

	const OVERRIDE_PERMISSION_LEVEL_SUFFIX = '_override-permission-level';
	const OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS = 'design';

	# base URL for external modules
	public static $BASE_URL;

	# URL for the modules directory
	public static $MODULES_URL;

	# path for the modules directory
	public static $MODULES_PATH;

	# index is hook $name, then $prefix, then $version
	private static $delayed;

	private static $hookBeingExecuted;
	private static $versionBeingExecuted;

	private static $initialized = false;
	private static $activeModulePrefix;
	private static $instanceCache = array();
	private static $idsByPrefix;

	private static $globallyEnabledVersions;
	private static $projectEnabledDefaults;
	private static $projectEnabledOverrides;

	private static $configs = array();

	# two reserved settings that are there for each project
	# KEY_VERSION, if present, denotes that the project is enabled system-wide
	# KEY_ENABLED is present when enabled for each project
	# Modules can be enabled for all projects (system-wide) if KEY_ENABLED == 1 for system value
	private static $RESERVED_SETTINGS = array(
		array(
			'key' => self::KEY_VERSION,
			'hidden' => true,
		),
		array(
			'key' => self::KEY_ENABLED,
			'name' => 'Enable on all projects by default',
			'project-name' => 'Enable on this project',
			'type' => 'checkbox',
			'allow-project-overrides' => true,
		)
	);

	# defines criteria to judge someone is on a development box or not
	private static function isLocalhost()
	{
		return @$_SERVER['HTTP_HOST'] == 'localhost';
	}

	# initializes the External Module aparatus
	static function initialize()
	{
		if(self::isLocalhost()){
			// Assume this is a developer's machine and enable errors.
			ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL);
		}

		$modulesDirectoryName = '/modules/';

		if(strpos($_SERVER['REQUEST_URI'], $modulesDirectoryName) === 0){
			die('Requests directly to module version directories are disallowed.  Please use the getUrl() method to build urls to your module pages instead.');
		}

		self::$BASE_URL = APP_PATH_WEBROOT . '../external_modules/';
		self::$MODULES_PATH = __DIR__ . "/../.." . $modulesDirectoryName;

		if(!self::isLocalhost()){
			register_shutdown_function(function(){
				$activeModulePrefix = self::getActiveModulePrefix();
				if($activeModulePrefix != null){
					$error = error_get_last();
					$message = "The '$activeModulePrefix' module was automatically disabled because of the following error:\n\n";
					$message .= 'Error Message: ' . $error['message'] . "\n";
					$message .= 'Server: ' . gethostname() . "\n";
					$message .= 'File: ' . $error['file'] . "\n";
					$message .= 'Line: ' . $error['line'] . "\n";

					error_log($message);
					ExternalModules::sendAdminEmail("REDCap External Module Automatically Disabled - $activeModulePrefix", $message);

					// We can't just call disable() from here because the database connection has been destroyed.
					// Disable this module via AJAX instead.
					?>
					<br>
					<h4 id="external-modules-message">
						A fatal error occurred while loading the "<?=$activeModulePrefix?>" external module.<br>
						Disabling that module...
					</h4>
					<script>
						var request = new XMLHttpRequest();
						request.onreadystatechange = function() {
							if (request.readyState == XMLHttpRequest.DONE ) {
								var messageElement = document.getElementById('external-modules-message')
								if(request.responseText == 'success'){
									messageElement.innerHTML = 'The "<?=$activeModulePrefix?>" external module was automatically disabled in order to allow REDCap to function properly.  The REDCap administrator has been notified.  Please save a copy of the above error and fix it before re-enabling the module.';
								}
								else{
									messageElement.innerHTML += '<br>An error occurred while disabling the "<?=$activeModulePrefix?>" module: ' + request.responseText;
								}
							}
						};

						request.open("POST", "<?=self::$BASE_URL?>/manager/ajax/disable-module.php?<?=self::DISABLE_EXTERNAL_MODULE_HOOKS?>");
						request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
						request.send("module=<?=$activeModulePrefix?>");
					</script>
					<?php
				}
			});
		}
	}

	# controls which module is currently being manipulated
	private static function setActiveModulePrefix($prefix)
	{
		self::$activeModulePrefix = $prefix;
	}

	# returns which module is currently being manipulated
	private static function getActiveModulePrefix()
	{
		return self::$activeModulePrefix;
	}

	private static function sendAdminEmail($subject, $message)
	{
		global $project_contact_email;

		$message = str_replace('<br>', "\n", $message);

		$email = new \Message();
		$email->setFrom($project_contact_email);
		$email->setTo('mark.mcever@vanderbilt.edu');
		$email->setSubject($subject);
		$email->setBody($message, true);
		$email->send();
	}

	# there are two situations which external modules are displayed
	# under a project or under the control center

	# this gets the project header
	static function getProjectHeaderPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	static function getProjectFooterPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	# disables a module system-wide
	static function disable($moduleDirectoryPrefix)
	{
		self::removeGlobalSetting($moduleDirectoryPrefix, self::KEY_VERSION);
	}

	# enables a module system-wide
	static function enable($moduleDirectoryPrefix, $version)
	{
		# Attempt to create an instance of the module before enabling it system wide.
		# This should catch problems like syntax errors in module code.
		$instance = self::getModuleInstance($moduleDirectoryPrefix, $version);

		self::initializeSettingDefaults($instance);

		self::setGlobalSetting($moduleDirectoryPrefix, self::KEY_VERSION, $version);
	}

	# initializes the global/system settings
	static function initializeSettingDefaults($moduleInstance)
	{
		$config = $moduleInstance->getConfig();
		foreach($config['global-settings'] as $details){
			$key = $details['key'];
			$default = @$details['default'];
			$existingValue = $moduleInstance->getGlobalSetting($key);
			if(isset($default) && $existingValue == null){
				$moduleInstance->setGlobalSetting($key, $default);
			}
		}
	}

	static function getGlobalSetting($moduleDirectoryPrefix, $key)
	{
		return self::getSetting($moduleDirectoryPrefix, self::GLOBAL_SETTING_PROJECT_ID, $key);
	}

	static function getGlobalSettings($moduleDirectoryPrefixes, $keys = null)
	{
		return self::getSettings($moduleDirectoryPrefixes, self::GLOBAL_SETTING_PROJECT_ID, $keys);
	}

	static function setGlobalSetting($moduleDirectoryPrefix, $key, $value)
	{
		self::setProjectSetting($moduleDirectoryPrefix, self::GLOBAL_SETTING_PROJECT_ID, $key, $value);
	}

	static function removeGlobalSetting($moduleDirectoryPrefix, $key)
	{
		self::removeProjectSetting($moduleDirectoryPrefix, self::GLOBAL_SETTING_PROJECT_ID, $key);
	}

	static function setProjectSetting($moduleDirectoryPrefix, $projectId, $key, $value)
	{
		self::setSetting($moduleDirectoryPrefix, $projectId, $key, $value);
	}

	# value is edoc ID
	static function setGlobalFileSetting($moduleDirectoryPrefix, $key, $value)
	{
		self::setFileSetting($moduleDirectoryPrefix, self::GLOBAL_SETTING_PROJECT_ID, $key, $value);
	}

	# value is edoc ID
	static function setFileSetting($moduleDirectoryPrefix, $projectId, $key, $value)
	{
		self::setSetting($moduleDirectoryPrefix, $projectId, $key, $value, "file");
	}

	static function removeGlobalFileSetting($moduleDirectoryPrefix, $key)
	{
		self::removeFileSetting($moduleDirectoryPrefix, self::GLOBAL_SETTING_PROJECT_ID, $key);
	}

	static function removeFileSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		self::setProjectSetting($moduleDirectoryPrefix, $projectId, $key, null);
	}

	# returns boolean
	public static function isProjectSettingDefined($prefix, $key)
	{
		$config = self::getConfig($prefix);
		foreach($config['project-settings'] as $setting){
			if($setting['key'] == $key){
				return true;
			}
		}

		return false;
	}

	# this is a helper method
	# call set [Global=System,Project] Setting instead of calling this method
	private static function setSetting($moduleDirectoryPrefix, $projectId, $key, $value, $type = "")
	{
		if($projectId == self::GLOBAL_SETTING_PROJECT_ID){
			if(!self::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
				throw new Exception("You don't have permission to save global settings!");
			}
		}
		else if(!self::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)) {
			if(self::isProjectSettingDefined($moduleDirectoryPrefix, $key)){
				throw new Exception("You don't have permission to save the following project setting: $key");
			}
			else{
				// The setting is not defined in the config.  Allow any user to save it
				// (effectively leaving permissions up to the module creator).
				// This is required for user based configuration (like reporting for ED Data).
			}
		}

		# if $value is an array, then encode as JSON
		# else store $value as type specified in gettype(...)
		if ($type === "") {
			$type = gettype($value);
		}
		if ($type == "array") {
			$type = "json";
			$newValue = array();
			foreach ($value as $v) {
				# cannot store null values; store as blank strings instead
				if ($v === null) {
					$v = "";
				}
				$newValue[] = $v;
			}
			$value = json_encode($newValue);
		}

		$externalModuleId = self::getIdForPrefix($moduleDirectoryPrefix);

		$projectId = db_real_escape_string($projectId);
		$key = db_real_escape_string($key);

		# oldValue is not escaped so that null values are maintained to specify an INSERT vs. UPDATE
		$oldValue = self::getSetting($moduleDirectoryPrefix, $projectId, $key);

		$pidString = $projectId;
		if (!$projectId) {
			$pidString = "NULL";
		}

		if ($type == "boolean") {
			$value = ($value) ? 'true' : 'false';
		}
		if (gettype($oldValue) == "boolean") {
			$oldValue = ($oldValue) ? 'true' : 'false';
		}
		$oldValueStr = (string) $oldValue;
		if (gettype($oldValue) == "array") {
			$oldValueStr = json_encode($oldValue);
		}
		if((string) $value === $oldValueStr){
			// We don't need to do anything.
			return;
		} else if($value === null){
			$event = "DELETE";
			$sql = "DELETE FROM redcap_external_module_settings
					WHERE
						external_module_id = $externalModuleId
						AND " . self::getSqlEqualClause('project_id', $pidString) . "
						AND `key` = '$key'";
		} else {
			$value = db_real_escape_string($value);
			if($oldValue === null) {
				$event = "INSERT";
				$sql = "INSERT INTO redcap_external_module_settings
							(
								`external_module_id`,
								`project_id`,
								`key`,
								`type`,
								`value`
							)
						VALUES
						(
							$externalModuleId,
							$pidString,
							'$key',
							'$type',
							'$value'
						)";
			} else {
				$event = "UPDATE";
				$sql = "UPDATE redcap_external_module_settings
						SET value = '$value',
							type = '$type'
						WHERE
							external_module_id = $externalModuleId
							AND " . self::getSqlEqualClause('project_id', $projectId) . "
							AND `key` = '$key'";
			}
		}

		self::query($sql);

		$affectedRows = db_affected_rows();

		$description = ucfirst(strtolower($event)) . ' External Module setting';

		if(class_exists('Logging')){
			// REDCap v6.18.3 or later
			\Logging::logEvent($sql, 'redcap_external_module_settings', $event, $key, $value, $description, "", "", $projectId);
		}
		else{
			// REDCap prior to v6.18.3
			log_event($sql, 'redcap_external_module_settings', $event, $key, $value, $description, "", "", $projectId);
		}

		if($affectedRows != 1){
			throw new Exception("Unexpected number of affected rows ($affectedRows) on External Module setting query: $sql");
		}
	}

	# get all the settings as an array instead of one by one
	# returns an associative array with index of key and value of value
	# arrays of values (e.g., repeatble) will be returned as arrays
	# As in,
	# 	$ary['key'] = 'string';
	#	$ary['key2'] = 123;
	#	$ary['key3'] = [ 1, 'abc', 3 ];
	#	$ary['key3'][0] = 1;
	#	$ary['key3'][1] = 'abc';
	#	$ary['key3'][2] = 3;
	static function getProjectSettingsAsArray($moduleDirectoryPrefixes, $projectId)
	{
		$result = self::getSettings($moduleDirectoryPrefixes, array(self::GLOBAL_SETTING_PROJECT_ID, $projectId));

		$settings = array();
		while($row = self::validateSettingsRow(db_fetch_assoc($result))){
			$key = $row['key'];
			$value = $row['value'];

			$setting =& $settings[$key];
			if(!isset($setting)){
				$setting = array();
				$settings[$key] =& $setting;
			}

			if($row['project_id'] === null){
				$setting['global_value'] = $value;

				if(!isset($setting['value'])){
					$setting['value'] = $value;
				}
			}
			else{
				$setting['value'] = $value;
			}
		}

		return $settings;
	}

	static function getSettings($moduleDirectoryPrefixes, $projectIds, $keys = array())
	{
		$whereClauses = array();

		if (!empty($moduleDirectoryPrefixes)) {
			$whereClauses[] = self::getSQLInClause('m.directory_prefix', $moduleDirectoryPrefixes);
		}

		if (!empty($projectIds)) {
			$whereClauses[] = self::getSQLInClause('s.project_id', $projectIds);
		}

		if (!empty($keys)) {
			$whereClauses[] = self::getSQLInClause('s.key', $keys);
		}

		return self::query("SELECT directory_prefix, s.project_id, s.project_id, s.key, s.value, s.type
							FROM redcap_external_modules m
							JOIN redcap_external_module_settings s
								ON m.external_module_id = s.external_module_id
							WHERE " . implode(' AND ', $whereClauses));
	}

	# row contains the data type in field 'type' and the value in field 'value'
	# this makes sure that the data returned in 'value' is of that correct type
	static function validateSettingsRow($row)
	{
		if ($row == null) {
			return null;
		}

		$type = $row['type'];
		$value = $row['value'];

		if ($type == "json") {
			if ($json = json_decode($value)) {
				$value = $json;
			}
		}
		else if ($type == 'file') {
			// do nothing
		}
		else if ($type == "boolean") {
			if ($value == "true") {
				$value = true;
			} else if ($value == "false") {
				$value = false;
			}
		}
		else {
			if (!settype($value, $type)) {
				die('Unable to set the type of "' . $value . '" to "' . $type . '"!  This should never happen, as it means unexpected/inconsistent values exist in the database.');
			}
		}

		$row['value'] = $value;

		return $row;
	}

	private static function getSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		$result = self::getSettings($moduleDirectoryPrefix, $projectId, $key);

		$numRows = db_num_rows($result);
		if($numRows == 1) {
			$row = self::validateSettingsRow(db_fetch_assoc($result));

			return $row['value'];
		}
		else if($numRows == 0){
			return null;
		}
		else{
			throw new Exception("More than one External Module setting exists for project $projectId and key '$key'!  This should never happen!");
		}
	}

	static function getProjectSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		$value = self::getSetting($moduleDirectoryPrefix, $projectId, $key);

		if($value == null){
			$value =  self::getGlobalSetting($moduleDirectoryPrefix, $key);
		}

		return $value;
	}

	static function removeProjectSetting($moduleDirectoryPrefix, $projectId, $key){
		self::setProjectSetting($moduleDirectoryPrefix, $projectId, $key, null);
	}

	# directory name is [institution]_[module]_v[X].[Y]
	# prefix is [institution]_[module]
	# gets stored in database as module_id number
	# translates prefix string into a module_id number
	private static function getIdForPrefix($prefix)
	{
		if(!isset(self::$idsByPrefix)){
			$result = self::query("SELECT external_module_id, directory_prefix FROM redcap_external_modules");

			$idsByPrefix = array();
			while($row = db_fetch_assoc($result)){
				$idsByPrefix[$row['directory_prefix']] = $row['external_module_id'];
			}

			self::$idsByPrefix = $idsByPrefix;
		}

		$id = @self::$idsByPrefix[$prefix];
		if($id == null){
			self::query("INSERT INTO redcap_external_modules (directory_prefix) VALUES ('$prefix')");
			$id = db_insert_id();
			self::$idsByPrefix[$prefix] = $id;
		}

		return $id;
	}

	# translates a module_id number into a prefix string
	public static function getPrefixForID($id){
		$id = db_real_escape_string($id);

		$result = self::query("SELECT directory_prefix FROM redcap_external_modules WHERE external_module_id = '$id'");

		$row = db_fetch_assoc($result);
		if($row){
			return $row['directory_prefix'];
		}

		return null;
	}

	# executes a database query and returns the result
	private static function query($sql)
	{
		$result = db_query($sql);

		if($result == FALSE){
			throw new Exception("Error running External Module query: \nDB Error: " . db_error() . "\nSQL: $sql");
		}

		return $result;
	}

	# converts an equals clause into SQL
	private static function getSQLEqualClause($columnName, $value)
	{
		$columnName = db_real_escape_string($columnName);
		$value = db_real_escape_string($value);

		if($value == 'NULL'){
			return "$columnName IS NULL";
		}
		else{
			return "$columnName = '$value'";
		}
	}

	# converts an IN array clause into SQL
	private static function getSQLInClause($columnName, $array)
	{
		if(!is_array($array)){
			$array = array($array);
		}

		$columnName = db_real_escape_string($columnName);

		$valueListSql = "";
		$nullSql = "";

		foreach($array as $item){
		if(!empty($valueListSql)){
			$valueListSql .= ', ';
		}

		$item = db_real_escape_string($item);

			if($item == 'NULL'){
				$nullSql = "$columnName IS NULL";
			}
			else{
				$valueListSql .= "'$item'";
			}
		}

		$parts = array();

		if(!empty($valueListSql)){
			$parts[] = "$columnName IN ($valueListSql)";
		}

		if(!empty($nullSql)){
			$parts[] = $nullSql;
		}

		return "(" . implode(" OR ", $parts) . ")";
	}

	# begins the execution of a hook
	# helper method
	# should call callHook
	private static function startHook($prefix, $version, $arguments) {
		if(!self::hasPermission($prefix, $version, self::$hookBeingExecuted)){
			// To prevent unnecessary class conflicts (especially with old plugins), we should avoid loading any module classes that don't actually use this hook.
			return;
		}

		$instance = self::getModuleInstance($prefix, $version);
		if(method_exists($instance, self::$hookBeingExecuted)){
			self::setActiveModulePrefix($prefix);
			try{
				call_user_func_array(array($instance,self::$hookBeingExecuted), $arguments);
			}
			catch(Exception $e){
				$message = "The '" . $prefix . "' module threw the following exception when calling the hook method '".self::$hookBeingExecuted."':\n\n" . $e;
				error_log($message);
				ExternalModules::sendAdminEmail("REDCap External Module Hook Exception - $prefix", $message);
			}
			self::setActiveModulePrefix(null);
		}
	}

	# calls a hooke via startHook
	static function callHook($name, $arguments)
	{
		if(isset($_GET[self::DISABLE_EXTERNAL_MODULE_HOOKS])){
			return;
		}

		if(!defined('PAGE')){
			$page = ltrim($_SERVER['REQUEST_URI'], '/');
			define('PAGE', $page);
		}

		# We must initialize this static class here, since this method actually gets called before anything else.
		# We can't initialize sooner than this because we have to wait for REDCap to initialize it's functions and variables we depend on.
		# This method is actually called many times (once per hook), so we should only initialize once.
		if(!self::$initialized){
			self::initialize();
			self::$initialized = true;
		}

		$name = str_replace('redcap_', '', $name);

		$templatePath = __DIR__ . "/../manager/templates/hooks/$name.php";
		if(file_exists($templatePath)){
			self::safeRequire($templatePath, $arguments);
		}

		$pid = null;
		if(!empty($arguments)){
			$firstArg = $arguments[0];
			if((int)$firstArg == $firstArg){
				// As of REDCap 6.16.8, the above checks allow us to safely assume the first arg is the pid for all hooks.
				$pid = $arguments[0];
			}
		}

		self::$hookBeingExecuted = "hook_$name";

		if (!self::$delayed) {
			self::$delayed = array();
		}
		self::$delayed[self::$hookBeingExecuted] = array();

		$versionsByPrefix = self::getEnabledModules($pid);
		foreach($versionsByPrefix as $prefix=>$version){
			self::$versionBeingExecuted = $version;

			self::startHook($prefix, $version, $arguments);
		}

		# runs delayed modules
		# terminates if queue is 0 or if it is the same as in the previous iteration
		# (i.e., no modules completing)
		$prevNumDelayed = count($versionsByPrefix) + 1;
		while (($prevNumDelayed > count(self::$delayed[self::$hookBeingExecuted])) && (count(self::$delayed[self::$hookBeingExecuted]) > 0)) {
			$prevDelayed = self::$delayed[self::$hookBeingExecuted];
			 $prevNumDelayed = count($prevDelayed);
			self::$delayed[self::$hookBeingExecuted] = array();
			foreach ($prevDelayed as $prefix=>$version) {
				self::$versionBeingExecuted = $version;

				if(!self::hasPermission($prefix, $version, self::$hookBeingExecuted)){
					// To prevent unnecessary class conflicts (especially with old plugins), we should avoid loading any module classes that don't actually use this hook.
					continue;
				}

				self::startHook($prefix, $version, $arguments);
			}
		}
		self::$hookBeingExecuted = "";
		self::$versionBeingExecuted = "";
	}

	# places module in delaying queue to be executed after all others are executed
	public static function delayModuleExecution() {
		self::$delayed[self::$hookBeingExecuted][self::$activeModulePrefix] = self::$versionBeingExecuted;
	}

	# This function exists solely to provide a scope where we don't care if local variables get overwritten by code in the required file.
	# Use the $arguments variable to pass data to the required file.
	static function safeRequire($path, $arguments = array()){
		require $path;
	}

	# This function exists solely to provide a scope where we don't care if local variables get overwritten by code in the required file.
	# Use the $arguments variable to pass data to the required file.
	static function safeRequireOnce($path, $arguments = array()){
		require_once $path;
	}

	# this is where a module has its code loaded
	private static function getModuleInstance($prefix, $version)
	{
		self::setActiveModulePrefix($prefix);

		$moduleDirectoryName = self::getModuleDirectoryName($prefix, $version);
		$instance = @self::$instanceCache[$moduleDirectoryName];
		if(!isset($instance)){
			$modulePath = ExternalModules::$MODULES_PATH . $moduleDirectoryName;
			$className = self::getMainClassName($prefix);
			$classNameWithNamespace = "\\" . __NAMESPACE__ . "\\$className";

			if(!class_exists($classNameWithNamespace)){
				$classFilePath = "$modulePath/$className.php";

				if(!file_exists($classFilePath)){
					throw new Exception("Could not find the following External Module main class file: $classFilePath");
				}

				self::safeRequireOnce($classFilePath);
			}

			$instance = new $classNameWithNamespace();
			self::$instanceCache[$moduleDirectoryName] = $instance;
		}

		self::setActiveModulePrefix(null);

		return $instance;
	}

	# parses the prefix and turns it into a class name
	# convention is [institution]_[module]_v[X].[Y]
	# module is converted into camelCase, has its first letter capitalized, and is appended with "ExternalModule"
	# note well that if [module] contains an underscore (_), only the first chain link will be dealt with
	# E.g., vanderbilt_example_v1.0 yields a class name of "ExampleExternalModule"
	# vanderbilt_pdf_modify_v1.2 yields a class name of "PdfExternalModule"
	private static function getMainClassName($prefix)
	{
		$parts = explode('_', $prefix);
		$parts = explode('-', $parts[1]);

		$className = '';
		foreach($parts as $part){
			$className .= ucfirst($part);
		}

		$className .= 'ExternalModule';

		return $className;
	}

	# Accepts a project id as the first parameter.
	# If the project id is null, all globally enabled module instances are returned.
	# Otherwise, only instances enabled for the current project id are returned.
	static function getEnabledModules($pid = null)
	{
		if($pid == null){
			return self::getGloballyEnabledVersions();
		}
		else{
			return self::getEnabledModuleVersionsForProject($pid);
		}
	}

	# returns all enabled versions that are enabled system-wide
	private static function getGloballyEnabledVersions()
	{
		if(!isset(self::$globallyEnabledVersions)){
			self::cacheAllEnableData();
		}

		return self::$globallyEnabledVersions;
	}

	private static function getProjectEnabledDefaults()
	{
		if(!isset(self::$projectEnabledDefaults)){
			self::cacheAllEnableData();
		}

		return self::$projectEnabledDefaults;
	}

	private static function getProjectEnabledOverrides()
	{
		if(!isset(self::$projectEnabledOverrides)){
			self::cacheAllEnableData();
		}

		return self::$projectEnabledOverrides;
	}

	# get all versions enabled for a given project
	private static function getEnabledModuleVersionsForProject($pid)
	{
		$projectEnabledOverrides = self::getProjectEnabledOverrides();

		$enabledPrefixes = self::getProjectEnabledDefaults();
		$overrides = @$projectEnabledOverrides[$pid];
		if(isset($overrides)){
			foreach($overrides as $prefix => $value){
				if($value){
					$enabledPrefixes[$prefix] = true;
				}
				else{
					unset($enabledPrefixes[$prefix]);
				}
			}
		}

		$globallyEnabledVersions = self::getGloballyEnabledVersions();

		$enabledVersions = array();
		foreach(array_keys($enabledPrefixes) as $prefix){
			$version = @$globallyEnabledVersions[$prefix];

			// Check the version to make sure the module is not globally disabled.
			if(isset($version)){
				$enabledVersions[$prefix] = $version;
			}
		}

		return $enabledVersions;
	}

	private static function shouldExcludeModule($prefix)
	{
		$isTestPrefix = strpos($prefix, self::TEST_MODULE_PREFIX) === 0;
		if($isTestPrefix && !self::isTesting($prefix)){
			// This php process is not running unit tests.
			// Ignore the test prefix so it doesn't interfere with this process.
			return true;
		}

		return false;
	}

	private static function isTesting()
	{
		return PHP_SAPI == 'cli' && strpos($_SERVER['argv'][0], 'phpunit') !== FALSE;
	}

	# calling this method stores a local cache of all releavant data from the database
	private static function cacheAllEnableData()
	{
		$globallyEnabledVersions = array();
		$projectEnabledOverrides = array();
		$projectEnabledDefaults = array();

		// Only attempt to detect enabled modules if the external module tables exist.
		if(self::getSqlToRunIfDBOutdated() === ""){
			$result = self::getSettings(null, null, array(self::KEY_VERSION, self::KEY_ENABLED));
			while($row = self::validateSettingsRow(db_fetch_assoc($result))){
				$pid = $row['project_id'];
				$prefix = $row['directory_prefix'];
				$key = $row['key'];
				$value = $row['value'];

				if(self::shouldExcludeModule($prefix)){
					continue;
				}

				if($key == self::KEY_VERSION){
					$globallyEnabledVersions[$prefix] = $value;
				}
				else if($key == self::KEY_ENABLED){
					if(isset($pid)){
						$projectEnabledOverrides[$pid][$prefix] = $value;
					}
					else if($value) {
						$projectEnabledDefaults[$prefix] = true;
					}
				}
				else{
					throw new Exception("Unexpected key: $key");
				}
			}
		}

		// Overwrite any previously cached results
		self::$globallyEnabledVersions = $globallyEnabledVersions;
		self::$projectEnabledDefaults = $projectEnabledDefaults;
		self::$projectEnabledOverrides = $projectEnabledOverrides;
	}

	# tests whether External Modules has been initially configured
	static function areTablesPresent()
	{
		$result = self::query("SHOW TABLES LIKE 'redcap_external_module%'");
		return db_num_rows($result) > 0;
	}

	# tests whether another database upgrade has taken place
	static function isTypePresentInTable()
	{
		global $db;
		$sql = "SELECT * 
			FROM information_schema.COLUMNS 
			WHERE 
				TABLE_SCHEMA = '".db_real_escape_string($db)."' 
				AND TABLE_NAME = 'redcap_external_module_settings' 
				AND COLUMN_NAME = 'type'";

		$result = self::query($sql);
		return db_num_rows($result) > 0;
	}

	# returns SQL statements to be run when the database is outdated.
	# returns "" if the database is up-to-date
	#
	# Checks in order for various conditions
	# Helper methods in methodName return true if up-to-date; false if out-of-date
	static function getSqlToRunIfDBOutdated()
	{
		$sql = array();
		$sql[] = array( "file" => "sql/create tables.sql",
				"methodName" => "areTablesPresent"
				);
		$sql[] = array( "file" => "sql/migration-2017-01-18_10-03-00.sql",
				"methodName" => "isTypePresentInTable"
				);

		$sqlToReturn = array();
		foreach ($sql as $row) {
			$isPresent = self::callPrivateMethod($row['methodName']);
			if (!$isPresent) {
				$sqlToReturn[] = htmlspecialchars(file_get_contents(__DIR__ . '/../'.$row['file']));
			}
		}
		return implode("\n", $sqlToReturn);
	}

	# calls a private method in the ExternalModules class
        private function callPrivateMethod($methodName)
        {
                $args = func_get_args();
                array_shift($args); // remove the method name

                $class = self::getReflectionClass();
                $method = $class->getMethod($methodName);
                $method->setAccessible(true);

                return $method->invokeArgs(null, $args);
        }

        private function getReflectionClass()
        {
                return new \ReflectionClass('ExternalModules\ExternalModules');
        }


	# echo's HTML for adding an approriate resource; also prepends appropriate directory structure
	static function addResource($path)
	{
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		if(substr($path,0,8) == "https://") {
			$url = $path;
		}
		else {
			$path = "manager/$path";
			$fullLocalPath = __DIR__ . "/../$path";

			// Add the filemtime to the url for cache busting.
                        clearstatcache(true, $path);
			$url = ExternalModules::$BASE_URL . $path . '?' . filemtime($fullLocalPath);
		}

		if ($extension == 'css') {
			echo "<link rel='stylesheet' type='text/css' href='" . $url . "'>";
		}
		else if ($extension == 'js') {
			echo "<script src='" . $url . "'></script>";
		}
		else {
			throw new Exception('Unsupported resource added: ' . $path);
		}
	}

	# returns an array of links requested by the config.json
	static function getLinks(){
		$pid = self::getPID();

		if(isset($pid)){
			$type = 'project';
		}
		else{
			$type = 'control-center';
		}

		$links = array();

		$versionsByPrefix = self::getEnabledModules($pid);
		foreach($versionsByPrefix as $prefix=>$version){
			$config = ExternalModules::getConfig($prefix, $version);

			foreach($config['links'][$type] as $link){
				$name = $link['name'];
				$link['url'] = self::getUrl($prefix, $link['url']);
				$links[$name] = $link;
			}
		}

		$addManageLink = function($url) use (&$links){
			$links['Manage External Modules'] = array(
				'icon' => 'brick',
				'url' => ExternalModules::$BASE_URL  . $url
			);
		};

		if(isset($pid)){
			if(SUPER_USER || !empty($modules) && self::hasDesignRights()){
				$addManageLink('manager/project.php?');
			}
		}
		else{
			$addManageLink('manager/control_center.php');
		}

		ksort($links);

		return $links;
	}

	# returns the pid from the $_GET array
	private static function getPID()
	{
		return @$_GET['pid'];
	}

	# for an internal request for a project URL, transforms the request into a URL
	private static function getUrl($prefix, $page)
	{
		$id = self::getIdForPrefix($prefix);
		$page = preg_replace('/\.php$/', '', $page); // remove .php extension if it exists
		return self::$BASE_URL . "?id=$id&page=$page";
	}

	# returns the configs for disabled modules
	static function getDisabledModuleConfigs($enabledModules)
	{
		$dirs = scandir(self::$MODULES_PATH);

		$disabledModuleVersions = array();
		foreach ($dirs as $dir) {
			if ($dir[0] == '.') {
				continue;
			}

			list($prefix, $version) = self::getParseModuleDirectoryPrefixAndVersion($dir);

			if(@$enabledModules[$prefix] != $version) {
				$versions = @$disabledModuleVersions[$prefix];
				if(!isset($versions)){
					$versions = array();

				}

				// Use array_merge_recursive() to show newest versions first.
				$disabledModuleVersions[$prefix] = array_merge_recursive(
					array($version => self::getConfig($prefix, $version)),
					$versions
				);
			}
		}

		return $disabledModuleVersions;
	}

	# Parses [institution]_[module]_v[X].[Y] into [ [institution]_[module], v[X].[Y] ]
	# e.g., vanderbilt_example_v1.0 becomes [ "vanderbilt_example", "v1.0" ]
	static function getParseModuleDirectoryPrefixAndVersion($directoryName){
		$parts = explode('_', $directoryName);

		$version = array_pop($parts);
		$prefix = implode('_', $parts);

		return array($prefix, $version);
	}

	# returns the config.json for a given module
	static function getConfig($prefix, $version = null, $pid = null)
	{
		if($version == null){
			$version = self::getEnabledVersion($prefix);
		}

		$moduleDirectoryName = self::getModuleDirectoryName($prefix, $version);
		$config = @self::$configs[$moduleDirectoryName];
		if($config === null){
			$configFilePath = self::$MODULES_PATH . "$moduleDirectoryName/config.json";
			$config = json_decode(file_get_contents($configFilePath), true);

			if($config == null){
				throw new Exception("An error occurred while parsing a configuration file!  The following file is likely not valid JSON: $configFilePath");
			}

			$configs[$moduleDirectoryName] = $config;
		}

		foreach(['permissions', 'global-settings', 'project-settings'] as $key){
			if(!isset($config[$key])){
				$config[$key] = array();
			}
		}

		## Pull form and field list for choice list of project-settings field-list and form-list settings
		if(!empty($pid)) {
			foreach($config['project-settings'] as $configKey => $configRow) {
				$config['project-settings'][$configKey] = self::getAdditionalFieldChoices($configRow,$pid);
			}
		}

		$config = self::addReservedSettings($config);

		return $config;
	}

	# specialty field lists include: user-role-list, user-list, dag-list, field-list, and form-list
	public static function getAdditionalFieldChoices($configRow,$pid) {
                if ($configRow['type'] == 'user-role-list') {
                        $choices = [];

                        $sql = "SELECT role_id,role_name
                                        FROM redcap_user_roles
                                        WHERE project_id = '" . db_real_escape_string($pid) . "'
                                        ORDER BY role_id";
                        $result = self::query($sql);

                        while ($row = db_fetch_assoc($result)) {
                                $choices[] = ['value' => $row['role_id'], 'name' => $row['role_name']];
                        }

                        $configRow['choices'] = $choices;
                }
                else if ($configRow['type'] == 'user-list') {
                        $choices = [];

                        $sql = "SELECT ur.username,ui.user_firstname,ui.user_lastname
                                        FROM redcap_user_rights ur, redcap_user_information ui
                                        WHERE ur.project_id = '" . db_real_escape_string($pid) . "'
                                                AND ui.username = ur.username
                                        ORDER BY ui.ui_id";
                        $result = self::query($sql);

                        while ($row = db_fetch_assoc($result)) {
                                $choices[] = ['value' => $row['username'], 'name' => $row['user_firstname'] . ' ' . $row['user_lastname']];
                        }

                        $configRow['choices'] = $choices;
                }
                else if ($configRow['type'] == 'dag-list') {
                        $choices = [];

                        $sql = "SELECT group_id,group_name
                                        FROM redcap_data_access_groups
                                        WHERE project_id = '" . db_real_escape_string($pid) . "'
                                        ORDER BY group_id";
                        $result = self::query($sql);

                        while ($row = db_fetch_assoc($result)) {
                                $choices[] = ['value' => $row['group_id'], 'name' => $row['group_name']];
                        }

                        $configRow['choices'] = $choices;
                }
		else if ($configRow['type'] == 'field-list') {
			$choices = [];

			$sql = "SELECT field_name,element_label
					FROM redcap_metadata
					WHERE project_id = '" . db_real_escape_string($pid) . "'
					ORDER BY field_order";
			$result = self::query($sql);

			while ($row = db_fetch_assoc($result)) {
				$choices[] = ['value' => $row['field_name'], 'name' => $row['field_name'] . " - " . substr($row['element_label'], 0, 20)];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'form-list') {
			$choices = [];

			$sql = "SELECT DISTINCT form_name
					FROM redcap_metadata
					WHERE project_id = '" . db_real_escape_string($pid) . "'
					ORDER BY field_order";
			$result = self::query($sql);

			while ($row = db_fetch_assoc($result)) {
				$choices[] = ['value' => $row['form_name'], 'name' => $row['form_name']];
			}

			$configRow['choices'] = $choices;
		}
		else if($configRow['type'] == 'sub_settings') {
			foreach ($configRow['sub_settings'] as $subConfigKey => $subConfigRow) {
				$configRow['sub_settings'][$subConfigKey] = self::getAdditionalFieldChoices($subConfigRow,$pid);
			}
		}

		return $configRow;
	}

	# gets the version of a module
	public static function getEnabledVersion($prefix)
	{
		$versionsByPrefix = self::getGloballyEnabledVersions();
		return @$versionsByPrefix[$prefix];
	}

	# adds the RESERVED_SETTINGS (above) to the config
	private static function addReservedSettings($config)
	{
		$globalSettings = $config['global-settings'];
		$projectSettings = $config['project-settings'];

		$existingSettingKeys = array();
		foreach($globalSettings as $details){
			$existingSettingKeys[$details['key']] = true;
		}

		foreach($projectSettings as $details){
			$existingSettingKeys[$details['key']] = true;
		}

		$visibleReservedSettings = array();
		foreach(self::$RESERVED_SETTINGS as $details){
			$key = $details['key'];
			if(isset($existingSettingKeys[$key])){
				throw new Exception("The '$key' setting key is reserved for internal use.  Please use a different setting key in your module.");
			}

			if(@$details['hidden'] != true){
				$visibleReservedSettings[] = $details;
			}
		}

		// Merge arrays so that reserved settings always end up at the top of the list.
		$config['global-settings'] = array_merge($visibleReservedSettings, $globalSettings);

		return $config;
	}

	# formats directory name from $prefix and $version
	static function getModuleDirectoryName($prefix, $version){
		return $prefix . '_' . $version;
	}

	static function hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)
	{
		if(self::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
			return true;
		}

		if(self::hasDesignRights()){
			if(!self::isGlobalSetting($moduleDirectoryPrefix, $key)){
				return true;
			}

			$level = self::getGlobalSetting($moduleDirectoryPrefix, $key . self::OVERRIDE_PERMISSION_LEVEL_SUFFIX);
			return $level == self::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS;
		}

		return false;
	}

	public static function hasPermission($prefix, $version, $permissionName)
	{
		return in_array($permissionName, self::getConfig($prefix, $version)['permissions']);
	}

	static function isGlobalSetting($moduleDirectoryPrefix, $key)
	{
		$version = self::getGlobalSetting($moduleDirectoryPrefix, self::KEY_VERSION);
		$config = self::getConfig($moduleDirectoryPrefix, $version);

		foreach($config['global-settings'] as $details){
			if($details['key'] == $key){
				return true;
			}
		}

		return false;
	}

	# returns boolean if design rights are given by REDCap for current user
	static function hasDesignRights()
	{
		if(SUPER_USER){
			return true;
		}

		if(!isset($_GET['pid'])){
			// REDCap::getUserRights() will crash if no pid is set, so just return false.
			return false;
		}

		$rights = \REDCap::getUserRights();
		return $rights[USERID]['design'] == 1;
	}

	static function hasGlobalSettingsSavePermission()
	{
		return self::isTesting() || SUPER_USER;
	}

	# Taken from: http://stackoverflow.com/questions/3338123/how-do-i-recursively-delete-a-directory-and-its-entire-contents-files-sub-dir
	private static function rrmdir($dir)
	{
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir . "/" . $object))
						self::rrmdir($dir . "/" . $object);
					else
						unlink($dir . "/" . $object);
				}
			}
			rmdir($dir);
		}
	}

	# there is no getInstance because settings returns an array of repeated elements
	# getInstance would merely consist of dereferencing the array; Ockham's razor

	# sets the instance to a JSON string into the database
	# $instance is 0-based index for array
	# if the old value is a number/string, etc., this function will transform it into a JSON
	# fills is with null values for non-expressed positions in the JSON before instance
	# JSON is a 0-based, one-dimensional array. It can be filled with associative arrays in
	# the form of other JSON-encoded strings.
	static function setInstance($prefix, $projectId, $key, $instance, $value) {
		$instance = (int) $instance;
		$oldValue = self::getSetting($prefix, $projectId, $key);
		$json = array();
		if (gettype($oldValue) != "array") {
			if ($oldValue !== null) {
				$json[] = $oldValue;
			}
		}

		# fill in with prior values
		for ($i=count($json); $i < $instance; $i++) {
			if ((gettype($oldValue) == "array") && (count($oldValue) > $i)) {
				$json[$i] = $oldValue[$i];
			} else {
				# pad with null for prior values when $n is ahead; should never be used
				$json[$i] = null;
			}
		}

		# do not set null values for current instance; always set to empty string 
		if ($value !== null) {
			$json[$instance] = $value;
		} else {
			$json[$instance] = "";
		}

		# fill in remainder if extant
		if (gettype($oldValue) == "array") {
			for ($i = $instance + 1; $i < count($oldValue); $i++) {
				$json[$i] = $oldValue[$i];
			}
		}

		#single-element JSONs are simply data values
		if (count($json) == 1) {
			self::setSetting($prefix, $projectId, $key, $json[0]);
		} else {
			self::setSetting($prefix, $projectId, $key, $json);
		}
	}
}
