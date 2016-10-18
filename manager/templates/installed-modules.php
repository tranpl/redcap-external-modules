<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';
?>

<h3>Installed Modules</h3>

<table id='external-modules-enabled' class="table">
	<?php

	$configsByName = ExternalModules::getConfigs(ExternalModules::getEnabledModuleNames());

	if (empty($configsByName)) {
		echo 'None';
	} else {
		foreach ($configsByName as $module => $config) {
			?>
			<tr data-module='<?= $module ?>'>
				<td><?= $config->name ?></td>
				<td class="external-modules-action-buttons">
					<button class='btn external-modules-configure-button' data-toggle="modal" data-target="#external-modules-configure-modal">Configure</button>
					<?php if (!isset($project_id)) { ?>
						<button class='btn external-modules-update-button'>Update</button>
						<button class='btn external-modules-remove-button'>Remove</button>
					<?php } ?>
				</td>
			</tr>
			<?php
		}
	}

	?>
</table>
