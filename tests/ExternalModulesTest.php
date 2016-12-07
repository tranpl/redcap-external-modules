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
			'global-settings' => [
				TEST_SETTING_KEY => [
					'default' => $defaultValue
				]
			]
		]);

		$this->assertNull($this->getGlobalSetting());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertEquals($defaultValue, $this->getGlobalSetting());

		// Make sure defaults do NOT overwrite any existing settings.
		$this->setGlobalSetting(rand());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertNotEquals($defaultValue, $this->getGlobalSetting());
	}

	function testGetGlobalAndProjectSettingsAsArray_globalOnly()
	{
		$value = rand();
		$this->setGlobalSetting($value);
		$array = ExternalModules::getGlobalAndProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['global_value']);
	}

	function testGetGlobalAndProjectSettingsAsArray_projectOnly()
	{
		$value = rand();
		$this->setProjectSetting($value);
		$array = ExternalModules::getGlobalAndProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals(null, $array[TEST_SETTING_KEY]['global_value']);
	}

	function testGetGlobalAndProjectSettingsAsArray_both()
	{
		$globalValue = rand();
		$projectValue = rand();

		$this->setGlobalSetting($globalValue);
		$this->setProjectSetting($projectValue);
		$array = ExternalModules::getGlobalAndProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($globalValue, $array[TEST_SETTING_KEY]['global_value']);

		// Re-test reversing the insert order to make sure it doesn't matter.
		$this->setProjectSetting($projectValue);
		$this->setGlobalSetting($globalValue);
		$array = ExternalModules::getGlobalAndProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($globalValue, $array[TEST_SETTING_KEY]['global_value']);
	}

	function testAddReservedSettings()
	{
		$settingsPlaceholder = "Normally settings would go here, but it doesn't matter for this test.";
		$method = 'addReservedSettings';

		$this->assertThrowsException(function() use ($method, $settingsPlaceholder){
			self::callPrivateMethod($method, array(
				'global-settings' => array(
					ExternalModules::KEY_VERSION => $settingsPlaceholder
				)
			));
		});

		$this->assertThrowsException(function() use ($method, $settingsPlaceholder){
			self::callPrivateMethod($method, array(
				'project-settings' => array(
					ExternalModules::KEY_ENABLED => $settingsPlaceholder
				)
			));
		});

		// Make sure other settings are passed through without exception.
		$key = 'some-non-reserved-settings';
		$config = self::callPrivateMethod($method, array(
			'global-settings' => array(
				$key => $settingsPlaceholder
			)
		));
		$this->assertEquals($settingsPlaceholder, $config['global-settings'][$key]);

		// Make sure reserved settings were merged.
		$this->assertTrue(is_array($config['global-settings']['enabled']));

		// Make sure version was excluded, since we don't want to display it.
		$this->assertTrue(!isset($config['global-settings']['version']));
	}

	function testCacheAllEnableData()
	{
		$m = $this->getInstance();

		$version = rand();
		$m->setGlobalSetting(ExternalModules::KEY_VERSION, $version);

		self::callPrivateMethod('cacheAllEnableData');
		$this->assertEquals($version, self::getPrivateVariable('enabledVersions')[TEST_MODULE_PREFIX]);

		$m->removeGlobalSetting(ExternalModules::KEY_VERSION);

		// the other values set by cacheAllEnableData() are tested via testGetEnabledModulePrefixesForProject()
	}

	function testGetEnabledModulePrefixesForProject_multiplePrefixes()
	{
		$prefix1 = TEST_MODULE_PREFIX . '-1';
		$prefix2 = TEST_MODULE_PREFIX . '-2';

		ExternalModules::setGlobalSetting($prefix1, ExternalModules::KEY_ENABLED, true);
		ExternalModules::setGlobalSetting($prefix2, ExternalModules::KEY_ENABLED, true);

		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNotNull($prefixes[$prefix2]);

		ExternalModules::removeGlobalSetting($prefix2, ExternalModules::KEY_ENABLED);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNull($prefixes[$prefix2]);


		ExternalModules::removeGlobalSetting($prefix1, ExternalModules::KEY_ENABLED);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNull($prefixes[$prefix1]);
	}

	function testGetEnabledModulePrefixesForProject_overrides()
	{
		$m = self::getInstance();
		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);

		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, true);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, false);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModulePrefixesForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$this->removeGlobalSetting(ExternalModules::KEY_ENABLED);
	}

	private function getEnabledModulePrefixesForProjectIgnoreCache()
	{
		self::callPrivateMethod('cacheAllEnableData'); // Call this every time to clear/reset the cache.
		return self::callPrivateMethod('getEnabledModulePrefixesForProject', TEST_SETTING_PID);
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