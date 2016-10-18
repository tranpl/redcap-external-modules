<?php
namespace ExternalModules;

require_once __DIR__ . "/AbstractExternalModule.php";

require_once __DIR__ . "/../../redcap_connect.php";

use \Exception;

class ExternalModules
{
	public static $BASE_URL;
	public static $MODULES_URL;
	public static $MODULES_PATH;

	private static $initialized = false;

	static function initialize()
	{
		if (!defined(__DIR__)) define(__DIR__, dirname(__FILE__));

		if($_SERVER[HTTP_HOST] == 'localhost'){
			// Assume this is a developer's machine and enable errors.
			ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL);
		}

		self::$BASE_URL = APP_PATH_WEBROOT . '../external_modules/';
		self::$MODULES_URL = self::$BASE_URL . 'modules/';
		self::$MODULES_PATH = __DIR__ . "/../modules/";
	}

	static function getProjectHeaderPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	static function getProjectFooterPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	static function getAvailableModules()
	{
		# TODO - We should remove this function, likely replacing references to it with the following:
		return self::getConfigs(self::getDisabledModuleNames());
	}

	static function getEnabledModuleNames()
	{
		# TODO - Eventually we'll track which modules are enabled in the database.
		# For testing (for now) simply use a hard coded list.
		return array('example');
	}

	static function remove($module)
	{
		throw new Exception('This method should be removed when we switch "removing" language to "disabling" language.');
	}

	static function enable($module)
	{
		throw new Exception('Enabling modules is not yet supported.  For testing (for now), they can be hardcoded in the getEnabledModuleNames() method.');
	}

	static function callHook($name, $arguments)
	{
		# We must initialize this static class here, since this method actually gets called before anything else.
		# This method is actually called many times (once per hook), so we should only initialize once.
		if(!self::$initialized){
			self::initialize();
			self::$initialized = true;
		}

		$name = str_replace('redcap_', '', $name);

		$templatePath = __DIR__ . "/../manager/templates/hooks/$name.php";
		if(file_exists($templatePath)){
			require $templatePath;
		}

		$modulesNames = self::getModuleNamesWithHook($name);
		foreach($modulesNames as $moduleName){
			$modulePath = ExternalModules::$MODULES_PATH . $moduleName;
			$className = basename($modulePath) . 'ExternalModule';

			$classFilePath = "$modulePath/$className.php";
			if(!file_exists($classFilePath)){
				throw new Exception("Could not find the following External Module main class file: $classFilePath");
			}

			require_once $classFilePath;
			$classNameWithNamespace = "\\" . __NAMESPACE__ . "\\$className";
			$instance = new $classNameWithNamespace;
			$methodName = "hook_$name";

			if(method_exists($instance, $methodName)){
				if(!$instance->hasPermission($methodName)){
					throw new Exception("The $classNameWithNamespace module must request permission in order to define the following hook: $methodName()");
				}

				call_user_func_array(array($instance,$methodName), $arguments);
			}
		}
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

