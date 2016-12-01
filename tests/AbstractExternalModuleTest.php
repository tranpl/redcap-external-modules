<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class AbstractExternalModuleTest extends BaseTest
{
	function testCheckSettings_emptyConfig()
	{
		self::assertConfigValid([]);
	}

    function testCheckSettings_duplicateKeys()
    {
		self::assertConfigInvalid([
			'global-settings' => [
				'someKey' => true
			],
			'project-settings' => [
				'someKey' => true
			],
		]);
    }

	function testCheckSettings_projectDefaults()
	{
		self::assertConfigInvalid([
			'project-settings' => [
				'some-setting' => [
					'default' => true
				]
			]
		]);
	}

	function testCheckSettingKey_valid()
	{
		self::assertConfigValid([
			'global-settings' => [
				'key1' => true
			],
			'project-settings' => [
				'key-two' => true
			],
		]);
	}

	function testCheckSettingKey_globalInvalid()
	{
		self::assertConfigInvalid([
			'global-settings' => [
				"A" => true
			]
		]);
	}

	function testCheckSettingKey_projectInvalid()
	{
		self::assertConfigInvalid([
			'project-settings' => [
				"!" => true
			]
		]);
	}

	function testIsSettingKeyValid()
	{
		$m = self::getInstance();

		$this->assertTrue($m->isSettingKeyValid('a'));
		$this->assertTrue($m->isSettingKeyValid('2'));
		$this->assertTrue($m->isSettingKeyValid('-'));

		$this->assertFalse($m->isSettingKeyValid('A'));
		$this->assertFalse($m->isSettingKeyValid('!'));
		$this->assertFalse($m->isSettingKeyValid('_'));
		$this->assertFalse($m->isSettingKeyValid('"'));
		$this->assertFalse($m->isSettingKeyValid('\''));
		$this->assertFalse($m->isSettingKeyValid(' '));
	}

	function assertConfigValid($config)
	{
		self::getInstance($config);
	}

	function assertConfigInvalid($config)
	{
		$this->assertThrowsException(function() use ($config){
			self::assertConfigValid($config);
		});
	}

	function testGlobalSettings()
	{
		$value = rand();
		$this->setGlobalSetting($value);
		$this->assertEquals($value, $this->getGlobalSetting());

		$this->removeGlobalSetting();
		$this->assertNull($this->getGlobalSetting());
	}

	function testProjectSettings()
	{
		$value = rand();
		$this->setProjectSetting($value);
		$this->assertEquals($value, $this->getProjectSetting());

		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());
	}

	function testGetSetting()
	{

		$globalValue = 'global';
		$this->setGlobalSetting($globalValue);
		$this->assertEquals($globalValue, $this->getGlobalSetting());

		$projectValue = 'project';
		$this->setProjectSetting($projectValue);
		$this->assertEquals($projectValue, $this->getProjectSetting());

		$this->removeGlobalSetting();
		$this->assertEquals($projectValue, $this->getProjectSetting());
		
		$this->removeProjectSetting();
	}

	function testRequireProjectId()
	{
		$m = $this->getInstance();

		$this->assertThrowsException(function() use ($m){
			$m->requireProjectId(null);
		});

		$pid = rand();
		$this->assertEquals($pid, $m->requireProjectId($pid));

		$_GET['pid'] = $pid;
		$this->assertEquals($pid, $m->requireProjectId(null));
		unset($_GET['pid']);
	}

	function testDetectProjectId()
	{
		$m = $this->getInstance();

		$this->assertEquals(null, $m->detectProjectId(null));

		$pid = rand();
		$this->assertEquals($pid, $m->detectProjectId($pid));

		$_GET['pid'] = $pid;
		$this->assertEquals($pid, $m->detectProjectId(null));
		unset($_GET['pid']);
	}
}