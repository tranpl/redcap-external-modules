<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/Modules.php';
?>

<h3>Installed Modules</h3>

<table id='modules-installed' class="table">
	<?php

	$installedModules = Modules::getInstalledModules();

	if (empty($installedModules)) {
		echo 'None';
	} else {
		foreach ($installedModules as $module => $config) {
			?>
			<tr data-module='<?= $module ?>'>
				<td><?= $config->name ?></td>
				<td class="modules-action-buttons">
					<button class='btn modules-configure-button' data-toggle="modal" data-target="#modules-configure-modal">Configure</button>
					<?php if (!isset($project_id)) { ?>
						<button class='btn modules-update-button'>Update</button>
						<button class='btn modules-remove-button'>Remove</button>
					<?php } ?>
				</td>
			</tr>
			<?php
		}
	}

	?>
</table>
