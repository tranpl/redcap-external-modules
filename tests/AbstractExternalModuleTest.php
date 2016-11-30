<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../classes/ExternalModules.php';

use PHPUnit\Framework\TestCase;
use \Exception;

class AbstractExternalModuleTest extends TestCase
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

		$this->assertTrue($m->isSettingKeyValid('test'));
		$this->assertTrue($m->isSettingKeyValid('test2'));
		$this->assertTrue($m->isSettingKeyValid('test-dash'));

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
				// Simply forward calls to the parent class (in order to allow public access to protected methods for testing).
				return call_user_func_array(array($this, $name), $arguments);
			}
		};
	}
}