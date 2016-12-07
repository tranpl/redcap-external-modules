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
require_once __DIR__ . "/../../redcap_connect.php";

use \Exception;

class ExternalModules
{
	const GLOBAL_SETTING_PROJECT_ID = 'NULL';
	const KEY_VERSION = 'version';
	const KEY_ENABLED = 'enabled';

	const DISABLE_EXTERNAL_MODULE_HOOKS = 'disable-external-module-hooks';

	const OVERRIDE_PERMISSION_LEVEL_SUFFIX = '_override-permission-level';
	const OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS = 'design';

	public static $BASE_URL;
	public static $MODULES_URL;
	public static $MODULES_PATH;

	private static $initialized = false;
	private static $moduleBeingLoaded;
	private static $instanceCache = array();

	private static $enabledVersions;
	private static $globallyEnabledPrefixes;
	private static $projectEnabledOverrides;
	private static $enabledInstancesByPID = array();

	private static $RESERVED_SETTINGS = array(
		'global-settings' => array(
			self::KEY_VERSION => false, // False will cause this setting to be checked (to avoid modules from using it), but it will not actually be displayed.
			self::KEY_ENABLED => array(
				'name' => 'Enable on projects by default',
				'project-name' => 'Enabled',
				'type' => 'checkbox',
				'allow-project-overrides' => true,
			)
		),
	);

	static function initialize()
	{
		if($_SERVER[HTTP_HOST] == 'localhost'){
			// Assume this is a developer's machine and enable errors.
			ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL);
		}

		self::$BASE_URL = APP_PATH_WEBROOT . '../external_modules/';
		self::$MODULES_URL = APP_PATH_WEBROOT . '../modules/';
		self::$MODULES_PATH = __DIR__ . "/../../modules/";

		register_shutdown_function(function(){
			$moduleBeingIncluded = self::$moduleBeingLoaded;
			if($moduleBeingIncluded != null){
				// We can't just call disable() from here because the database connection has been destroyed.
				// Disable this module via AJAX instead.
				?>
				<br>
				<h4 id="external-modules-message">
					A fatal error occurred while loading the "<?=$moduleBeingIncluded?>" external module.<br>
					Disabling that module...
				</h4>
				<script>
					var request = new XMLHttpRequest();
					request.onreadystatechange = function() {
						if (request.readyState == XMLHttpRequest.DONE ) {
							var messageElement = document.getElementById('external-modules-message')
							if(request.responseText == 'success'){
								messageElement.innerHTML = 'The "<?=$moduleBeingIncluded?>" external module was automatically disabled in order to allow REDCap to function properly.  Please fix the above error before re-enabling the module.';
							}
							else{
								messageElement.innerHTML += '<br>An error occurred while disabling the "<?=$moduleBeingIncluded?>" module: ' + request.responseText;
							}
						}
					};

					request.open("POST", "<?=self::$BASE_URL?>/manager/ajax/disable-module.php?<?=self::DISABLE_EXTERNAL_MODULE_HOOKS?>");
					request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
					request.send("module=<?=$moduleBeingIncluded?>");
				</script>
				<?php
			}
		});
	}

	static function getProjectHeaderPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	static function getProjectFooterPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	static function getEnabledModules()
	{
		$result = self::getGlobalSettings(null, array(self::KEY_VERSION));

		$modules = array();
		while($row = db_fetch_assoc($result)){
			$modules[$row['directory_prefix']] = $row['value'];
		}

		return $modules;
	}

	static function disable($moduleDirectoryPrefix)
	{
		self::setGlobalSetting($moduleDirectoryPrefix, self::KEY_VERSION, false);
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
		foreach($config['global-settings'] as $key=>$details){
			$default = @$details['default'];
			$existingValue = $moduleInstance->getGlobalSetting($key);
			if(isset($default) && $existingValue == null){
				$moduleInstance->setGlobalSetting($key, $default);
			}
		}
	}

	static function getGlobalSetting($moduleDirectoryPrefix, $key)
	{
		return self::getProjectSetting($moduleDirectoryPrefix, self::GLOBAL_SETTING_PROJECT_ID, $key);
	}

	static function getGlobalSettings($moduleDirectoryPrefixes, $keys = null)
	{
		return self::getProjectSettings($moduleDirectoryPrefixes, array(self::GLOBAL_SETTING_PROJECT_ID), $keys);
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
		if($value === false){
			// False gets translated to an empty string by db_real_escape_string().
			// We much change this value to 0 for it to actually be saved.
			$value = 0;
		}

		$externalModuleId = self::getExternalModuleId($moduleDirectoryPrefix);

		$projectId = db_real_escape_string($projectId);
		$key = db_real_escape_string($key);
		$value = db_real_escape_string($value);

		$oldValue = self::getProjectSetting($moduleDirectoryPrefix, $projectId, $key);
		if($value == $oldValue){
			// We don't need to do anything.
			return;
		}
		else if($value == null){
			$event = "DELETE";
			$sql = "DELETE FROM redcap_external_module_settings
					WHERE
						external_module_id = $externalModuleId
						AND " . self::getSqlEqualClause('project_id', $projectId) . "
						AND `key` = '$key'";
		}
		else if($oldValue == null) {
			$event = "INSERT";
			$sql = "INSERT INTO redcap_external_module_settings
					VALUES
					(
						$externalModuleId,
						$projectId,
						'$key',
						'$value'
					)";
		}
		else {
			$event = "UPDATE";
			$sql = "UPDATE redcap_external_module_settings
					SET value = '$value'
					WHERE
						external_module_id = $externalModuleId
						AND " . self::getSqlEqualClause('project_id', $projectId) . "
						AND `key` = '$key'";
		}

		self::query($sql);
		$affectedRows = db_affected_rows();

		$description = ucfirst(strtolower($event)) . ' External Module setting';
		log_event($sql, 'redcap_external_module_settings', $event, $key, $value, $description, "", "", $projectId);

		if($affectedRows != 1){
			throw new Exception("Unexpected number of affected rows ($affectedRows) on External Module setting query: $sql");
		}
	}

	static function getGlobalAndProjectSettingsAsArray($moduleDirectoryPrefixes, $projectId)
	{
		$result = self::getProjectSettings($moduleDirectoryPrefixes, array(self::GLOBAL_SETTING_PROJECT_ID, $projectId));

		$settings = array();
		while($row = db_fetch_assoc($result)){
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

	static function getProjectSettings($moduleDirectoryPrefixes, $projectIds, $keys = array())
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

		return self::query("SELECT directory_prefix, s.project_id, s.project_id, s.key, s.value
							FROM redcap_external_modules m
							JOIN redcap_external_module_settings s
								ON m.external_module_id = s.external_module_id
							WHERE " . implode(' AND ', $whereClauses));
	}

	static function getProjectSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		$result = self::getProjectSettings(array($moduleDirectoryPrefix), array($projectId), array($key));

		$numRows = db_num_rows($result);
		if($numRows == 1){
			$row = db_fetch_assoc($result);
			return $row['value'];
		}
		else if($numRows == 0){
			return null;
		}
		else{
			throw new Exception("More than one External Module setting exists for project $projectId and key '$key'!  This should never happen!");
		}
	}

	static function removeProjectSetting($moduleDirectoryPrefix, $projectId, $key){
		self::setProjectSetting($moduleDirectoryPrefix, $projectId, $key, null);
	}

	private static function getExternalModuleId($moduleDirectoryPrefix)
	{
		$moduleDirectoryPrefix = db_real_escape_string($moduleDirectoryPrefix);

		$result = self::query("SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix = '$moduleDirectoryPrefix'");

		$row = db_fetch_assoc($result);
		if($row){
			return $row['external_module_id'];
		}
		else{
			self::query("INSERT INTO redcap_external_modules (directory_prefix) VALUES ('$moduleDirectoryPrefix')");
			return db_insert_id();
		}
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

		// REDCap may call hooks for a project in cases where the pid get param is not set.
		// If the pid was passed as an argument, use it.
		if(!empty($arguments) && gettype($arguments) == 'integer'){
			// As of REDCap 6.16.8, the above checks allow us to safely assume the first arg is the pid for all hooks.
			$pid = $arguments[0];
		}

		if(!isset($pid)){
			// Fallback to the pid get param.
			// This is required in cases where we're calling a hook that's not project specific on a project specific page.
			// ex: calling every_page_top on the project homepage
			$pid = @$_GET['pid'];
		}

		$modules = self::getEnabledModulesForProject($pid);
		foreach($modules as $instance){
			$methodName = "hook_$name";

			if(method_exists($instance, $methodName)){
				if(!$instance->hasPermission($methodName)){
					throw new Exception("The \"" . $instance->PREFIX . "\" external module must request permission in order to define the following hook: $methodName()");
				}

				call_user_func_array(array($instance,$methodName), $arguments);
			}
		}
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
		self::$moduleBeingLoaded = $prefix;

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

			$instance = new $classNameWithNamespace($prefix, $version);
			self::$instanceCache[$moduleDirectoryName] = $instance;
		}

		self::$moduleBeingLoaded = null;

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

	private static function getEnabledModulesForProject($pid)
	{
		$instances = @self::$enabledInstancesByPID[$pid];
		if(!isset($instances)){
			$enabledPrefixes = self::getEnabledModulePrefixesForProject($pid);

			$instances = array();
			foreach(array_keys($enabledPrefixes) as $prefix){
				$instances[] = self::getModuleInstance($prefix, self::$enabledVersions[$prefix]);
			}

			self::$enabledInstancesByPID[$pid] = $instances;
		}

		return $instances;
	}

	private static function getEnabledModulePrefixesForProject($pid)
	{
		if(!isset(self::$enabledVersions)){
			self::cacheAllEnableData();
		}

		$enabledPrefixes = self::$globallyEnabledPrefixes;
		$projectPrefixes = @self::$projectEnabledOverrides[$pid];
		if(isset($projectPrefixes)){
			foreach($projectPrefixes as $prefix => $value){
				if($value == 1){
					$enabledPrefixes[$prefix] = true;
				}
				else{
					unset($enabledPrefixes[$prefix]);
				}
			}
		}

		return $enabledPrefixes;
	}

	private static function cacheAllEnableData()
	{
		$result = self::getProjectSettings(null, null, array(self::KEY_VERSION, self::KEY_ENABLED));

		$enabledVersions = array();
		$projectEnabledOverrides = array();
		$globallyEnabledPrefixes = array();
		while($row = db_fetch_assoc($result)){
			$pid = $row['project_id'];
			$prefix = $row['directory_prefix'];
			$key = $row['key'];
			$value = $row['value'];

			if($key == self::KEY_VERSION){
				$enabledVersions[$prefix] = $value;
			}
			else if($key == self::KEY_ENABLED){
				if(isset($pid)){
					$projectEnabledOverrides[$pid][$prefix] = $value;
				}
				else if($value == 1) {
					$globallyEnabledPrefixes[$prefix] = true;
				}
			}
			else{
				throw new Exception("Unexpected key: $key");
			}
		}

		// Overwrite any previously cached results
		self::$enabledVersions = $enabledVersions;
		self::$globallyEnabledPrefixes = $globallyEnabledPrefixes;
		self::$projectEnabledOverrides = $projectEnabledOverrides;
		self::$enabledInstancesByPID = array();
	}

	static function addResource($path)
	{
		$path = "manager/$path";
		$fullLocalPath = __DIR__ . "/../$path";
		$extension = pathinfo($fullLocalPath, PATHINFO_EXTENSION);

		// Add the filemtime to the url for cache busting.
		$url = ExternalModules::$BASE_URL . $path . '?' . filemtime($fullLocalPath);

		if ($extension == 'css') {
			echo "<link rel='stylesheet' type='text/css' href='" . $url . "'>";
		}
		else {
			throw new Exception('Unsupported resource added: ' . $path);
		}
	}

	static function getControlCenterLinks(){
		$links = self::getLinks('control-center');

		$links['Manage External Modules'] = array(
			'icon' => 'brick',
			'url' => ExternalModules::$BASE_URL  . 'manager/control_center.php'
		);

		ksort($links);

		return $links;
	}

	static function getProjectLinks(){
		$links = self::getLinks('project');

		if(self::hasDesignRights()){
			$links['Manage External Modules'] = array(
				'icon' => 'brick',
				'url' => ExternalModules::$BASE_URL  . 'manager/project.php'
			);
		}

		ksort($links);

		return $links;
	}

	private function getLinks($type){
		# TODO - This data will likely end up coming from enabled modules in the database instead in the future.

		$links = array();

		$modules = self::getEnabledModules();
		foreach($modules as $prefix=>$version){
			$config = self::getConfig($prefix, $version);

			foreach($config['links'][$type] as $name=>$link){
				$link['url'] = self::$MODULES_URL . self::getModuleDirectoryName($prefix, $version) . '/' . $link['url'];
				$links[$name] = $link;
			}
		}

		ksort($links);

		return $links;
	}

	static function getDisabledModuleConfigs()
	{
		$enabledModules = self::getEnabledModules();
		$dirs = scandir(self::$MODULES_PATH);

		$disabledModuleVersions = array();
		foreach ($dirs as $dir) {
			if ($dir[0] == '.') {
				continue;
			}

			list($prefix, $version) = self::getParseModuleDirectoryPrefixAndVersion($dir);

			if(!isset($enabledModules[$prefix])) {
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

	static function getConfig($prefix, $version)
	{
		$moduleDirectoryName = self::getModuleDirectoryName($prefix, $version);
		$config = json_decode(file_get_contents(self::$MODULES_PATH . "$moduleDirectoryName/config.json"), true);

		if($config == NULL){
			throw new Exception("An error occurred while parsing configuration file for the \"$prefix\" module!  It is likely not valid JSON.");
		}

		return self::addReservedSettings($config);
	}

	private static function addReservedSettings($config)
	{
		foreach(self::$RESERVED_SETTINGS as $type=>$reservedSettings){
			$visibleReservedSettings = array();

			$configSettings = @$config[$type];
			if(!isset($configSettings)){
				$configSettings = array();
			}

			foreach($reservedSettings as $key=>$details){
				if(isset($configSettings[$key])){
					throw new Exception("The '$key' setting key is reserved for internal use.  Please use a different setting key in your module.");
				}

				if($details){
					$visibleReservedSettings[$key] = $details;
				}
			}

			// Merge arrays so that reserved settings always end up at the top of the list.
			$config[$type] = array_merge_recursive($visibleReservedSettings, $configSettings);
		}

		return $config;
	}

	private static function getModuleDirectoryName($prefix, $version){
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

	static function isGlobalSetting($moduleDirectoryPrefix, $key)
	{
		$version = self::getGlobalSetting($moduleDirectoryPrefix, self::KEY_VERSION);
		$instance = self::getModuleInstance($moduleDirectoryPrefix, $version);
		return isset($instance->getConfig()['global-settings'][$key]);
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
		return SUPER_USER;
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
}

