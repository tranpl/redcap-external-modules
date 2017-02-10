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
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		], 'both the global and project level');

		self::assertConfigInvalid([
			'global-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		], 'global setting multiple times!');

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
		], 'Default values are only allowed on global settings');
	}

	function testCheckSettingKey_valid()
	{
		self::assertConfigValid([
			'global-settings' => [
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
			'global-settings' => [
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
		$this->setConfig($config);
		self::getInstance();
	}

	function assertConfigInvalid($config, $exceptionExcerpt)
	{
		$this->assertThrowsException(function() use ($config){
			self::assertConfigValid($config);
		}, $exceptionExcerpt);
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
		$projectValue = rand();
		$globalValue = rand();

		$this->setProjectSetting($projectValue);
		$this->assertEquals($projectValue, $this->getProjectSetting());

		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());

		$this->setGlobalSetting($globalValue);
		$this->assertEquals($globalValue, $this->getProjectSetting());

		$this->setProjectSetting($projectValue);
		$this->assertEquals($projectValue, $this->getProjectSetting());
	}

	function testSettingTypeConsistency()
	{
		$assertReturnedType = function($value, $expectedType){
			$this->setProjectSetting($value);
			$type = gettype($this->getProjectSetting());
			$this->assertEquals($expectedType, $type);
		};

		$assertReturnedType(true, 'boolean');
		$assertReturnedType(1, 'integer');
		$assertReturnedType(1.1, 'double');
		$assertReturnedType("1", 'string');
		$assertReturnedType([1], 'array');
		$assertReturnedType(null, 'NULL');
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

	function testHasPermission()
	{
		$m = $this->getInstance();

		$testPermission = 'some_test_permission';
		$config = ['permissions' => []];

		$this->setConfig($config);
		$this->assertFalse($m->hasPermission($testPermission));

		$config['permissions'][] = $testPermission;
		$this->setConfig($config);
		$this->assertTrue($m->hasPermission($testPermission));
	}
}