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

	function testAddReservedSettings(){
		$class = new \ReflectionClass('ExternalModules\ExternalModules');
		$method = $class->getMethod('addReservedSettings');
		$method->setAccessible(true);

		$settingsPlaceholder = "Normally settings would go here, but it doesn't matter for this test.";

		$this->assertThrowsException(function() use ($method, $settingsPlaceholder){
			$method->invokeArgs(null, array(array(
				'global-settings' => array(
					'version' => $settingsPlaceholder
				)
			)));
		});

		$this->assertThrowsException(function() use ($method, $settingsPlaceholder){
			$method->invokeArgs(null, array(array(
				'global-settings' => array(
					'enabled' => $settingsPlaceholder
				)
			)));
		});

		// Make sure other settings are passed through without exception.
		$key = 'some-non-reserved-settings';
		$config = $method->invokeArgs(null, array(array(
			'global-settings' => array(
				$key => $settingsPlaceholder
			)
		)));
		$this->assertEquals($settingsPlaceholder, $config['global-settings'][$key]);

		// Make sure reserved settings were merged.
		$this->assertTrue(is_array($config['global-settings']['enabled']));

		// Make sure version was excluded, since we don't want to display it.
		$this->assertTrue(!isset($config['global-settings']['version']));
	}
}