<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';

if(!ExternalModules::areTablesPresent()){
	echo 'Before using External Modules, you must run the following sql to create the appropriate tables:<br><br>';
	echo '<textarea style="width: 100%; height: 300px">' . htmlspecialchars(file_get_contents(__DIR__ . '/../../sql/create tables.sql')) . '</textarea>';
	return;
}

$pid = $_GET['pid'];
?>

<div id="external-modules-disabled-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Available Modules</h4>
			</div>
			<div class="modal-body">
				<form>
				</form>
			</div>
		</div>
	</div>
</div>

<br>
<?php if (isset($_GET['pid'])) { ?>

<p>External modules combine and replace what REDCap previously has called plugins and hooks.
Below is a list of enabled modules that can be used in this project. You can see what other modules are
available by searching for additional modules. These are groups of code from outside sources
that enhance REDCap functioning for specific purposes.</p> 

<?php } else { ?>

<p>External modules combine and replace what REDCap previously has called plugins and hooks.
Below is a list of enabled modules (consisting of hooks and plugins) that are available for your users' use.
They can be enabled system-wide or they can be enabled (opt-in style) on a project-level. Default values for each module,
where desired, have been set by the author of the module. Each system can override these defaults by configuring them
here. In turn, each project can override this set of defaults with their own value.</p>

<?php } ?>
<br>
<button id="external-modules-enable-modules-button">Search for Additional Module(s)</button>
<br>
<br>

<?php if (isset($_GET['pid'])) { ?>
<h3>Currently Enabled Modules</h3>
<?php } else { ?>
<h3>Modules Currently Available on this System</h3>
<?php } ?>

<?php if (isset($_GET['pid'])) { ?>
	<script>
		var pid = <?=json_encode($$_GET['pid'])?>;
	</script>
	<script src='js/enabled-modules-1.js'>
	</script>
<?php } ?>

<table id='external-modules-enabled' class="table">
	<?php

	$versionsByPrefix = ExternalModules::getEnabledModules();
	$configsByPrefix = array();

	if (empty($versionsByPrefix)) {
		echo 'None';
	} else {
		foreach ($versionsByPrefix as $prefix => $version) {
			if (isset($_GET['pid'])) {
				$config = ExternalModules::getConfig($prefix, $version, $_GET['pid']);
			} else {
				$config = ExternalModules::getConfig($prefix, $version);
			}
			$configsByPrefix[$prefix] = $config;
			$enabled = false;
			if (isset($_GET['pid'])) {
				$enabled = ExternalModules::getSetting($prefix, $_GET['pid'], ExternalModules::KEY_ENABLED);
				if ($enabled == "false") {
					$enabled = false;
				} else if ($enabled == "true") {
					$enabled = true;
				}
			}
			if ((isset($_GET['pid']) && $enabled) || (!isset($_GET['pid']) && isset($config['system-settings']))) {
			?>
				<tr data-module='<?= $prefix ?>' data-version='<?= $version ?>'>
					<td><?= $config['name'] . ' - ' . $version ?></td>
					<td class="external-modules-action-buttons">
						<button class='external-modules-configure-button'>Configure</button>
						<button class='external-modules-disable-button'>Disable</button>
					</td>
				</tr>
			<?php
			}
		}
	}

	?>
</table>

<?php
// JSON_PARTIAL_OUTPUT_ON_ERROR was added here to fix an odd conflict between field-list and form-list types
// and some Hebrew characters on the "Israel: Healthcare Personnel (Hebrew)" project that could not be json_encoded.
// This workaround allows configs to be encoded anyway, even though the unencodable characters will be excluded
// (causing form-list and field-list to not work for any fields with unencodeable characters).
// I spent a couple of hours trying to find a solution, but was unable.  This workaround will have to do for now.
$configsByPrefixJSON = json_encode($configsByPrefix, JSON_PARTIAL_OUTPUT_ON_ERROR);
if($configsByPrefixJSON == null){
	echo '<script>alert(' . json_encode('An error occurred while converting the configurations to JSON: ' . json_last_error_msg()) . ');</script>';
	die();
}
?>

<script>
	var configsByPrefix = <?=$configsByPrefixJSON?>;
	var pid = <?=json_encode($pid)?>;
	var versionsByPrefix = <?=$versionsByPrefixJSON?>;
	var pidString = pid;
	if(pid == null){
		pidString = '';
	}
	var isSuperUser = <?=json_encode(SUPER_USER == 1)?>;
</script>
<script src='js/enabled-modules-3.js'></script>
