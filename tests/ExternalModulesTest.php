<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class ExternalModulesTest extends BaseTest
{
	function testInitializeSettingDefaults()
	{
		$defaultValue = rand();

		$this->setConfig([
			'global-settings' => [
				[
					'key' => TEST_SETTING_KEY,
					'default' => $defaultValue
				]
			]
		]);

		$m = $this->getInstance();

		$this->assertNull($this->getGlobalSetting());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertEquals($defaultValue, $this->getGlobalSetting());

		// Make sure defaults do NOT overwrite any existing settings.
		$this->setGlobalSetting(rand());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertNotEquals($defaultValue, $this->getGlobalSetting());
	}

	function testGetProjectSettingsAsArray_globalOnly()
	{
		$value = rand();
		$this->setGlobalSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['global_value']);
	}

	function testGetProjectSettingsAsArray_projectOnly()
	{
		$value = rand();
		$this->setProjectSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals(null, $array[TEST_SETTING_KEY]['global_value']);
	}

	function testGetProjectSettingsAsArray_both()
	{
		$globalValue = rand();
		$projectValue = rand();

		$this->setGlobalSetting($globalValue);
		$this->setProjectSetting($projectValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($globalValue, $array[TEST_SETTING_KEY]['global_value']);

		// Re-test reversing the insert order to make sure it doesn't matter.
		$this->setProjectSetting($projectValue);
		$this->setGlobalSetting($globalValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertEquals($globalValue, $array[TEST_SETTING_KEY]['global_value']);
	}

	function testAddReservedSettings()
	{
		$method = 'addReservedSettings';

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'global-settings' => array(
					array('key' => ExternalModules::KEY_VERSION)
				)
			));
		}, 'reserved for internal use');

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'project-settings' => array(
					array('key' => ExternalModules::KEY_ENABLED)
				)
			));
		}, 'reserved for internal use');

		// Make sure other settings are passed through without exception.
		$key = 'some-non-reserved-settings';
		$config = self::callPrivateMethod($method, array(
			'global-settings' => array(
				array('key' => $key)
			)
		));

		$globalSettings = $config['global-settings'];
		$this->assertEquals(2, count($globalSettings));
		$this->assertEquals(ExternalModules::KEY_ENABLED, $globalSettings[0]['key']);
		$this->assertEquals($key, $globalSettings[1]['key']);
	}

	function testCacheAllEnableData()
	{
		$m = $this->getInstance();

		$version = rand();
		$m->setGlobalSetting(ExternalModules::KEY_VERSION, $version);

		self::callPrivateMethod('cacheAllEnableData');
		$this->assertEquals($version, self::callPrivateMethod('getGloballyEnabledVersions')[TEST_MODULE_PREFIX]);

		$m->removeGlobalSetting(ExternalModules::KEY_VERSION);

		// the other values set by cacheAllEnableData() are tested via testGetEnabledModuleVersionsForProject()
	}

	function testGetEnabledModules()
	{
		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertNull($versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertNull($versionsByPrefix[TEST_MODULE_PREFIX]);

		$m = $this->getInstance();
		$m->setGlobalSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertEquals(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertNull($versionsByPrefix[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertEquals(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertEquals(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
	}

	function testGetEnabledModuleVersionsForProject_multiplePrefixesAndVersions()
	{
		$prefix1 = TEST_MODULE_PREFIX . '-1';
		$prefix2 = TEST_MODULE_PREFIX . '-2';

		ExternalModules::setGlobalSetting($prefix1, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setGlobalSetting($prefix2, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setGlobalSetting($prefix1, ExternalModules::KEY_ENABLED, true);
		ExternalModules::setGlobalSetting($prefix2, ExternalModules::KEY_ENABLED, true);

		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNotNull($prefixes[$prefix2]);

		ExternalModules::removeGlobalSetting($prefix2, ExternalModules::KEY_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNull($prefixes[$prefix2]);

		ExternalModules::removeGlobalSetting($prefix1, ExternalModules::KEY_ENABLED);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[$prefix1]);

		ExternalModules::removeGlobalSetting($prefix1, ExternalModules::KEY_VERSION);
		ExternalModules::removeGlobalSetting($prefix2, ExternalModules::KEY_ENABLED);
	}

	function testGetEnabledModuleVersionsForProject_overrides()
	{
		$m = self::getInstance();

		$m->setGlobalSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, true);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);


		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, false);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull($prefixes[TEST_MODULE_PREFIX]);
	}

	function testGetFileSettings() {
		$m = self::getInstance();					

		$edocIdGlobal = rand();
		$edocIdProject = rand();

                # global
		ExternalModules::setGlobalFileSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY, $edocIdGlobal);

                # project
		ExternalModules::setFileSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY, $edocIdProject);

		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertEquals($edocIdProject, $array[FILE_SETTING_KEY]['value']);
		$this->assertEquals($edocIdGlobal, $array[FILE_SETTING_KEY]['global_value']);

		ExternalModules::removeFileSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY);
		ExternalModules::removeGlobalFileSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);

		$this->assertNull($array[FILE_SETTING_KEY]['value']);
		$this->assertNull($array[FILE_SETTING_KEY]['global_value']);
	}

	function testGetLinks()
	{
		$controlCenterLinkName = "Test Control Center Link Name";
		$controlCenterLinkUrl = "some/control/center/url";
		$projectLinkName = "Test Project Link Name";
		$projectLinkUrl = "some/project/url";

		$this->setConfig([
			'links' => [
				'control-center' => [
					[
						'name'=>$controlCenterLinkName,
						'url'=>$controlCenterLinkUrl
					]
				],
				'project' => [
					[
						'name'=>$projectLinkName,
						'url'=>$projectLinkUrl
					]
				]
			]
		]);

		$links = $this->getLinks();
		$this->assertNull($links[$controlCenterLinkName]);
		$this->assertNull($links[$projectLinkName]);

		$m = $this->getInstance();
		$m->setGlobalSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);

		$assertUrl = function($pageExpected, $actual){
			$externalModulesClass = new \ReflectionClass("ExternalModules\\ExternalModules");
			$method = $externalModulesClass->getMethod('getUrl');
			$method->setAccessible(true);
			$expected = $method->invoke(null, TEST_MODULE_PREFIX, $pageExpected);

			$this->assertEquals($expected, $actual);
		};

		$links = $this->getLinks();
		$assertUrl($controlCenterLinkUrl, $links[$controlCenterLinkName]['url']);
		$this->assertNull($links[$projectLinkName]);

		$_GET['pid'] = TEST_SETTING_PID;

		$links = $this->getLinks();
		$this->assertNull($links[$controlCenterLinkName]);
		$this->assertNull($links[$projectLinkName]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true);

		$links = $this->getLinks();
		$this->assertNull($links[$controlCenterLinkName]);
		$assertUrl($projectLinkUrl, $links[$projectLinkName]['url']);
	}

	function testCallHook_enabledStates()
	{
		$pid = TEST_SETTING_PID;
		$m = $this->getInstance();
		$this->setConfig(['permissions' => ['hook_test']]);
		$this->assertTestHookCalled(false);

		$m->setGlobalSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$this->assertTestHookCalled(true);

		$this->assertTestHookCalled(false, $pid);

		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, true);
		$this->assertTestHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$this->assertTestHookCalled(false, $pid);

		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, false);
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, $pid);
		$this->assertTestHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$this->assertTestHookCalled(false, $pid);
	}

	function testCallHook_arguments()
	{
		$m = $this->getInstance();
		$m->setGlobalSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, true);
		$this->cacheAllEnableData();

		$this->setConfig(['permissions' => ['hook_test']]);

		$argOne = 1;
		$argTwo = 'a';
		ExternalModules::callHook('redcap_test', [$argOne, $argTwo]);
		$this->assertEquals($argOne, $m->testHookArguments[0]);
		$this->assertEquals($argTwo, $m->testHookArguments[1]);
	}

	function testCallHook_permissions()
	{
		$m = $this->getInstance();
		$m->setGlobalSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setGlobalSetting(ExternalModules::KEY_ENABLED, true);

		$this->setConfig(['permissions' => ['hook_test']]);
		$this->assertTestHookCalled(true);

		$this->setConfig([]);
		$this->assertTestHookCalled(false);;
	}

	private function assertTestHookCalled($called, $pid = null)
	{
		$arguments = [];
		if($pid){
			$arguments[] = $pid;
		}

		$this->cacheAllEnableData();
		$m = $this->getInstance();

		$m->testHookArguments = null;
		ExternalModules::callHook('redcap_test', $arguments);
		if($called){
			$this->assertNotNull($m->testHookArguments);
		}
		else{
			$this->assertNull($m->testHookArguments);
		}
	}

	private function getLinks()
	{
		self::callPrivateMethod('cacheAllEnableData');
		return ExternalModules::getLinks();
	}

	// Calling this will effectively clear/reset the cache.
	private function cacheAllEnableData()
	{
		self::callPrivateMethod('cacheAllEnableData');
	}

	private function getEnabledModuleVersionsForProjectIgnoreCache()
	{
		$this->cacheAllEnableData();
		return self::callPrivateMethod('getEnabledModuleVersionsForProject', TEST_SETTING_PID);
	}

	private function callPrivateMethod($methodName)
	{
		$args = func_get_args();
		array_shift($args); // remove the method name

		$class = self::getReflectionClass();
		$method = $class->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs(null, $args);
	}

	private function getPrivateVariable($name)
	{
		$class = self::getReflectionClass();
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		return $property->getValue(null);
	}

	private function getReflectionClass()
	{
		return new \ReflectionClass('ExternalModules\ExternalModules');
	}

	function testInstance()
	{
		$value1 = rand();
		$value2 = rand();
		$value3 = rand();
		$value4 = rand();
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 0, $value1);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertEquals($value1, $array[TEST_SETTING_KEY]['value']);

		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertEquals($value1, $array[TEST_SETTING_KEY]['value'][0]);
		$this->assertEquals($value2, $array[TEST_SETTING_KEY]['value'][1]);
		$this->assertEquals($value3, $array[TEST_SETTING_KEY]['value'][2]);
		$this->assertEquals($value4, $array[TEST_SETTING_KEY]['value'][3]);

		ExternalModules::setProjectSetting($value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID,  TEST_SETTING_KEY);
		$this->assertNotNull(json_encode($array));
		$this->assertEquals($value1, $array[0]);
		$this->assertEquals($value2, $array[1]);
		$this->assertEquals($value3, $array[2]);
		$this->assertEquals($value4, $array[3]);
	}

}
