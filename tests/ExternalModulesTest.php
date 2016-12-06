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
}