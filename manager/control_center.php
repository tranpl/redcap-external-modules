<?php
namespace ExternalModules;
require_once __DIR__ . '/../classes/ExternalModules.php';
require_once '../..' . APP_PATH_WEBROOT . 'ControlCenter/header.php';

ExternalModules::addResource('css/style.css');

?>

<h4 style="margin-top: 0;">
	<img src="<?= APP_PATH_WEBROOT . 'Resources/images/brick.png' ?>">
	Module Management
</h4>

<br>
<br>
<br>
<button data-toggle="modal" data-target="#external-modules-disabled-modal">Enable Module(s)</button>
<br>
<br>

<?php ExternalModules::safeRequireOnce('templates/enabled-modules.php'); ?>

<div id="external-modules-disabled-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Disabled Modules</h4>
			</div>
			<div class="modal-body">
				<form>
					<div class="loading-indicator"></div>
				</form>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-enable-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Enable Module: <span class="module-name"></span></h4>
			</div>
			<div class="modal-body">
				<p>This module requests the following permissions:</p>
				<ul></ul>
			</div>
			<div class="modal-footer">
				<button data-dismiss="modal">Cancel</button>
				<button class="enable-button"></button>
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
					<thead>
						<tr>
							<th colspan="2">Global Settings</th>
							<th>Allow Projects<br>To Override</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<div class="modal-footer">
				<button data-dismiss="modal">Cancel</button>
				<button class="save">Save</button>
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

		var disabledModal = $('#external-modules-disabled-modal');
		var form = disabledModal.find('.modal-body form');

		disabledModal.on('show.bs.modal', function () {
			var loadingIndicator = disabledModal.find('.loading-indicator');
			if (loadingIndicator.length == 1) {
				new Spinner().spin(loadingIndicator[0]);

				$.post('ajax/get-disabled-modules.php', null, function (html) {
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

		$('.external-modules-disable-button').click(function (event) {
			var button = $(event.target);
			button.attr('disabled', true);
			button.html('Disabling...');

			var row = button.closest('tr');
			var module = row.data('module');
			$.post('ajax/disable-module.php', {module: module}, function (data) {
				if (data == 'success') {
					var table = row.closest('table');
					row.remove();

					if(table.find('tr').length == 0){
						table.html('None');
					}
				}
				else {
					alert('An error occurred while disabling the ' + module + ' module: ' + data);
				}
			});
		});
	})
</script>

<?php

require_once '../..' . APP_PATH_WEBROOT . 'ControlCenter/footer.php';
