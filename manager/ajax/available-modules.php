<?php
namespace ExternalModules;

require_once '../../classes/ExternalModules.php';

$excludedModules = @$_POST['excludedModules'];
if ($excludedModules == null) {
	$excludedModules = array();
}

?>

<table id='external-modules-available' class="table table-no-top-row-border">
	<?php

	$availableModules = ExternalModules::getAvailableModules($excludedModules);

	if (empty($availableModules)) {
		echo 'None';
	} else {
		foreach ($availableModules as $module => $config) {
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
		var availableModal = $('#external-modules-available-modal');
		var enableModal = $('#external-modules-enable-modal');
		var moduleEnabled = false;

		var reloadPage = function(){
			$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
			location.reload();
		}

		availableModal.find('.enable-button').click(function(event){
			availableModal.hide();

			var row = $(event.target).closest('tr');
			var module = row.data('module');

			var enableButton = enableModal.find('.enable-button');
			enableButton.html('Enable');
			enableModal.find('button').attr('disabled', false);

			var list = enableModal.find('.modal-body ul');
			list.html('');

			var availableModules = <?=json_encode($availableModules)?>;
			availableModules[module].permissions.forEach(function(permission){
				list.append("<li>" + permission + "</li>");
			})

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
			var availableModuleCount = $('#external-modules-available tr').length
			if(availableModuleCount == 0){
				// Reload since there aren't any more available modules to enable.
				reloadPage();
			}
			else{
				availableModal.show();
			}
		});

		availableModal.on('hide.bs.modal', function(){
			if(moduleEnabled){
				// Reload to refresh the list of enabled modules.
				reloadPage();
			}
		});
	})
</script>