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
		var configureModal = $('#external-modules-configure-modal');

		var getSettingColumns = function(settingKey, setting, currentValue, inputAttributes){
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

				return '<input type="' + type + '" name="' + settingKey + '" value="' + getAttributeValueHtml(value) + '" ' + checked + ' ' + inputAttributes + '>';
			};

			var inputHtml;
			if(type == 'dropdown'){
				var optionsHtml = '';
				for(var value in setting.choices ){
					optionsHtml += '<option value="' + value + '">' +  choices[value] + '</option>';
				}

				inputHtml = '<select name="' + settingKey + '" ' + inputAttributes + '>' + optionsHtml + '</select>';
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

			return html;
		};

		var getGlobalSettingColumns = function(key, setting, value){
			var columns = getSettingColumns(key, setting, value, '');
			columns += '<td><select name="' + key + '_allow-project-overrides">';
			columns += '<option>Superusers Only</option>';
			columns += '<option>Design Rights Users</option>';
			columns += '<option>Any User</option>';
			columns += '</select></td>';

			return columns;
		};

		var getAttributeValueHtml = function(s){
			if(s == null){
				return ''
			}

			s = s.replace(/"/g, '&quot;');
			s = s.replace(/'/g, '&apos;');

			return s
		}

		var getProjectSettingColumns = function(key, setting, value, globalValue){
			var inputAttributes = '';
			var overrideCheckboxAttributes = 'data-global-value="' + getAttributeValueHtml(globalValue) + '"';
			if(value == globalValue){
				inputAttributes += ' disabled';
			}
			else{
				overrideCheckboxAttributes += ' checked';
			}

			var columns = getSettingColumns(key, setting, value, inputAttributes);
			columns += '<td><input type="checkbox" class="override-global-setting" ' + overrideCheckboxAttributes + '></td>';

			return columns;
		};

		var getSettingRows = function(global, configSettings, savedSettings){
			var rowsHtml = ''

			for(var key in configSettings){
				var config = configSettings[key];
				var saved = savedSettings[key];

				var value = null;
				var globalValue = null;
				if(saved){
					value = saved.value;
					globalValue = saved.global_value;
				}

				var columns;
				if(!pid){
					columns = getGlobalSettingColumns(key, config, value);
				}
				else if(!global || config['allow-project-overrides']){
					columns = getProjectSettingColumns(key, config, value, globalValue);
				}

				rowsHtml +=  '<tr>' + columns + '</tr>';
			}

			return rowsHtml;
		};

		$('#external-modules-enabled').on('click', '.external-modules-configure-button', function(){
			var moduleDirectoryName = $(this).closest('tr').data('module');
			var config = configsByName[moduleDirectoryName];

			configureModal.find('.module-name').html(config.name);
			var tbody = configureModal.find('tbody');
			tbody.html('');
			configureModal.modal('show');

			$.post('ajax/get-settings.php', {moduleDirectoryName: moduleDirectoryName}, function(data){
				if(data.status != 'success'){
					return;
				}

				var savedSettings = data.settings;

				var settingsHtml = "";
				settingsHtml += getSettingRows(true, config['global-settings'], savedSettings);

				if(pid) {
					settingsHtml += getSettingRows(false, config['project-settings'], savedSettings);
				}

				tbody.html(settingsHtml);
			});
		});

		configureModal.on('click', '.override-global-setting', function(){
			var overrideCheckbox = $(this);
			var globalValue = overrideCheckbox.data('global-value');
			var inputs = overrideCheckbox.closest('tr').find('td:nth-child(2)').find('input, select');

			if(overrideCheckbox.prop('checked')){
				inputs.prop('disabled', false)
			}
			else{
				var type = inputs[0].type;
				if(type == 'radio'){
					inputs.filter('[value=' + globalValue + ']').click()
				}
				else if(type == 'checkbox'){
					inputs.prop('checked', globalValue)
				}
				else{ // text or select
					inputs.val(globalValue)
				}

				inputs.prop('disabled', true);
			}
		});
	});
</script>