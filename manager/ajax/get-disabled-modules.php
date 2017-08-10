<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

?>

<table id='external-modules-disabled-table' class="table table-no-top-row-border">
	<?php

	$enabledModules = ExternalModules::getEnabledModules();

	if (!isset($_GET['pid'])) {
		$disabledModuleConfigs = ExternalModules::getDisabledModuleConfigs($enabledModules);

		if (empty($disabledModuleConfigs)) {
			echo 'None';
		} else {
			foreach ($disabledModuleConfigs as $moduleDirectoryPrefix => $versions) {
				$config = reset($versions);
	
				if(isset($enabledModules[$moduleDirectoryPrefix])){
					$enableButtonText = 'Change Version';
				}
				else{
					$enableButtonText = 'Enable';
				}
	
				?>
				<tr data-module='<?= $moduleDirectoryPrefix ?>'>
					<td><?= $config['name'] ?></td>
					<td>
						<select name="version">
							<?php
							foreach($versions as $version=>$config){
								echo "<option>$version</option>";
							}
							?>
						</select>
					</td>
					<td class="external-modules-action-buttons">
						<button class='enable-button'><?=$enableButtonText?></button>
					</td>
				</tr>
				<?php
			}
		}
	} else {
		foreach ($enabledModules as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version, $_GET['pid']);
			$enabled = ExternalModules::getProjectSetting($prefix, $_GET['pid'], ExternalModules::KEY_ENABLED);
			$system_enabled = ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_ENABLED);

			if (!$enabled) {
			?>
				<tr data-module='<?= $prefix ?>' data-version='<?= $version ?>'>
					<td><div class='external-modules-title'>
                            <?= $config['name'] ?> <?= $version ?>
                            <?php if ($system_enabled) print "<span class='label label-warning' title='This module is normally enabled globally for all projects'>Global Module</span>" ?>
                            <input type='hidden' name='version' value='<?= $version ?>'>
                        </div>
                        <div class='external-modules-description'>
                            <?php echo $config['description'] ? $config['description'] : '';?>
                        </div>
                        <div class='external-modules-byline'>
<?php
	if ($config['authors']) {
		$names = array();
		foreach ($config['authors'] as $author) {
			$name = $author['name'];
            $institution = empty($author['institution']) ? "" : " <span class='author-institution'>({$author['institution']})</span>";
			if ($name) {
				if ($author['email']) {
					$names[] = "<a href='mailto:".$author['email']."'>".$name."</a> $institution";
				} else {
					$names[] = $name .  $institution;
				}
			}
		}
		if (count($names) > 0) {
			echo "by ".implode($names, ", ");
		}
	}
?>
</div></td>
					<td style='vertical-align: middle;' class="external-modules-action-buttons">
						<button class='enable-button'>Enable</button>
					</td>
				</tr>
			<?php
			}
		}
	}

	?>
</table>

<script>
	<?php
	if (isset($_GET['pid'])) {
		echo "var pid = ".json_encode($_GET['pid']).";";
	} else {
		echo "var pid = null;";
		if (isset($disabledModuleConfigs)) {
			echo "var disabledModules = ".json_encode($disabledModuleConfigs).";";
		}
	}
	?>

	ExternalModules.sortModuleTable($('#external-modules-disabled-table'))
</script>
<?php ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'get-disabled-modules.js'); ?>
