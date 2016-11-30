<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';

$pid = $_GET['pid'];
?>

<h3>Enabled Modules</h3>

<table id='external-modules-enabled' class="table">
	<?php

	$configsByName = ExternalModules::getConfigs(ExternalModules::getEnabledModuleNames());

	if (empty($configsByName)) {
		echo 'None';
	} else {
		foreach ($configsByName as $module => $config) {
			?>
			<tr data-module='<?= $module ?>'>
				<td><?= $config['name'] ?></td>
				<td class="external-modules-action-buttons">
					<button class='external-modules-configure-button'>Configure</button>
					<?php if (!isset($pid)) { ?>
						<button class='external-modules-disable-button'>Disable</button>
					<?php } ?>
				</td>
			</tr>
			<?php
		}
	}

	?>
</table>

<script>
	$(function(){
		var pid = <?=json_encode($pid)?>;
		var configsByName = <?=json_encode($configsByName)?>;

		var getSettingRow = function(settingKey, setting, currentValue){
			var html = "<td><label>" + setting.name + ":</label></td>";

			var type = setting.type;
			var choices = setting.choices;

			var getInputElement = function(value){
				var checked = '';
				if(value == currentValue && $.inArray(value, ['radio', 'checkbox'])){
					checked = 'checked';
				}

				if(value == null){
					value = '';
				}

				return '<input type="' + type + '" name="' + settingKey + '" value="' + value + '" ' + checked + '>';
			};

			var inputHtml
			if(type == 'dropdown'){
				var optionsHtml = '';
				for(var value in setting.choices ){
					optionsHtml += '<option value="' + value + '">' +  choices[value] + '</option>';
				}

				inputHtml = '<select name="' + settingKey + '">' + optionsHtml + '</select>';
			}
			else if(type == 'radio'){
				inputHtml = "";
				for(var value in setting.choices ){
					inputHtml += getInputElement(value) + '<label>' + choices[value] + '</label><br>';
				}
			}
			else{
				inputHtml = getInputElement(currentValue);
			}

			html += "<td>" + inputHtml + "</td>";

			html += '<td><select name="' + settingKey + '_allow-project-overrides">';
			html += '<option>Superusers Only</option>';
			html += '<option>Design Rights Users</option>';
			html += '<option>Any User</option>';
			html += '</select></td>';

			return "<tr>" + html + "</tr>";
		};

		$('#external-modules-enabled').on('click', '.external-modules-configure-button', function(){
			var moduleDirectoryName = $(this).closest('tr').data('module');
			var modal = $('#external-modules-configure-modal');
			var config = configsByName[moduleDirectoryName];

			modal.find('.module-name').html(config.name);
			var tbody = modal.find('tbody');
			tbody.html('');
			modal.modal('show');

			$.post('ajax/get-settings.php', {moduleDirectoryName: moduleDirectoryName}, function(data){
				if(data.status != 'success'){
					return;
				}

				var existingSettings = data.settings;

				var globalSettings = config['global-settings'];
				var settingHtml = "";

				for(var key in globalSettings){
					var setting = globalSettings[key];
					var value = existingSettings[key];

					if(!pid || setting['allow-project-overrides']){
						settingHtml += getSettingRow(key, setting, value);
					}
				}
				
				tbody.html(settingHtml);
			});
		});
	});
</script>