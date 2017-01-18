<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class ExternalModulesTest extends BaseTest
{
	function testInitializeSettingDefaults()
	{
		$defaultValue = rand();

		$m = $this->getInstance([
			'system-settings' => [
				[
					'key' => TEST_SETTING_KEY,
					'default' => $defaultValue
				]
			]
		]);

		$this->assertNull($this->getSystemSetting());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertEquals($defaultValue, $this->getSystemSetting());

		// Make sure defaults do NOT overwrite any existing settings.
		$this->setSystemSetting(rand());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertNotEquals($defaultValue, $this->getSystemSetting());
	}

	function testgetProjectSettingsAsArray_systemOnly()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testgetProjectSettingsAsArray_projectOnly()
	{
		$value = rand();
		$this->setProjectSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals(null, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testgetProjectSettingsAsArray_both()
	{
		$systemValue = rand();
		$projectValue = rand();

		$this->setSystemSetting($systemValue);
		$this->setProjectSetting($projectValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($systemValue, $array[TEST_SETTING_KEY]['system_value']);

		// Re-test reversing the insert order to make sure it doesn't matter.
		$this->setProjectSetting($projectValue);
		$this->setSystemSetting($systemValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($systemValue, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testAddReservedSettings()
	{
		$method = 'addReservedSettings';

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'system-settings' => array(
					array('key' => ExternalModules::KEY_VERSION)
				)
			));
		}, 'reserved for internal use');

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'project-settings' => array(
					array('key' => ExternalModules::KEY_ENABLED)
				)
			));
		}, 'reserved for internal use');

		// Make sure other settings are passed through without exception.
		$key = 'some-non-reserved-settings';
		$config = self::callPrivateMethod($method, array(
			'system-settings' => array(
				array('key' => $key)
			)
		));

		$systemSettings = $config['system-settings'];
		$this->assertEquals(2, count($systemSettings));
		$this->assertEquals(ExternalModules::KEY_ENABLED, $systemSettings[0]['key']);
		$this->assertEquals($key, $systemSettings[1]['key']);
	}

	function testCacheAllEnableData()
	{
		$m = $this->getInstance();

		$version = rand();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, $version);

		self::callPrivateMethod('cacheAllEnableData');
		$this->assertEquals($version, self::callPrivateMethod('getSystemwideEnabledVersions')[TEST_MODULE_PREFIX]);

		$m->removeSystemSetting(ExternalModules::KEY_VERSION);

		// the other values set by cacheAllEnableData() are tested via testgetEnabledModuleVersionsForProject()
	}

	function testgetEnabledModuleVersionsForProject_multiplePrefixesAndVersions()
	{
		$prefix1 = TEST_MODULE_PREFIX . '-1';
		$prefix2 = TEST_MODULE_PREFIX . '-2';

		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_ENABLED, true);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_ENABLED, true);

		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNotNull($prefixes[$prefix2]);

		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNull($prefixes[$prefix2]);

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_ENABLED);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[$prefix1]);

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_VERSION);
		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_ENABLED);
	}

	function testgetEnabledModuleVersionsForProject_overrides()
	{
		$m = self::getInstance();

		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$rv = $m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, false);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);
	}

	private function getEnabledModuleVersionsForProjectIgnoreCache()
	{
		self::callPrivateMethod('cacheAllEnableData'); // Call this every time to clear/reset the cache.
		return self::callPrivateMethod('getEnabledModuleVersionsForProject', TEST_SETTING_PID);
	}

	private function callPrivateMethod($methodName)
	{
		$args = func_get_args();
		array_shift($args); // remove the method name

		$class = self::getReflectionClass();
		$method = $class->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs(null, $args);
	}

	private function getPrivateVariable($name)
	{
		$class = self::getReflectionClass();
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		return $property->getValue(null);
	}

	private function getReflectionClass()
	{
		return new \ReflectionClass('ExternalModules\ExternalModules');
	}
}
