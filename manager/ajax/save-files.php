<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];
$version = $_GET['version'];

if(empty($pid) && !ExternalModules::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
	die("You don't have permission to save global settings!");
}
$config = ExternalModules::getConfig($moduleDirectoryPrefix, $version, $pid);

foreach($_POST as $key=>$value){
	if(empty($pid)){
		ExternalModules::setGlobalSetting($moduleDirectoryPrefix, $key, $value);
	}
	else{
		if(!ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)){
			die("You don't have permission to save the following project setting: $key");
		}

                $settings = array();
                foreach ($config['global-settings'] as $row) {
                        $settings[] = $row;
                }
                foreach ($config['project-settings'] as $row) {
                        $settings[] = $row;
                }
                foreach ($settings as $row) {
                        if (($key == $row['key']) && $row['type'] && ($row['type'] == "file")) {
                                $files[$key] = $value;
:wq









                        }
                }

                if (!$files[$key]) {
		        ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
                }
	}
}

header('Content-type: application/json');
echo json_encode(array(
        'keys' => json_encode($_POST),
        'files' => json_encode($files),
	'status' => 'success'
));
