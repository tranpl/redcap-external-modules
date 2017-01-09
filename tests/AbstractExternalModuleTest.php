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
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		], 'both the system and project level');

		self::assertConfigInvalid([
			'system-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		], 'system setting multiple times!');

		self::assertConfigInvalid([
			'project-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		], 'project setting multiple times!');
    }

	function testCheckSettings_projectDefaults()
	{
		self::assertConfigInvalid([
			'project-settings' => [
				[
					'key' => 'some-setting',
					'default' => true
				]
			]
		], 'Default values are only allowed on system settings');
	}

	function testCheckSettingKey_valid()
	{
		self::assertConfigValid([
			'system-settings' => [
				['key' => 'key1']
			],
			'project-settings' => [
				['key' => 'key-two']
			],
		]);
	}

	function testCheckSettingKey_invalidChars()
	{
		$expected = 'contains invalid characters';

		self::assertConfigInvalid([
			'system-settings' => [
				['key' => 'A']
			]
		], $expected);

		self::assertConfigInvalid([
			'project-settings' => [
				['key' => '!']
			]
		], $expected);
	}

	function testIsSettingKeyValid()
	{
		$m = self::getInstance();

		$this->assertTrue($m->isSettingKeyValid('a'));
		$this->assertTrue($m->isSettingKeyValid('2'));
		$this->assertTrue($m->isSettingKeyValid('-'));
		$this->assertTrue($m->isSettingKeyValid('_'));

		$this->assertFalse($m->isSettingKeyValid('A'));
		$this->assertFalse($m->isSettingKeyValid('!'));
		$this->assertFalse($m->isSettingKeyValid('"'));
		$this->assertFalse($m->isSettingKeyValid('\''));
		$this->assertFalse($m->isSettingKeyValid(' '));
	}

	function assertConfigValid($config)
	{
		self::getInstance($config);
	}

	function assertConfigInvalid($config, $exceptionExcerpt)
	{
		$this->assertThrowsException(function() use ($config){
			self::assertConfigValid($config);
		}, $exceptionExcerpt);
	}

	function testSystemSettings()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$this->assertEquals($value, $this->getSystemSetting());

		$this->removeSystemSetting();
		$this->assertNull($this->getSystemSetting());
	}

	function testProjectSettings()
	{
		$projectValue = rand();
		$systemValue = rand();

		$this->setProjectSetting($projectValue);
		$this->assertEquals($projectValue, $this->getProjectSetting());

		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());

		$this->setSystemSetting($systemValue);
		$this->assertEquals($systemValue, $this->getProjectSetting());

		$this->setProjectSetting($projectValue);
		$this->assertEquals($projectValue, $this->getProjectSetting());
	}

	function testRequireProjectId()
	{
		$m = $this->getInstance();

		$this->assertThrowsException(function() use ($m){
			$m->requireProjectId(null);
		}, 'must supply a project id');

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
