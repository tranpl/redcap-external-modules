# REDCap_Modules
Development work for REDCap External Modules/Packages to support a standardized Hook/Plugin framework and management mechanism

An example module can be found here: https://github.com/mmcev106/redcap-external-module-example

## Installation
1. Clone this repo into to an **external_modules** directory under your REDCap web root.
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
