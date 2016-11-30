<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../classes/ExternalModules.php';

use PHPUnit\Framework\TestCase;
use \Exception;

const TEST_SETTING_KEY = 'unit-test-setting-key';
const TEST_SETTING_PID = 1;

class AbstractExternalModuleTest extends TestCase
{
	protected $backupGlobals = FALSE;

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
		$exception = null;

		try{
			self::assertConfigValid($config);
		}
		catch(Exception $e){
			$exception = $e;
		}

		$this->assertNotNull($exception);
	}

	function testGlobalSettings()
	{
		// Clean up this setting from any previous failed tests.
		$this->removeGlobalSetting();
		$this->assertNull($this->getGlobalSetting());

		$value = rand();
		$this->setGlobalSetting($value);
		$this->assertEquals($value, $this->getGlobalSetting());

		$this->removeGlobalSetting();
		$this->assertNull($this->getGlobalSetting());
	}

	function testProjectSettings()
	{
		// Clean up this setting from any previous failed tests.
		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());

		$value = rand();
		$this->setProjectSetting($value);
		$this->assertEquals($value, $this->getProjectSetting());

		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());
	}

	function testGetSetting()
	{
		// Clean up this setting from any previous failed tests.
		$this->removeGlobalSetting();
		$this->assertNull($this->getGlobalSetting());
		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());

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

	private function setGlobalSetting($value)
	{
		self::getInstance()->setGlobalSetting(TEST_SETTING_KEY, $value);
	}

	private function getGlobalSetting()
	{
		return self::getInstance()->getGlobalSetting(TEST_SETTING_KEY);
	}

	private function removeGlobalSetting()
	{
		self::getInstance()->removeGlobalSetting(TEST_SETTING_KEY);
	}

	private function setProjectSetting($value)
	{
		self::getInstance()->setProjectSetting(TEST_SETTING_KEY, $value, TEST_SETTING_PID);
	}

	private function getProjectSetting()
	{
		return self::getInstance()->getProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	private function removeProjectSetting()
	{
		self::getInstance()->removeProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

    private function getInstance($config = [])
	{
    	return new class($config) extends AbstractExternalModule {
    		function __construct($config)
			{
				$this->CONFIG = $config;
				parent::__construct();
			}

			function __call($name, $arguments)
			{
				// We end up in here when we try to call a private method.
				// use reflection to call the method anyway (allowing unit testing of private methods).
				$method = new \ReflectionMethod(get_class(), $name);
				$method->setAccessible(true);

				return $method->invokeArgs ($this, $arguments);
			}
		};
	}

	private function assertThrowsException($callable){
		$exceptionThrown = false;
		try{
			$callable();
		}
		catch(Exception $e){
			$exceptionThrown = true;
		}

		$this->assertTrue($exceptionThrown);
	}
}