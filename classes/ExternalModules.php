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

	public static $BASE_URL;
	public static $MODULES_URL;
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

	private static function isLocalhost()
	{
		return @$_SERVER['HTTP_HOST'] == 'localhost';
	}

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

	private static function setActiveModulePrefix($prefix)
	{
		self::$activeModulePrefix = $prefix;
	}

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

	static function getProjectHeaderPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	static function getProjectFooterPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	static function disable($moduleDirectoryPrefix)
	{
		self::removeGlobalSetting($moduleDirectoryPrefix, self::KEY_VERSION);
	}

	static function enable($moduleDirectoryPrefix, $version)
	{
		# Attempt to create an instance of the module before enabling it system wide.
		# This should catch problems like syntax errors in module code.
		$instance = self::getModuleInstance($moduleDirectoryPrefix, $version);

		self::initializeSettingDefaults($instance);

		self::setGlobalSetting($moduleDirectoryPrefix, self::KEY_VERSION, $version);
	}

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
			$value = json_encode($value);
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
		if((string) $value === (string) $oldValue){
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
			if($oldValue == null) {
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

	public static function getPrefixForID($id){
		$id = db_real_escape_string($id);

		$result = self::query("SELECT directory_prefix FROM redcap_external_modules WHERE external_module_id = '$id'");

		$row = db_fetch_assoc($result);
		if($row){
			return $row['directory_prefix'];
		}

		return null;
	}

	private static function query($sql)
	{
		$result = db_query($sql);

		if($result == FALSE){
			throw new Exception("Error running External Module query: \nDB Error: " . db_error() . "\nSQL: $sql");
		}

		return $result;
	}

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

	static function callHook($name, $arguments)
	{
		if(isset($_GET[self::DISABLE_EXTERNAL_MODULE_HOOKS])){
			return;
		}

		if(!defined(PAGE)){
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

	// Accepts a project id as the first parameter.
	// If the project id is null, all globally enabled module instances are returned.
	// Otherwise, only instances enabled for the current project id are returned.
	static function getEnabledModules($pid = null)
	{
		if($pid == null){
			return self::getGloballyEnabledVersions();
		}
		else{
			return self::getEnabledModuleVersionsForProject($pid);
		}
	}

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

	private static function cacheAllEnableData()
	{
		$globallyEnabledVersions = array();
		$projectEnabledOverrides = array();
		$projectEnabledDefaults = array();

		// Only attempt to detect enabled modules if the external module tables exist.
		if(self::areTablesPresent()){
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

	static function areTablesPresent()
	{
		$result = self::query("SHOW TABLES LIKE 'redcap_external_module%'");
		return db_num_rows($result) > 0;
	}

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

	private static function getPID()
	{
		return @$_GET['pid'];
	}

	private static function getUrl($prefix, $page)
	{
		$id = self::getIdForPrefix($prefix);
		$page = preg_replace('/\.php$/', '', $page); // remove .php extension if it exists
		return self::$BASE_URL . "?id=$id&page=$page";
	}

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

	static function getParseModuleDirectoryPrefixAndVersion($directoryName){
		$parts = explode('_', $directoryName);

		$version = array_pop($parts);
		$prefix = implode('_', $parts);

		return array($prefix, $version);
	}

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

	public static function getAdditionalFieldChoices($configRow,$pid) {
		if ($configRow['type'] == 'field-list') {
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

	public static function getEnabledVersion($prefix)
	{
		$versionsByPrefix = self::getGloballyEnabledVersions();
		return @$versionsByPrefix[$prefix];
	}

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

	## Duplicate of authorization code for REDCap, but with the check for NOAUTH removed
	public static function reauthorize()
	{
		global $auth_meth, $app_name, $username, $password, $hostname, $db, $institution, $double_data_entry,
			   $project_contact_name, $autologout_timer, $lang, $isMobileDevice, $password_reset_duration, $enable_user_whitelist,
			   $homepage_contact_email, $homepage_contact, $isAjax, $rc_autoload_function, $two_factor_auth_enabled;

		// Start the session before PEAR Auth does so we can check if auth session was lost or not (from load balance issues)
		if (!session_id())
		{
			session_start();
		}

		// Set default value to determine later if we need to make left-hand menu disappear so user has access to nothing
		$GLOBALS['no_access'] = 0;

		// If logging in, trim the username to prevent confusion and accidentally creating a new user
		if (isset($_POST['redcap_login_a38us_09i85']) && $auth_meth != "none")
		{
			// If we just reset a password, in which it is encrypted in the query string
			if (isset($_GET['epreset']) && $_POST['password'] == '') {
				$_POST['password'] = decrypt(base64_decode(rawurldecode(urldecode($_GET['epreset']))));
			}
			// Trim
			$_POST['username'] = trim($_POST['username']);
			// Make sure it's not longer than 255 characters to prevent attacks via hitting upper bounds
			if (strlen($_POST['username']) > 255) {
				$_POST['username'] = substr($_POST['username'], 0, 255);
			}
		}

		## AUTHENTICATE and GET USERNAME: Determine method of authentication
		// No authentication is used
		if ($auth_meth == 'none') {
			$userid = 'site_admin'; //Default user
		}
		// RSA SecurID two-factor authentication (using PHP Pam extension)
		elseif ($auth_meth == 'rsa') {
			// If username in session doesn't exist and not on login page, then force login
			if (!isset($_SESSION['rsa_username']) && !isset($_POST['redcap_login_a38us_09i85'])) {
				loginFunction();
			}
			// User is attempting to log in, so try to authenticate them using PAM
			elseif (isset($_POST['redcap_login_a38us_09i85']))
			{
				// Make sure RSA password is not longer than 14 characters to prevent attacks via hitting upper bounds
				// (8 char max for PIN + 6-digit tokencode)
				if (strlen($_POST['password']) > 14) {
					$_POST['password'] = substr($_POST['password'], 0, 14);
				}
				// If PHP PECL package PAM is not installed, then give error message
				if (!function_exists("pam_auth")) {
					if (isDev()) {
						// For development purposes only, allow passthru w/o valid authentication
						$userid = $_SESSION['username'] = $_SESSION['rsa_username'] = $_POST['username'];
					} else {
						// Display error
						renderPage(
								\RCView::div(array('class'=>'red'),
										\RCView::div(array('style'=>'font-weight:bold;'), $lang['global_01'].$lang['colon']) .
										"The PECL PAM package in PHP is not installed! The PAM package must be installed in order to use
								the pam_auth() function in PHP to authenticate tokens via RSA SecurID. You can find the offical
								documentation on PAM at <a href='http://pecl.php.net/package/PAM' target='_blank'>http://pecl.php.net/package/PAM</a>."
								)
						);
					}
				}
				// If have logged in, then try to authenticate the user
				elseif (pam_auth($_POST['username'], $_POST['password'], $err, false) === true) {
					$userid = $_SESSION['username'] = $_SESSION['rsa_username'] = $_POST['username'];
					// Log that they successfully logged in in log_view table
					\Logging::logPageView("LOGIN_SUCCESS", $userid);
					// Set the user's last_login timestamp
					\Authentication::setUserLastLoginTimestamp($userid);
				}
				// Error
				else {

					// Render error message and show login screen again
					print   \RCView::div(array('class'=>'red','style'=>'max-width:100%;width:100%;font-weight:bold;'),
							\RCView::img(array('src'=>'exclamation.png')) .
							"{$lang['global_01']}{$lang['colon']} {$lang['config_functions_49']}"
					);
					loginFunction();
				}
			}
			// If already logged in, the just set their username
			elseif (isset($_SESSION['rsa_username'])) {
				$userid = $_SESSION['username'] = $_SESSION['rsa_username'];
			}
		}
		// Shibboleth authentication (Apache module)
		elseif ($auth_meth == 'shibboleth') {
			// Check is custom username field is set for Shibboleth. If so, use it to determine username.
			$GLOBALS['shibboleth_username_field'] = trim($GLOBALS['shibboleth_username_field']);
			if (isDev()) {
				// For development purposes only, allow passthru w/o valid authentication
				$userid = $_SESSION['username'] = 'taylorr4';
			} elseif (strlen($GLOBALS['shibboleth_username_field']) > 0) {
				// Custom username field
				$userid = $_SESSION['username'] = $_SERVER[$GLOBALS['shibboleth_username_field']];
			} else {
				// Default value
				$userid = $_SESSION['username'] = $_SERVER['REMOTE_USER'];
			}
			// Update user's "last login" time if not yet updated for this session (for Shibboleth only since we can't know when users just logged in).
			// Only do this if coming from outside REDCap.
			if (!isset($_SERVER['HTTP_REFERER']) || (isset($_SERVER['HTTP_REFERER'])
							&& substr($_SERVER['HTTP_REFERER'], 0, strlen(APP_PATH_WEBROOT_FULL)) != APP_PATH_WEBROOT_FULL)
			) {
				\Authentication::setLastLoginTime($userid);
			}
		}
		// SAMS authentication (specifically used by the CDC)
		elseif ($auth_meth == 'sams') {
			// Hack for development testing
			// if (isDev() && isset($_GET['sams'])) {
			// $_SERVER['HTTP_EMAIL'] = 'rob.taylor@vanderbilt.edu';
			// $_SERVER['HTTP_FIRSTNAME'] = 'Rob';
			// $_SERVER['HTTP_LASTNAME'] = 'Taylor';
			// $_SERVER['HTTP_USERACCOUNTID'] = '0014787563';
			// }
			// Make sure we have all 4 HTTP headers from SAMS
			$http_headers = get_request_headers();
			if (isset($_SESSION['redcap_userid']) && !empty($_SESSION['redcap_userid']))
			{
				global $project_contact_email;
				// DEBUGGING: If somehow the userid in the header changes mid-session, end the sessino and email the administrator.
				if ($http_headers['Useraccountid'] != $_SESSION['redcap_userid'])
				{
					// Get user information and login info
					$userInfo = \User::getUserInfo($_SESSION['redcap_userid']);
					$userInfo2 = \User::getUserInfo($http_headers['Useraccountid']);
					$sql = "select ts from redcap_log_view where user = '".prep($_SESSION['redcap_userid'])."'
							and event = 'LOGIN_SUCCESS' order by log_view_id desc limit 1";
					$q = db_query($sql);
					$lastLoginTime = db_result($q, 0);
					// Build debug message
					$debugMsg = "<html><body style='font-family:arial,helvetica;font-size:10pt;'>
						An authentication error just occurred in REDCap. All relevant information is listed below.<br><br>
						<b>Current REDCap user: \"{$_SESSION['redcap_userid']}\" ({$userInfo['user_firstname']} {$userInfo['user_lastname']}, {$userInfo['user_email']})</b><br>
						 - Last login time for \"{$_SESSION['redcap_userid']}\": $lastLoginTime<br><br>
						REDCap just received an HTTP header with a *different* Useraccountid: <b>\"{$http_headers['Useraccountid']}\" ({$userInfo2['user_firstname']} {$userInfo2['user_lastname']}, {$userInfo2['user_email']})</b><br><br>
						Current server time (time of incident): ".NOW."<br>
						REDCap server: ".APP_PATH_WEBROOT_FULL."<br>
						Request method: ".$_SERVER['REQUEST_METHOD']."<br>
						Current request URL: ".$_SERVER['REQUEST_URI']."<br>
						Current REDCap project_id: ".(defined("PROJECT_ID") ? PROJECT_ID : "[none]")."
						<br><br><b>POST parameters (if a POST request):</b><br>".nl2br(print_r($_POST, true))."
						<br><br><b>HTTP HEADERS:</b><br>".nl2br(print_r($http_headers, true))."
						<br><br><b>REDCap session information:</b><br>".nl2br(print_r($_SESSION, true))."
						<br><br><b>REDCap cookies:</b><br>".nl2br(print_r($_COOKIE, true))."
						<br><br><b>SERVER variables:</b><br>".nl2br(print_r($_SERVER, true))."
						</body></html>";
					// Email session/request info to the administrator
					\REDCap::email($project_contact_email, $project_contact_email, 'REDCap/SAMS authentication error', $debugMsg);
					// End the session/force logout
					print  "<div style='padding:20px;color:#A00000;'><b>ERROR:</b> Your REDCap session has ended before the timeout.
							This happens occasionally and does not affect the projects or data.
							<br>You may <a href='{$GLOBALS['sams_logout']}'>click here to log in again</a>.</div>";
					// Log the logout
					\Logging::logPageView("LOGOUT", $_SESSION['redcap_userid']);
					// Destroy session and erase userid
					$_SESSION = array();
					session_unset();
					session_destroy();
					deletecookie('PHPSESSID');
					exit;
				}
				// Set the userid as the SAMS useraccountid value from the session
				$userid = $_SESSION['username'] = $_SESSION['redcap_userid'];
			}
			elseif (isset($http_headers['Useraccountid']) && isset($http_headers['Email']) && isset($http_headers['Firstname']) && isset($http_headers['Lastname'])) {
				// If we have the SAMS headers, add the sams user account id to PHP Session (to keep throughout this user's session to know they've already authenticated)
				$userid = $_SESSION['username'] = $_SESSION['redcap_userid'] = $http_headers['Useraccountid'];
				// Log that they successfully logged in in log_view table
				\Logging::logPageView("LOGIN_SUCCESS", $userid);
				// Set the user's last_login timestamp
				\Authentication::setUserLastLoginTimestamp($userid);
			}
			else {
				// Error: Could not find an existing session or the SAMS headers
				exit("{$lang['global_01']}{$lang['colon']} Your SAMS authentication session has ended. You may <a href='{$GLOBALS['sams_logout']}'>click here to log in again</a>.");
			}
		}
		// OpenID (general)
		elseif ($auth_meth == 'openid') {
			// Authenticate via OpenID provider
			$userid = \Authentication::authenticateOpenID();
			// Now redirect back to our original page in order to remove all the "openid..." parameters in the query string
			if (isset($_GET['openid_return_to'])) redirect(urldecode($_GET['openid_return_to']));
		}
		// OpenID (Google's Oauth2 - OpenID Connect)
		elseif ($auth_meth == 'openid_google') {
			// Authenticate via OpenID provider
			$userid = \Authentication::authenticateOpenIDGoogle();
			// Now redirect back to our original page in order to remove all the "openid..." parameters in the query string
			if (isset($_GET['openid_return_to'])) redirect(urldecode($_GET['openid_return_to']));
		}
		// Error was made in Control Center for authentication somehow
		elseif ($auth_meth == '') {
			if ($userid == '') {
				// If user is navigating directing to a project page but hasn't created their account info yet, redirect to home page.
				redirect(APP_PATH_WEBROOT_FULL);
			} else {
				// Project has no authentication somehow, which needs to be fixed in the Control Center.
				exit("{$lang['config_functions_20']}
					  <a target='_blank' href='". APP_PATH_WEBROOT . "ControlCenter/edit_project.php?project=".PROJECT_ID."'>REDCap {$lang['global_07']}</a>.");
			}
		}
		// Table-based and/or LDAP authentication
		else {
			// Set DSN arrays for Table-based auth and/or LDAP auth
			\Authentication::setDSNs();
			// This variable sets the timeout limit if server activity is idle
			$autologout_timer = ($autologout_timer == "") ? 0 : $autologout_timer;
			// In case of users having characters in password that were stripped out earlier, restore them (LDAP only)
			if (isset($_POST['password'])) $_POST['password'] = html_entity_decode($_POST['password'], ENT_QUOTES);
			// Check if user is logged in
			\Authentication::checkLogin("", $auth_meth);

			// Set username variable passed from PEAR Auth
			$userid = $_SESSION['username'];
			// Check if table-based user has a temporary password. If so, direct them to page to set it.
			if ($auth_meth == "table" || $auth_meth == "ldap_table")
			{
				$q = db_query("select * from redcap_auth where username = '".prep($userid)."'");
				$isTableBasedUser = db_num_rows($q);
				// User is table-based user
				if ($isTableBasedUser)
				{
					// Get values from auth table
					$temp_pwd 					= db_result($q, 0, 'temp_pwd');
					$password_question 			= db_result($q, 0, 'password_question');
					$password_answer 			= db_result($q, 0, 'password_answer');
					$password_question_reminder = db_result($q, 0, 'password_question_reminder');
					$legacy_hash 				= db_result($q, 0, 'legacy_hash');
					$hashed_password			= db_result($q, 0, 'password');
					$password_salt 				= db_result($q, 0, 'password_salt');
					$password_reset_key			= db_result($q, 0, 'password_reset_key');

					// Check if need to trigger setup for SECURITY QUESTION (only on My Projects page or project's Home/Project Setup page)
					$myProjectsUri = "/index.php?action=myprojects";
					$pagePromptSetSecurityQuestion = (substr($_SERVER['REQUEST_URI'], strlen($myProjectsUri)*-1) == $myProjectsUri || PAGE == 'index.php' || PAGE == 'ProjectSetup/index.php');
					$conditionPromptSetSecurityQuestion = (!($two_factor_auth_enabled && !isset($_SESSION['two_factor_auth']) && !isset($_SESSION['two_factor_auth_bypass_login']))
							&& !isset($_POST['redcap_login_a38us_09i85']) && !$isAjax && empty($password_question)
							&& (empty($password_question_reminder) || NOW > $password_question_reminder));
					if ($pagePromptSetSecurityQuestion && $conditionPromptSetSecurityQuestion)
					{
						// Set flag to display pop-up dialog to set up security question
						define("SET_UP_SECURITY_QUESTION", true);
					}

					// If using table-based auth and enforcing password reset after X days, check if need to reset or not
					if (isset($_POST['redcap_login_a38us_09i85']) && !empty($password_reset_duration))
					{
						// Also add to auth_history table
						$sql = "select timestampdiff(MINUTE,timestamp,'".NOW."')/60/24 as daysExpired,
								timestampadd(DAY,$password_reset_duration,timestamp) as expirationTime from redcap_auth_history
								where username = '$userid' order by timestamp desc limit 1";
						$q = db_query($sql);
						$daysExpired = db_result($q, 0, "daysExpired");
						$expirationTime = db_result($q, 0, "expirationTime");

						// If the number of days expired has passed, then redirect them to the password reset page
						if (db_num_rows($q) > 0 && $daysExpired > $password_reset_duration)
						{
							// Set the temp password flag to prompt them to enter new password
							db_query("UPDATE redcap_auth SET temp_pwd = 1 WHERE username = '$userid'");
							// Redirect to password reset page with flag set
							redirect(APP_PATH_WEBROOT . "Authentication/password_reset.php?msg=expired");
						}
						// If within 7 days of expiring, then give a notice on next page load.
						elseif ($daysExpired > $password_reset_duration-7)
						{
							// Put expiration time in session in order to prompt user on next page load
							$_SESSION['expire_time'] = \DateTimeRC::format_ts_from_ymd($expirationTime);
						}
					}

					// PASSWORD RESET (non-email): If temporary password flag is set, then redirect to allow user to set new password
					if ($temp_pwd == '1' && PAGE != "Authentication/password_reset.php")
					{
						redirect(APP_PATH_WEBROOT . "Authentication/password_reset.php" . ((isset($app_name) && $app_name != "") ? "?pid=" . PROJECT_ID : ""));
					}

					// UPDATE LEGACY PASSWORD HASH: If table-based user is logging in (successfully) and is using a legacy hashed password,
					// then update password to newer salted hash.
					if (isset($_POST['redcap_login_a38us_09i85']) && $legacy_hash && md5($_POST['password'].$password_salt) == $hashed_password)
					{
						// Generate random salt for this user
						$new_salt = \Authentication::generatePasswordSalt();
						// Create the one-way hash for this new password
						$new_hashed_password = \Authentication::hashPassword($_POST['password'], $new_salt);
						// Update a table-based user's hashed password and salt
						\Authentication::setUserPasswordAndSalt($userid, $new_hashed_password, $new_salt);
					}
				}
			}
		}

		// Reset autoload function in case one of the authentication frameworks changed it
		spl_autoload_register($rc_autoload_function);

		// If $userid is somehow blank (e.g., authentication server is down), then prevent from accessing.
		if (trim($userid) == '')
		{
			// If using Shibboleth authentication and user is on API Help page but somehow lost their username
			// (or can't be used in /api directory due to Shibboleth setup), then just redirect to the target page itself.
			if ($auth_meth == 'shibboleth' && strpos(PAGE_FULL, '/api/help/index.php') !== false) {
				redirect(APP_PATH_WEBROOT . "API/help.php");
			}
			// Display error message
			$objHtmlPage = new \HtmlPage();
			$objHtmlPage->addStylesheet("style.css", 'screen,print');
			$objHtmlPage->addStylesheet("home.css", 'screen,print');
			$objHtmlPage->PrintHeader();
			print \RCView::br() . \RCView::br()
					. \RCView::errorBox($lang['config_functions_82']." <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}")
					. \RCView::button(array('onclick'=>"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';"), "Try again");
			$objHtmlPage->PrintFooter();
			exit;
		}

		// LOGOUT: Check if need to log out
		\Authentication::checkLogout();

		// USER WHITELIST: If using external auth and user whitelist is enabled, the validate user as in whitelist
		if ($enable_user_whitelist && $auth_meth != 'none' && $auth_meth != 'table')
		{
			// The user has successfully logged in, so determine if they're an external auth user
			$isExternalUser = ($auth_meth != "ldap_table" || ($auth_meth == "ldap_table" && isset($isTableBasedUser) && !$isTableBasedUser));
			// They're an external auth user, so make sure they're in the whitelist
			if ($isExternalUser)
			{
				$sql = "select 1 from redcap_user_whitelist where username = '" . prep($userid) . "'";
				$inWhitelist = db_num_rows(db_query($sql));
				// If not in whitelist, then give them error page
				if (!$inWhitelist)
				{
					// Give notice that user cannot access REDCap
					$objHtmlPage = new \HtmlPage();
					$objHtmlPage->addStylesheet("style.css", 'screen,print');
					$objHtmlPage->addStylesheet("home.css", 'screen,print');
					$objHtmlPage->PrintHeader();
					print  "<div class='red' style='margin:40px 0 20px;padding:20px;'>
								{$lang['config_functions_78']} \"<b>$userid</b>\"{$lang['period']}
								{$lang['config_functions_79']} <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}
							</div>
							<button onclick=\"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';\">Go back</button>";
					$objHtmlPage->PrintFooter();
					exit;
				}
			}
		}

		// If logging in, update Last Login time in user_information table
		// (but NOT if they are suspended - could be confusing if last login occurs AFTER suspension)
		if (isset($_POST['redcap_login_a38us_09i85']))
		{
			\Authentication::setUserLastLoginTimestamp($userid);
		}

		// If just logged in, redirect back to same page to avoid $_POST confliction on certain pages.
		// Do NOT simply redirect if user lost their session when saving data so that their data will be resurrected.
		if (isset($_POST['redcap_login_a38us_09i85']) && !isset($_POST['redcap_login_post_encrypt_e3ai09t0y2']))
		{
			// Set URL of redirect-to page
			$url = $_SERVER['REQUEST_URI'];
			// If user is logging into main Home page, then redirect them to My Projects page if they've had access to REDCap for > 7 days
			if ($_SERVER['REQUEST_URI'] == APP_PATH_WEBROOT_PARENT || $_SERVER['REQUEST_URI'] == APP_PATH_WEBROOT_PARENT."index.php") {
				// Get user info
				$row = \User::getUserInfo($userid);
				// Was their first visit > 7 days ago?
				if ($row['user_firstvisit'] != "" && (time() - strtotime($row['user_firstvisit']))/3600/24 > 7) {
					// Set redirect URL as My Projects page
					$url = APP_PATH_WEBROOT_PARENT."index.php?action=myprojects";
				}
			}
			// Redirect to same page
			redirect($url);
		}

		// CHECK USER INFO: Make sure that we have the user's email address and name in redcap_user_information. If not, prompt user for it.
		if (PAGE != "Profile/user_info_action.php" && PAGE != "Authentication/password_reset.php") {
			// Set super_user default value
			$super_user = $account_manager = 0;
			// Get user info
			$row = \User::getUserInfo($userid);
			// If user has no email address or is not in user_info table, then prompt user for their name and email
			if (empty($row) || $row['user_email'] == "" || ($row['user_email'] != "" && $row['email_verify_code'] != "")) {
				// Prompt user for values
				include APP_PATH_DOCROOT . "Profile/user_info.php";
				exit;
			} else {
				// Define user's name and email address for use throughout the application
				$user_email 	= $row['user_email'];
				$user_phone 	= $row['user_phone'];
				$user_phone_sms 	= $row['user_phone_sms'];
				$user_firstname = $row['user_firstname'];
				$user_lastname 	= $row['user_lastname'];
				$super_user 	= $row['super_user'];
				$account_manager 	= $super_user ? 0 : $row['account_manager'];
				$user_firstactivity = $row['user_firstactivity'];
				$user_lastactivity = $row['user_lastactivity'];
				$user_firstvisit = $row['user_firstvisit'];
				$user_lastlogin = $row['user_lastlogin'];
				$user_access_dashboard_view = $row['user_access_dashboard_view'];
				$allow_create_db 	= $row['allow_create_db'];
				$datetime_format 	= $row['datetime_format'];
				$number_format_decimal = $row['number_format_decimal'];
				$ui_state = ($row['ui_state'] == "") ? array() : unserialize($row['ui_state']);
				// If thousands separator is blank, then assume a space (since MySQL cannot do a space for an ENUM data type)
				$number_format_thousands_sep = ($row['number_format_thousands_sep'] == 'SPACE') ? ' ' : $row['number_format_thousands_sep'];
				// Do not let the secondary/tertiary emails be set unless they have been verified first
				$user_email2 	= ($row['user_email2'] != '' && $row['email2_verify_code'] == '') ? $row['user_email2'] : "";
				$user_email3 	= ($row['user_email3'] != '' && $row['email3_verify_code'] == '') ? $row['user_email3'] : "";
			}
			// TWO-FACTOR AUTHENTICATION: Add user's two factor auth secret hash
			if ($row['two_factor_auth_secret'] == "")
			{
				$row['two_factor_auth_secret'] = \Authentication::createTwoFactorSecret($userid);
			}
			// If we have not recorded time of user's first visit, then set it
			if ($row['user_firstvisit'] == "")
			{
				\User::updateUserFirstVisit($userid);
			}
			// If we have not recorded time of user's last login, then set it based upon first page view of current session
			if ($row['user_lastlogin'] == "")
			{
				\Authentication::setLastLoginTime($userid);
			}
			// Check if user account has been suspended
			if ($row['user_suspended_time'] != "")
			{
				// Give notice that user cannot access REDCap
				global $homepage_contact_email, $homepage_contact;
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->addStylesheet("style.css", 'screen,print');
				$objHtmlPage->addStylesheet("home.css", 'screen,print');
				$objHtmlPage->PrintHeader();
				$user_firstlast = ($user_firstname == "" && $user_lastname == "") ? "" : " (<b>$user_firstname $user_lastname</b>)";
				print  "<div class='red' style='margin:40px 0 20px;padding:20px;'>
							{$lang['config_functions_75']} \"<b>$userid</b>\"{$user_firstlast}{$lang['period']}
							{$lang['config_functions_76']} <a href='mailto:$homepage_contact_email'>$homepage_contact</a>{$lang['period']}
						</div>
						<button onclick=\"window.location.href='".APP_PATH_WEBROOT_FULL."index.php?logout=1';\">Go back</button>";
				$objHtmlPage->PrintFooter();
				exit;
			}

		}

		//Define user variables
		defined("USERID") or define("USERID", $userid);
		define("SUPER_USER", $super_user);
		define("ACCOUNT_MANAGER", $account_manager);
		$GLOBALS['userid'] = $userid;
		$GLOBALS['super_user'] = $super_user;
		$GLOBALS['account_manager'] = $account_manager;
		$GLOBALS['user_email'] = $user_email;
		$GLOBALS['user_email2'] = $user_email2;
		$GLOBALS['user_email3'] = $user_email3;
		$GLOBALS['user_phone'] = $user_phone;
		$GLOBALS['user_phone_sms'] = $user_phone_sms;
		$GLOBALS['user_firstname'] = $user_firstname;
		$GLOBALS['user_lastname'] = $user_lastname;
		$GLOBALS['user_firstactivity'] = $user_firstactivity;
		$GLOBALS['user_access_dashboard_view'] = $user_access_dashboard_view;
		$GLOBALS['allow_create_db'] = $allow_create_db;
		$GLOBALS['datetime_format'] = $datetime_format;
		$GLOBALS['number_format_decimal'] = $number_format_decimal;
		$GLOBALS['number_format_thousands_sep'] = $number_format_thousands_sep;
		$GLOBALS['ui_state'] = $ui_state;

		## DEAL WITH COOKIES
		// Remove authchallenge cookie created by Pear Auth because it's not necessary
		if (isset($_COOKIE['authchallenge'])) {
			unset($_COOKIE['authchallenge']);
			deletecookie('authchallenge');
		}

		## TWO FACTOR AUTHENTICATION
		// Enforce 2FA here if enabled and user has not authenticated via two factor
		if (\Authentication::checkToDisplayTwoFactorLoginPage()) {
			// Display the two-factor login screen
			\Authentication::renderTwoFactorLoginPage();
		}
		// If a user is inside a "Force 2FA" project and hasn't done a 2FA login during this session (because the trust cookie was used).
		if (\Authentication::enforceTwoFactorByManualForceProject()) {
			// Display the two-factor login screen
			\Authentication::renderTwoFactorLoginPage(true);
		}
		// If user bypassed the 2FA login (due to cookie or IP), then make sure their 1-step login
		// got logged (because we didn't log it when it happened prior to 2FA detection)
		if ($auth_meth != 'none' && $two_factor_auth_enabled && !isset($_SESSION['two_factor_auth'])
				&& !isset($_SESSION['two_factor_auth_bypass_login']) && !in_array(PAGE, \Authentication::getTwoFactorWhitelistedPages())) {
			// Set flag so that we know we logged their 1-step login
			$_SESSION['two_factor_auth_bypass_login'] = "1";
			// Log the login
			\Logging::logPageView("LOGIN_SUCCESS", USERID, null, true);
		}
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
		if (is_int($instance)) {
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

			#single-element JSONs are simply data values
			if (count($json) == 1) {
				self::setSetting($prefix, $projectId, $key, $json[0]);
			} else {
				self::setSetting($prefix, $projectId, $key, $json);
			}
		}
	}
}
