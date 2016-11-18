<?php
namespace ExternalModules;

if (!defined(__DIR__)){
	define(__DIR__, dirname(__FILE__));
}

require_once __DIR__ . "/AbstractExternalModule.php";
require_once __DIR__ . "/../../redcap_connect.php";

use \Exception;

class ExternalModules
{
	public static $BASE_URL;
	public static $MODULES_URL;
	public static $MODULES_PATH;

	private static $initialized = false;
	private static $moduleBeingLoaded = null;

	const GLOBAL_SETTING_PROJECT_ID = 'NULL';
	const KEY_ENABLED = 'enabled';

	const DISABLE_EXTERNAL_MODULE_HOOKS = 'disable-external-module-hooks';

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

	static function getEnabledModuleNames()
	{
		$result = self::getGlobalSettings(null, array(self::KEY_ENABLED));

		$names = array();
		while($row = db_fetch_assoc($result)){
			$names[] = $row['directory_name'];
		}

		return $names;
	}

	static function disable($moduleDirectoryName)
	{
		self::setGlobalSetting($moduleDirectoryName, self::KEY_ENABLED, false);
	}

	static function enable($moduleDirectoryName)
	{
		# Attempt to load the module before enabling it system wide.
		# This should catch problems like syntax errors in module code.
		self::getModuleInstance($moduleDirectoryName);

		self::setGlobalSetting($moduleDirectoryName, self::KEY_ENABLED, true);
	}

	private static function getGlobalSetting($moduleDirectoryName, $key)
	{
		return self::getProjectSetting($moduleDirectoryName, self::GLOBAL_SETTING_PROJECT_ID, $key);
	}

	private static function getGlobalSettings($moduleDirectoryNames, $keys)
	{
		return self::getProjectSettings($moduleDirectoryNames, array(self::GLOBAL_SETTING_PROJECT_ID), $keys);
	}

	private static function setGlobalSetting($moduleDirectoryName, $key, $value)
	{
		self::setProjectSetting($moduleDirectoryName, self::GLOBAL_SETTING_PROJECT_ID, $key, $value);
	}

	private static function removeGlobalSetting($moduleDirectoryName, $key)
	{
		self::removeProjectSetting($moduleDirectoryName, self::GLOBAL_SETTING_PROJECT_ID, $key);
	}

	private static function setProjectSetting($moduleDirectoryName, $projectId, $key, $value)
	{
		$externalModuleId = self::getExternalModuleId($moduleDirectoryName);

		$projectId = db_real_escape_string($projectId);
		$key = db_real_escape_string($key);
		$value = db_real_escape_string($value);

		$oldValue = self::getProjectSetting($moduleDirectoryName, $projectId, $key);
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

	static function getProjectSettings($moduleDirectoryNames, $projectIds, $keys)
	{
		$whereClauses = array();

		if (!empty($moduleDirectoryNames)) {
			$whereClauses[] = self::getSQLInClause('m.directory_name', $moduleDirectoryNames);
		}

		if (!empty($projectIds)) {
			$whereClauses[] = self::getSQLInClause('s.project_id', $projectIds);
		}

		if (!empty($keys)) {
			$whereClauses[] = self::getSQLInClause('s.key', $keys);
		}

		return self::query("SELECT directory_name, value
							FROM redcap_external_modules m
							JOIN redcap_external_module_settings s
								ON m.external_module_id = s.external_module_id
							WHERE " . implode(' AND ', $whereClauses));
	}

	static function getProjectSetting($moduleDirectoryName, $projectId, $key)
	{
		$result = self::getProjectSettings(array($moduleDirectoryName), array($projectId), array($key));

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

	static function removeProjectSetting($moduleDirectoryName, $projectId, $key){
		self::setProjectSetting($moduleDirectoryName, $projectId, $key, null);
	}

	private static function getExternalModuleId($moduleDirectoryName)
	{
		$moduleDirectoryName = db_real_escape_string($moduleDirectoryName);

		$result = self::query("SELECT external_module_id FROM redcap_external_modules WHERE directory_name = '$moduleDirectoryName'");

		$row = db_fetch_assoc($result);
		if($row){
			return $row['external_module_id'];
		}
		else{
			self::query("INSERT INTO redcap_external_modules (directory_name) VALUES ('$moduleDirectoryName')");
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

		$modulesNames = self::getModuleNamesWithHook($name);
		foreach($modulesNames as $moduleName){
			self::$moduleBeingLoaded = $moduleName;
			$instance = self::getModuleInstance($moduleName);
			self::$moduleBeingLoaded = null;

			$methodName = "hook_$name";

			if(method_exists($instance, $methodName)){
				if(!$instance->hasPermission($methodName)){
					throw new Exception("The \"$moduleName\" external module must request permission in order to define the following hook: $methodName()");
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

	private static function getModuleInstance($moduleDirectoryName)
	{
		$modulePath = ExternalModules::$MODULES_PATH . $moduleDirectoryName;
		$className = basename($modulePath) . 'ExternalModule';
		$classNameWithNamespace = "\\" . __NAMESPACE__ . "\\$className";

		if(!class_exists($classNameWithNamespace)){
			$classFilePath = "$modulePath/$className.php";

			if(!file_exists($classFilePath)){
				throw new Exception("Could not find the following External Module main class file: $classFilePath");
			}

			self::safeRequireOnce($classFilePath);
		}

		return new $classNameWithNamespace;
	}

	static function getModuleNamesWithHook($hookName)
	{
		# TODO - Once enabled modules are stored in the database we will query for enabled modules that request permission for the specified hook.
		# For now, simply return all enabled modules.
		return self::getEnabledModuleNames();
	}

	static function getSettingOverrideDropdown($fieldName)
	{
		?>
		<td>
			<select name="<?= $fieldName ?>_override">
				<option>Superusers Only</option>
				<option>Design Rights Users</option>
				<option>Any User</option>
			</select>
		</td>
		<?php
	}

	static function getProjectSettingOverrideCheckbox($fieldName)
	{
		?>
		<td>
			<input type="checkbox" name="<?= $fieldName ?>_override" checked>
		</td>
		<?php
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

		$links['Manage External Modules'] = array(
			'icon' => 'brick',
			'url' => ExternalModules::$BASE_URL  . 'manager/project.php'
		);

		ksort($links);

		return $links;
	}

	private function getLinks($type){
		# TODO - This data will likely end up coming from enabled modules in the database instead in the future.

		$links = array();

		$moduleNames = self::getEnabledModuleNames();
		foreach($moduleNames as $moduleName){
			$config = json_decode(file_get_contents(self::$MODULES_PATH . "$moduleName/config.json"), true);

			foreach($config['links'][$type] as $name=>$link){
				$link['url'] = self::$MODULES_URL . $moduleName . '/' . $link['url'];
				$links[$name] = $link;
			}
		}

		ksort($links);

		return $links;
	}

	static function getDisabledModuleNames()
	{
		$enabledModules = self::getEnabledModuleNames();
		$dirs = scandir(self::$MODULES_PATH);

		$disabledModuleNames = array();
		foreach ($dirs as $dir) {
			if ($dir[0] == '.') {
				continue;
			}

			if(!in_array($dir, $enabledModules)){
				$disabledModuleNames[] = $dir;
			}
		}

		return $disabledModuleNames;
	}

	static function getConfigs($moduleNames)
	{
		$modules = array();

		foreach ($moduleNames as $name) {
			$config = json_decode(file_get_contents(self::$MODULES_PATH . "$name/config.json"));
			$modules[$name] = $config;
		}

		return $modules;
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

