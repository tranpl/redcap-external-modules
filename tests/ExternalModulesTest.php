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

		$this->removeGlobalSetting();
	}
}