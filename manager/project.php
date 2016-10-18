<?php
namespace ExternalModules;
require_once __DIR__ . '/../classes/ExternalModules.php';
require_once ExternalModules::getProjectHeaderPath();

ExternalModules::addResource('css/style.css');

require_once 'templates/enabled-modules.php';

?>

<style>
	#external-modules-configure-modal th:nth-child(2),
	#external-modules-configure-modal td:nth-child(3) {
		text-align: center;
	}
</style>

<div id="external-modules-configure-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Configure Module: <span class="module-name"></span></h4>
			</div>
			<div class="modal-body">
				<table class="table table-no-top-row-border">
					<tr>
						<th colspan="2">Project Settings</th>
						<th>Override Default</th>
					</tr>
					<tr>
						<td>
							<label>Enable on this project: </label>
						</td>
						<td>
							<input type="checkbox" name="enabled">
						</td>
						<?= ExternalModules::getProjectSettingOverrideCheckbox() ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Setting 1:</label>
						</td>
						<td>
							<input name="module-setting-1">
						</td>
						<?= ExternalModules::getProjectSettingOverrideCheckbox() ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Setting 2:</label>
						</td>
						<td>
							<input type="checkbox" name="module-setting-2">
						</td>
						<?= ExternalModules::getProjectSettingOverrideCheckbox() ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Project Setting 1:</label>
						</td>
						<td>
							<input name="module-setting-1">
						</td>
						<td></td>
					</tr>
					<tr>
						<td>
							<label>Module Defined Project Setting 2:</label>
						</td>
						<td>
							<input type="checkbox" name="module-setting-2">
						</td>
						<td></td>
					</tr>
				</table>
			</div>
			<div class="modal-footer">
				<button class="btn" data-dismiss="modal">Cancel</button>
				<button class="btn">Save</button>
			</div>
		</div>
	</div>
</div>

<?php

require_once ExternalModules::getProjectFooterPath();