<?php
namespace ExternalModules;
require_once __DIR__ . '/../classes/ExternalModules.php';
require_once '../..' . APP_PATH_WEBROOT . 'ControlCenter/header.php';

ExternalModules::addResource('css/style.css');

?>

<h4 style="margin-top: 0;">
	<img src="<?= ExternalModules::getIconPath() ?>">
	Module Management
</h4>

<br>
<br>
<br>
<button id="external-modules-install-button" class="btn" data-toggle="modal" data-target="#external-modules-available-modal">Install
	Module(s)
</button>
<br>
<br>

<?php require_once 'templates/installed-modules.php'; ?>

<div id="external-modules-available-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Available Modules</h4>
			</div>
			<div class="modal-body">
				<form>
					<div class="loading-indicator"></div>
				</form>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-install-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Install Module: <span class="module-name"></span></h4>
			</div>
			<div class="modal-body">
				<p>This module requests the following permissions:</p>
				<ul></ul>
			</div>
			<div class="modal-footer">
				<button class="btn" data-dismiss="modal">Cancel</button>
				<button class="btn install-button"></button>
			</div>
		</div>
	</div>
</div>

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
						<th colspan="2">Global Settings</th>
						<th>Allow Project Overrides</th>
					</tr>
					<tr>
						<td>
							<label>Enable on projects by default: </label>
						</td>
						<td>
							<input type="checkbox" name="enabled">
						</td>
						<?= ExternalModules::getSettingOverrideDropdown('enabled') ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Setting 1:</label>
						</td>
						<td>
							<input name="module-setting-1">
						</td>
						<?= ExternalModules::getSettingOverrideDropdown('module-setting-1') ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Setting 2:</label>
						</td>
						<td>
							<input type="checkbox" name="module-setting-2">
						</td>
						<?= ExternalModules::getSettingOverrideDropdown('module-setting-2') ?>
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

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/spin.js/2.3.2/spin.min.js"
		integrity="sha256-PieqE0QdEDMppwXrTzSZQr6tWFX3W5KkyRVyF1zN3eg=" crossorigin="anonymous"></script>

<script>
	$(function () {
		// Make Control Center the active tab
		$('#sub-nav li.active').removeClass('active');
		$('#sub-nav a[href*="ControlCenter"]').closest('li').addClass('active');

		var availableModal = $('#external-modules-available-modal');
		var form = availableModal.find('.modal-body form');

		var getInstalledModules = function () {
			var modules = [];
			$('#external-modules-installed tr').each(function (index, element) {
				modules.push($(element).data('module'));
			});

			return modules;
		}

		availableModal.on('show.bs.modal', function () {
			var loadingIndicator = availableModal.find('.loading-indicator');
			if (loadingIndicator.length == 1) {
				new Spinner().spin(loadingIndicator[0]);

				$.post('ajax/available-modules.php', {excludedModules: getInstalledModules()}, function (html) {
					form.html(html);
				})
			}
		});

		var configureModal = $('#external-modules-configure-modal');
		configureModal.on('show.bs.modal', function () {
			var button = $(event.target);
			var moduleName = $(button.closest('tr').find('td')[0]).html();
			configureModal.find('.module-name').html(moduleName);
		});

		$('.external-modules-update-button').click(function (event) {
			alert('There are currently no updates available for this module.')
		});

		$('.external-modules-remove-button').click(function (event) {
			// TODO
			alert('Removing modules has been disabled until we have the ability to configure a writable installed modules directory.');
			return;

			var button = $(event.target);
			button.attr('disabled', true);
			button.html('Removing...');

			var row = button.closest('tr');
			var module = row.data('module');
			$.post('ajax/remove-module.php', {module: module}, function (data) {
				if (data == 'success') {
					// TODO - This will leave a blank list when the last module is removed.
					// We might want to display "none" or something
					row.remove()
				}
				else {
					alert('An error occurred while removing the ' + module + ' module: ' + data);
				}
			});
		});
	})
</script>

<?php

require_once '../..' . APP_PATH_WEBROOT . 'ControlCenter/footer.php';
