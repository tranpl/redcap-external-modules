<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../classes/ExternalModules.php';

use PHPUnit\Framework\TestCase;
use \Exception;

const TEST_MODULE_PREFIX = ExternalModules::TEST_MODULE_PREFIX;
const TEST_MODULE_VERSION = 'v1.0.0';
const TEST_SETTING_KEY = 'unit-test-setting-key';
const TEST_SETTING_PID = 1;

abstract class BaseTest extends TestCase
{
	protected $backupSystems = FALSE;

	protected function setUp(){
		self::cleanupSettings();
	}

	protected function tearDown()
	{
		self::cleanupSettings();
	}

	private function cleanupSettings()
	{
		$this->removeSystemSetting();
		$this->removeProjectSetting();

		$m = self::getInstance();
		$m->removeSystemSetting(ExternalModules::KEY_VERSION, TEST_SETTING_PID);
		$m->removeSystemSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
	}

	protected function setSystemSetting($value)
	{
		self::getInstance()->setSystemSetting(TEST_SETTING_KEY, $value);
	}

	protected function getSystemSetting()
	{
		return self::getInstance()->getSystemSetting(TEST_SETTING_KEY);
	}

	protected function removeSystemSetting()
	{
		self::getInstance()->removeSystemSetting(TEST_SETTING_KEY);
	}

	protected function setProjectSetting($value)
	{
		self::getInstance()->setProjectSetting(TEST_SETTING_KEY, $value, TEST_SETTING_PID);
	}

	protected function getProjectSetting()
	{
		return self::getInstance()->getProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	protected function removeProjectSetting()
	{
		self::getInstance()->removeProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	protected function getInstance($config = [])
	{
		return new BaseTestExternalModule($config);
	}

	protected function assertThrowsException($callable, $exceptionExcerpt){
		$exceptionThrown = false;
		try{
			$callable();
		}
		catch(Exception $e){
			if(empty($exceptionExcerpt)){
				throw new Exception('You must specify an exception excerpt!  Here\'s a hint: ' . $e->getMessage());
			}
			else if(strpos($e->getMessage(), $exceptionExcerpt) === false){
				throw new Exception("Could not find the string '$exceptionExcerpt' in the following exception message: " . $e->getMessage());
			}

			$exceptionThrown = true;
		}

		$this->assertTrue($exceptionThrown);
	}
}

class BaseTestExternalModule extends AbstractExternalModule {
	function __construct($config)
	{
		$this->CONFIG = $config;
		parent::__construct();

		$this->PREFIX = TEST_MODULE_PREFIX;
		$this->VERSION = TEST_MODULE_VERSION;
	}

	function __call($name, $arguments)
	{
		// We end up in here when we try to call a private method.
		// use reflection to call the method anyway (allowing unit testing of private methods).
		$method = new \ReflectionMethod(get_class(), $name);
		$method->setAccessible(true);

		return $method->invokeArgs ($this, $arguments);
	}
}
