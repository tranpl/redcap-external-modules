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
                        if ($enabled == "true") {
                                $enabled = true;
                        } else if ($enabled == "false"){
                                $enabled = false;
                        }
                        if (!$enabled) {
                        ?>
                                <tr data-module='<?= $prefix ?>' data-version='<?= $version ?>'>
                                        <td style='vertical-align: middle;'><?= $config['name'] ?></td>
                                        <td style='vertical-align: middle;'><?= $version ?></td>   
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
	$(function(){
		var disabledModal = $('#external-modules-disabled-modal');
		var enableModal = $('#external-modules-enable-modal');

		var reloadPage = function(){
			$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
                        var loc = window.location;
                        window.location = loc.protocol + '//' + loc.host + loc.pathname + loc.search;
		}

		disabledModal.find('.enable-button').click(function(event){
			disabledModal.hide();

			var row = $(event.target).closest('tr');
			var prefix = row.data('module');
			var version = row.find('select').val()

<?php
                        if (!isset($_GET['pid'])) {
?>
			        var enableButton = enableModal.find('.enable-button');
			        enableButton.html('Enable');
			        enableModal.find('button').attr('disabled', false);

			        var list = enableModal.find('.modal-body ul');
			        list.html('');

			        var disabledModules = <?=json_encode($disabledModuleConfigs)?>;
			        disabledModules[prefix][version].permissions.forEach(function(permission){
				        list.append("<li>" + permission + "</li>");
			        });

			        enableButton.off('click') // disable any events attached from other modules
			        enableButton.click(function(){
			                enableButton.html('Enabling...');
			                enableModal.find('button').attr('disabled', true);

				        $.post('ajax/enable-module.php', {prefix: prefix, version: version}, function (data) {
					        if (data == 'success') {
						        reloadPage();
						        disabledModal.modal('hide');
					        }
					        else {
						        var message = 'An error occurred while enabling the module: ' + data;
						        console.log('AJAX Request Error:', message);
						        alert(message);
					        }

					        enableModal.modal('hide');
				        });
			        });
<?php
                         } else {   // pid
?>
                                var pid = <?=json_encode($_GET['pid'])?>;
                                var data = {};
                                data['<?=ExternalModules::KEY_ENABLED?>'] = true;
                                $.post('ajax/save-settings.php?pid=' + pid + '&moduleDirectoryPrefix=' + prefix, data, function(data){
					if (data.status == 'success') {
                                                console.log(JSON.stringify(data));
						reloadPage();
						disabledModal.modal('hide');
					}
					else {
						var message = 'An error occurred while enabling the module: ' + data;
						console.log('AJAX Request Error:', message);
						alert(message);
					}
                                 });
<?php
                        }
?>

                        if (enableModal) {
			        enableModal.modal('show');
                        }

			return false;
		});

                if (enableModal) {
		        enableModal.on('hide.bs.modal', function(){
			        if($('#external-modules-disabled-table tr').length == 0){
				        // Reload since there aren't any more disabled modules to enable.
				        reloadPage();
			        }
			        else{
				        disabledModal.show();
			        }
		        });
                }
	})
</script>
