# REDCap_Modules
Development work for REDCap External Modules/Packages to support a standardized Hook/Plugin framework and management mechanism

## Installation
1. Clone this repo into to an **external_modules** directory under your REDCap web root.
1. Run ```sql/create tables.sql``` and then ```sql/migration-2017-01-18_10-03-00.sql``` on your redcap database to create the required tables.
1. In **Control Center -> General Configuration -> REDCap Hooks**, select the **hooks.php** file under the new **external_modules** directory.
	* If you wish to use a different hooks file, you can still add External Module support via the following steps:
		1. Insert the following at the top of your hooks file:
		
			```
			require_once dirname(__FILE__) . '/classes/ExternalModules.php';
			use ExternalModules\ExternalModules;
			```
			
		2. Place the following line at the end of each function in your hooks file:
		
			```
			ExternalModules::callHook(__FUNCTION__, func_get_args());
			```
3. An **External Modules** section will now be available under both the Control Center and Project menus.


## Usage

The best way to get started is to download the example module from here:
https://github.com/mmcev106/redcap-external-module-example

It can be installed by downloading that repo as a ZIP file, then extracting it's contents to ```<redcap-web-root>/modules/vanderbilt_example_v1.0```

Here are a few details on managing modules:

* Once installed, modules can be enabled under the **Manage External Modules** link under **Control Center**.
* Enabling a module under **Control Center** does not enable it on any projects by default.
* To enable a module on a specific project, go to the **Manage External Modules** link on the project homepage, click **Search for Additional Module(s)**, and click **Enable** next to the desired module name. Then to configure a module, click **Configure** next to the module's name.
* To enable a module on ALL projects by default, go to the **Manage External Modules** link under **Control Center**, click **Configure** next to the module name, check the **Enable on all projects by default** checkbox, then click save.

The only setting that actually does anything in the example module is the **Project Menu Background CSS** setting.  This will change the background color of the menu on project pages, and is a great demo of simple hook usage, and how a setting can be set systemwide and/or overridden per project.


## AbstractExternalModule

The **AbstractExternalModule** class must be extended when creating an external module.  Module creators may make use of the following methods to store and manage settings for their module.  This includes both settings set via the **Manage External Modules** interface, as well as any other data the module creator wants to store:

Method  | Description
------- | -----------
setSystemSetting($key,&nbsp;$value) | Set the setting specified by the key to the specified value systemwide (shared by all projects).
getSystemSetting($key) | Get the value stored systemwide for the specified key.
removeSystemSetting($key) | Remove the value stored systemwide for the specified key.
setProjectSetting($key,&nbsp;$value&nbsp;[,&nbsp;$pid]) | Set the setting specified by the key to the specified value for this project (override the systemwide setting).  In most cases the project id can be detected automatically, but it can optionaly be specified as the third parameter instead.
getProjectSetting($key&nbsp;[,&nbsp;$pid]) | Returns the value stored for the specified key for the current project if it exists.  If this setting key is not set (overriden) for the current project, the systemwide value for this key is returned.  In most cases the project id can be detected automatically, but it can optionaly be specified as the third parameter instead.
removeProjectSetting($key&nbsp;[,&nbsp;$pid]) | Remove the value stored for this project and the specified key.  In most cases the project id can be detected automatically, but it can optionaly be specified as the third parameter instead. 
getUrl($path) | Get the url to a resource (php page, js/css file, image etc.) at the specified path relative to the module directory.
hasPermission($permissionName) | checks whether the current External Module has permission for $permissionName
getConfig() | get the config for the current External Module; consists of config.json and filled-in values
getModuleDirectoryName() | get the directory name of the current external module
getModuleName() | get the name of the current external module
delayModuleExecution() | pushes the execution of the module to the end of the queue; helpful to wait for data to be processed by other modules; execution of the module will be restarted from the beginning
