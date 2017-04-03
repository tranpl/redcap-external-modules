<?php
\ExternalModules\ExternalModules::addResource(\ExternalModules\ExternalModules::getManagerJSDirectory().'globals.js');
?>
<script type='text/javascript'>
<?php
global $configsByPrefixJSON,$versionsByPrefixJSON;
$constantArray = ["SUPER_USER","ExternalModules::KEY_ENABLED","ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS".
		'ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX'];
foreach($constantArray as $constantName) {
	$javascriptName = str_replace("ExternalModules::","",$constantName);

	echo "ExternalModules.$javascriptName = '".constant($constantName)."';\n";
}
echo "ExternalModules.configsByPrefixJSON = $configsByPrefixJSON;\n";
echo "ExternalModules.versionsByPrefixJSON = $versionsByPrefixJSON;\n";
?>
</script>