<?php
namespace Modules;

if (!defined(__DIR__)) define(__DIR__, dirname(__FILE__));

require_once __DIR__ . "/../../redcap_connect.php";

class Modules
{
	public static $BASE_URL;

	private static $AVAILABLE_MODULES_PATH;
	private static $INSTALLED_MODULES_PATH;

	static function init()
	{
		self::$BASE_URL = APP_PATH_WEBROOT . '../external_modules/';

		self::$AVAILABLE_MODULES_PATH = __DIR__ . "/../modules/";
		self::$INSTALLED_MODULES_PATH = __DIR__ . "/../installed_modules/";

		if (!file_exists(self::$INSTALLED_MODULES_PATH)) {
			// TODO - Uncomment this one we add the ability to configure a writable folder for modules.
			// mkdir(self::$INSTALLED_MODULES_PATH);
		}
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
		// We're just mocking an installed module for now, until we create configuration steps for a writable installed modules folder.
		$doggyDaycare = new \StdClass();
		$doggyDaycare->name = 'Doggy Daycare';

		return array(
			'doggy-daycare' => $doggyDaycare
		);

//		return self::getModulesFromPath(self::$INSTALLED_MODULES_PATH);
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

	static function callHook($name, $args)
	{
		# TODO: We need to add a way to forward hooks calls to modules here.
		# This could be a callHook() function like this one on the module class, a function
		# definition for each hook on the module class, or an actual file for each hook.

		if ($name == 'redcap_control_center') {
			require_once __DIR__ . "/../manager/templates/control-center.php";
		} else if ($name == 'redcap_every_page_top') {
			$project_id = $args[0];
			require_once __DIR__ . "/../manager/templates/every-page-top.php";
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
		$url = Modules::$BASE_URL . $path . '?' . filemtime($fullLocalPath);

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

Modules::init();