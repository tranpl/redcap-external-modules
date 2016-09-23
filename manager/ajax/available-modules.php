<?php
namespace ExternalModules;

require_once '../../classes/Modules.php';

$excludedModules = @$_POST['excludedModules'];
if ($excludedModules == null) {
	$excludedModules = array();
}

?>

<table id='modules-available' class="table table-no-top-row-border">
	<?php

	$availableModules = Modules::getAvailableModules($excludedModules);

	if (empty($availableModules)) {
		echo 'None';
	} else {
		foreach ($availableModules as $module => $config) {
			?>
			<tr data-module='<?= $module ?>'>
				<td><?= $config->name ?></td>
				<td class="modules-action-buttons">
					<button class='btn install-button'>Install</button>
				</td>
			</tr>
			<?php
		}
	}

	?>
</table>

<script>
	$(function(){
		var availableModal = $('#modules-available-modal');
		var installModal = $('#modules-install-modal');
		var moduleInstalled = false;

		var reloadPage = function(){
			$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
			location.reload();
		}

		availableModal.find('.install-button').click(function(event){
			availableModal.hide();

			var row = $(event.target).closest('tr');
			var module = row.data('module');

			var installButton = installModal.find('.install-button');
			installButton.html('Install');
			installModal.find('button').attr('disabled', false);

			var list = installModal.find('.modal-body ul');
			list.html('');

			var availableModules = <?=json_encode($availableModules)?>;
			availableModules[module].permissions.forEach(function(permission){
				list.append("<li>" + permission + "</li>");
			})

			installButton.click(function(){
				installButton.html('Installing...');
				installModal.find('button').attr('disabled', true);

				$.post('ajax/install-modules.php', {modules: [module]}, function (data) {
					if (data != 'success') {
						alert('An error occurred while installing the module: ' + data);
					}

					moduleInstalled = true;
					row.remove();
					installModal.modal('hide');
				});
			});

			installModal.modal('show');

			return false;
		});

		installModal.on('hide.bs.modal', function(){
			var availableModuleCount = $('#modules-available tr').length
			if(availableModuleCount == 0){
				// Reload since there aren't any more available modules to install.
				reloadPage();
			}
			else{
				availableModal.show();
			}
		});

		availableModal.on('hide.bs.modal', function(){
			if(moduleInstalled){
				// Reload to refresh the list of installed modules.
				reloadPage();
			}
		});
	})
</script>