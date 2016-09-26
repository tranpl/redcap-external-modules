<?php
namespace ExternalModules;

require_once __DIR__ . "/../../redcap_connect.php";

use \Exception;

require_once __DIR__ . "/AbstractExternalModule.php";

class ExternalModules
{
	public static $BASE_URL;

	private static $AVAILABLE_MODULES_PATH;
	private static $INSTALLED_MODULES_PATH;

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

		self::$AVAILABLE_MODULES_PATH = __DIR__ . "/../modules/available/";
		self::$INSTALLED_MODULES_PATH = __DIR__ . "/../modules/installed/";

		if (!file_exists(self::$INSTALLED_MODULES_PATH)) {
			// TODO - Uncomment this one we add the ability to configure a writable folder for modules.
			// mkdir(self::$INSTALLED_MODULES_PATH);
		}
	}

	static function getProjectHeaderPath()
	{
		return __DIR__ . '/../../' . APP_PATH_WEBROOT . 'ProjectGeneral/header.php';
	}

	static function getProjectFooterPath()
	{
		return __DIR__ . '/../../' . APP_PATH_WEBROOT . 'ProjectGeneral/footer.php';
	}

	static function getAvailableModules($excludedModules = array())
	{
		$available = self::getModulesFromPath(self::$AVAILABLE_MODULES_PATH);

		foreach ($available as $module => $config) {
			if (in_array($module, $excludedModules)) {
				unset($available[$module]);
			}
		}

		return $available;
	}

	static function getInstalledModules()
	{
		return self::getModulesFromPath(self::$INSTALLED_MODULES_PATH);
	}

	static function remove($module)
	{
		self::rrmdir(self::$INSTALLED_MODULES_PATH . $module);
	}

	static function getIconPath()
	{
		return APP_PATH_WEBROOT . 'Resources/images/brick.png';
	}

	static function install($module)
	{
		$source = self::$AVAILABLE_MODULES_PATH . $module;
		$destination = self::$INSTALLED_MODULES_PATH . $module;

		# Taken from here: http://stackoverflow.com/questions/5707806/recursive-copy-of-directory
		mkdir($destination, 0755);
		foreach (
		$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
		\RecursiveIteratorIterator::SELF_FIRST) as $item
		) {
			if ($item->isDir()) {
				mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
			} else {
				copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
			}
		}
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

		# TODO - We could optimize for efficiency here by only looping through the modules that actually request permissions for each hook.
		$modulesPaths = glob(self::$INSTALLED_MODULES_PATH . '*' , GLOB_ONLYDIR);
		foreach($modulesPaths as $modulePath){
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

	private static function getModulesFromPath($path)
	{
		$modules = array();
		$dirs = scandir($path);

		foreach ($dirs as $dir) {
			if ($dir[0] == '.') {
				continue;
			}

			$config = json_decode(file_get_contents("$path/$dir/config.json"));
			$modules[$dir] = $config;
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

