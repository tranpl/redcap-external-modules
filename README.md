# REDCap_Modules
Development work for REDCap External Modules/Packages to support a standardized Hook/Plugin framework and management mechanism

## Installation
1. Clone this repo into to an **external_modules** directory under your REDCap web root.
1. Run ```sql/create tables.sql``` on your redcap database to create the required tables.
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
* To enable a module on a specific project, go to the **Manage External Modules** link on the project homepage, click **Configure** next to the module name, check the **Enabled** checkbox, then click save.
* To enable a module on ALL projects by default, go to the **Manage External Modules** link under **Control Center**, click **Configure** next to the module name, check the **Enable on all projects by default** checkbox, then click save.

The only setting that actually does anything in the example module is the **Project Menu Background CSS** setting.  This will change the background color of the menu on project pages, and is a great demo of simple hook usage, and how a setting can be set globally and/or overridden per project.