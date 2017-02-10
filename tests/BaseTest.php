<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../classes/ExternalModules.php';

use PHPUnit\Framework\TestCase;
use \Exception;

const TEST_MODULE_PREFIX = ExternalModules::TEST_MODULE_PREFIX;
const TEST_MODULE_VERSION = 'v1.0.0';
const TEST_SETTING_KEY = 'unit-test-setting-key';
const FILE_SETTING_KEY = 'unit-test-file-setting-key';
const TEST_SETTING_PID = 1;

abstract class BaseTest extends TestCase
{
	protected $backupGlobals = FALSE;

	private $testModuleInstance;

	public static function setUpBeforeClass(){
		// These were added simply to avoid warnings from REDCap code.
		$_SERVER['REMOTE_ADDR'] = 'unit testing';
		if(!defined('PAGE')){
			define('PAGE', 'unit testing');
		}

		ExternalModules::initialize();
	}

	protected function setUp(){
		self::cleanupSettings();
	}

	protected function tearDown()
	{
		self::cleanupSettings();
	}

	private function cleanupSettings()
	{
		$this->setConfig([]);
		$this->getInstance()->testHookArguments = null;

		$this->removeGlobalSetting();
		$this->removeProjectSetting();

		$m = self::getInstance();
		$m->removeGlobalSetting(ExternalModules::KEY_VERSION, TEST_SETTING_PID);
		$m->removeGlobalSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);

		unset($_GET['pid']);
	}

	protected function setGlobalSetting($value)
	{
		self::getInstance()->setGlobalSetting(TEST_SETTING_KEY, $value);
	}

	protected function getGlobalSetting()
	{
		return self::getInstance()->getGlobalSetting(TEST_SETTING_KEY);
	}

	protected function removeGlobalSetting()
	{
		self::getInstance()->removeGlobalSetting(TEST_SETTING_KEY);
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

	protected function getInstance()
	{
		if($this->testModuleInstance == null){
			$instance = new BaseTestExternalModule();
			$moduleDirectoryName = ExternalModules::getModuleDirectoryName(TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
			$this->setExternalModulesProperty('instanceCache', [$moduleDirectoryName => $instance]);

			$this->testModuleInstance = $instance;
		}

		return $this->testModuleInstance;
	}

	protected function setConfig($config)
	{
		$moduleDirectoryName = ExternalModules::getModuleDirectoryName(TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
		$this->setExternalModulesProperty('configs', [$moduleDirectoryName => $config]);
	}

	private function setExternalModulesProperty($name, $value)
	{
		$externalModulesClass = new \ReflectionClass("ExternalModules\\ExternalModules");
		$configsProperty = $externalModulesClass->getProperty($name);
		$configsProperty->setAccessible(true);
		$configsProperty->setValue($value);
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

	public $testHookArguments;

	function __construct()
	{
		$this->PREFIX = TEST_MODULE_PREFIX;
		$this->VERSION = TEST_MODULE_VERSION;

		parent::__construct();
	}

	function getModuleDirectoryName()
	{
		return ExternalModules::getModuleDirectoryName($this->PREFIX, $this->VERSION);
	}

	function __call($name, $arguments)
	{
		// We end up in here when we try to call a private method.
		// use reflection to call the method anyway (allowing unit testing of private methods).
		$method = new \ReflectionMethod(get_class(), $name);
		$method->setAccessible(true);

		return $method->invokeArgs ($this, $arguments);
	}

	function hook_test()
	{
		$this->testHookArguments = func_get_args();
	}
}
