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
	private static $activeModulePrefix;
	private static $instanceCache = array();
	private static $idsByPrefix;

	private static $globallyEnabledVersions;
	private static $projectEnabledDefaults;
	private static $projectEnabledOverrides;
	private static $enabledInstancesByPID = array();

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

	static function initialize()
	{
		if($_SERVER[HTTP_HOST] == 'localhost'){
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

		register_shutdown_function(function(){
			$activeModulePrefix = self::getActiveModulePrefix();
			if($activeModulePrefix != null){
				$error = error_get_last();
				var_dump($error);
				$message = "The '$activeModulePrefix' module was automatically disabled because of the following error:\n\n";
				$message .= 'Error Message: ' . $error['message'] . "\n";
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

	private static function setSetting($moduleDirectoryPrefix, $projectId, $key, $value)
	{
		if($value === false){
			// False gets translated to an empty string by db_real_escape_string().
			// We much change this value to 0 for it to actually be saved.
			$value = 0;
		}

		$externalModuleId = self::getIdForPrefix($moduleDirectoryPrefix);

		$projectId = db_real_escape_string($projectId);
		$key = db_real_escape_string($key);
		$value = db_real_escape_string($value);

		$oldValue = self::getSetting($moduleDirectoryPrefix, $projectId, $key);
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

	static function getProjectSettingsAsArray($moduleDirectoryPrefixes, $projectId)
	{
		$result = self::getSettings($moduleDirectoryPrefixes, array(self::GLOBAL_SETTING_PROJECT_ID, $projectId));

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

		return self::query("SELECT directory_prefix, s.project_id, s.project_id, s.key, s.value
							FROM redcap_external_modules m
							JOIN redcap_external_module_settings s
								ON m.external_module_id = s.external_module_id
							WHERE " . implode(' AND ', $whereClauses));
	}

	private static function getSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		$result = self::getSettings($moduleDirectoryPrefix, $projectId, $key);

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

		$modules = self::getEnabledModuleInstances($pid);
		foreach($modules as $instance){
			$methodName = "hook_$name";

			if(method_exists($instance, $methodName)){
				if(!$instance->hasPermission($methodName)){
					throw new Exception("The \"" . $instance->PREFIX . "\" external module must request permission in order to define the following hook: $methodName()");
				}

				self::setActiveModulePrefix($instance->PREFIX);
				try{
					call_user_func_array(array($instance,$methodName), $arguments);
				}
				catch(Exception $e){
					$message = "The '" . $instance->PREFIX . "' module threw the following exception when calling the hook method '$methodName':\n\n" . $e;
					error_log($message);
					ExternalModules::sendAdminEmail("REDCap External Module Hook Exception - $instance->PREFIX", $message);
				}
				self::setActiveModulePrefix(null);
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
	private static function getEnabledModuleInstances($pid)
	{
		$instances = @self::$enabledInstancesByPID[$pid];
		if(!isset($instances)){
			if($pid == null){
				// Cache globally enabled module instances.  Yes, the caching will still work even though the key ($pid) is null.
				$prefixes = self::getGloballyEnabledVersions();
			}
			else{
				$prefixes = self::getEnabledModuleVersionsForProject($pid);
			}

			$instances = array();
			foreach($prefixes as $prefix=>$version){
				$instances[] = self::getModuleInstance($prefix, $version);
			}

			self::$enabledInstancesByPID[$pid] = $instances;
		}

		return $instances;
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
				if($value == 1){
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

	private static function cacheAllEnableData()
	{
		$globallyEnabledVersions = array();
		$projectEnabledOverrides = array();
		$projectEnabledDefaults = array();

		// Only attempt to detect enabled modules if the external module tables exist.
		if(self::areTablesPresent()){
			$result = self::getSettings(null, null, array(self::KEY_VERSION, self::KEY_ENABLED));
			while($row = db_fetch_assoc($result)){
				$pid = $row['project_id'];
				$prefix = $row['directory_prefix'];
				$key = $row['key'];
				$value = $row['value'];

				if($key == self::KEY_VERSION){
					$globallyEnabledVersions[$prefix] = $value;
				}
				else if($key == self::KEY_ENABLED){
					if(isset($pid)){
						$projectEnabledOverrides[$pid][$prefix] = $value;
					}
					else if($value == 1) {
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
		self::$enabledInstancesByPID = array();
	}

	static function areTablesPresent()
	{
		$result = self::query("SHOW TABLES LIKE 'redcap_external_module%'");
		return db_num_rows($result) > 0;
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
		$links = self::getLinks();

		$links['Manage External Modules'] = array(
			'icon' => 'brick',
			'url' => ExternalModules::$BASE_URL  . 'manager/control_center.php'
		);

		ksort($links);

		return $links;
	}

	static function getProjectLinks($pid){
		$links = self::getLinks($pid);

		if(self::hasDesignRights()){
			$links['Manage External Modules'] = array(
				'icon' => 'brick',
				'url' => ExternalModules::$BASE_URL  . 'manager/project.php?'
			);
		}

		ksort($links);

		return $links;
	}

	private function getLinks($pid = null){
		if(isset($pid)){
			$type = 'project';
		}
		else{
			$type = 'control-center';
		}

		$links = array();

		$modules = self::getEnabledModuleInstances($pid);
		foreach($modules as $instance){
			$config = $instance->getConfig();

			foreach($config['links'][$type] as $link){
				$name = $link['name'];
				$link['url'] = self::getUrl($instance->PREFIX, $link['url']);
				$links[$name] = $link;
			}
		}

		ksort($links);

		return $links;
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

