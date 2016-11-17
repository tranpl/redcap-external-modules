<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

?>

<table id='external-modules-disabled-table' class="table table-no-top-row-border">
	<?php

	$disabledModules = ExternalModules::getConfigs(ExternalModules::getDisabledModuleNames());

	if (empty($disabledModules)) {
		echo 'None';
	} else {
		foreach ($disabledModules as $module => $config) {
			?>
			<tr data-module='<?= $module ?>'>
				<td><?= $config->name ?></td>
				<td class="external-modules-action-buttons">
					<button class='btn enable-button'>Enable</button>
				</td>
			</tr>
			<?php
		}
	}

	?>
</table>

<script>
	$(function(){
		var disabledModal = $('#external-modules-disabled-modal');
		var enableModal = $('#external-modules-enable-modal');
		var moduleEnabled = false;

		var reloadPage = function(){
			$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
			location.reload();
		}

		disabledModal.find('.enable-button').click(function(event){
			disabledModal.hide();

			var row = $(event.target).closest('tr');
			var module = row.data('module');

			var enableButton = enableModal.find('.enable-button');
			enableButton.html('Enable');
			enableModal.find('button').attr('disabled', false);

			var list = enableModal.find('.modal-body ul');
			list.html('');

			var disabledModules = <?=json_encode($disabledModules)?>;
			disabledModules[module].permissions.forEach(function(permission){
				list.append("<li>" + permission + "</li>");
			})

			enableButton.off('click') // disable any events attached from other modules
			enableButton.click(function(){
				enableButton.html('Enabling...');
				enableModal.find('button').attr('disabled', true);

				$.post('ajax/enable-modules.php', {modules: [module]}, function (data) {
					if (data != 'success') {
						alert('An error occurred while enabling the module: ' + data);
					}

					moduleEnabled = true;
					row.remove();
					enableModal.modal('hide');
				});
			});

			enableModal.modal('show');

			return false;
		});

		enableModal.on('hide.bs.modal', function(){
			if($('#external-modules-disabled-table tr').length == 0){
				// Reload since there aren't any more disabled modules to enable.
				reloadPage();
			}
			else{
				disabledModal.show();
			}
		});

		disabledModal.on('hide.bs.modal', function(){
			if(moduleEnabled){
				// Reload to refresh the list of enabled modules.
				reloadPage();
			}
		});
	})
</script>