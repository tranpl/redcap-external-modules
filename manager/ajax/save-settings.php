<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

if(empty($pid) && !ExternalModules::hasGlobalSettingsSavePermission($moduleDirectoryPrefix)){
	die("You don't have permission to save global settings!");
}

$instances = array();    # for repeatable elements, you must save them after the original is saved
                         # if not, the value is overwritten by a string/int/etc. - not a JSON
foreach($_POST as $key=>$value){
	if($value == ''){
		$value = null;
	}

	if(empty($pid)){
		ExternalModules::setGlobalSetting($moduleDirectoryPrefix, $key, $value);
	}
	else{
		if(!ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)){
			die("You don't have permission to save the following project setting: $key");
		}

                if (preg_match("/____/", $key)) {
                        $instances[$key] = $value;
                } else {
		        ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pid, $key, $value);
                }
	}
}

# instances must come after the initial settings have been saved
foreach($instances as $key => $value) {
        # allow the last match to be blank and not put into the database
        $last = true;
        $a = preg_split("/____/", $key);
        $shortKey = $a[0];
        $n = $a[1];

        # check if the current element is the last in the repeatable element
        foreach ($_POST as $key2 => $value2) {
                $a2 = preg_split("/____/", $key2);
                if (($a2[0] == $shortKey) && ($a2[1] > $n)) {
                        $last = false;
                        break;
                }
        }

        # do not put in database if last and value is blank
        if (!$last || $value != "") { 
                $a = preg_split("/____/", $key);
                $data = ExternalModules::setInstance($moduleDirectoryPrefix, $pid, $shortKey, (int) $n, $value);
        }
}

header('Content-type: application/json');
echo json_encode(array(
	'status' => 'success'
));
