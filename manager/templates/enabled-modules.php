<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';

$pid = $_GET['pid'];
?>

<h3>Enabled Modules</h3>

<table id='external-modules-enabled' class="table">
	<?php

	$versionsByPrefix = ExternalModules::getEnabledModules();
	$configsByPrefix = array();

	if (empty($versionsByPrefix)) {
		echo 'None';
	} else {
		foreach ($versionsByPrefix as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version);
			$configsByPrefix[$prefix] = $config;
			?>
			<tr data-module='<?= $prefix ?>'>
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
		var configsByPrefix = <?=json_encode($configsByPrefix)?>;
		var configureModal = $('#external-modules-configure-modal');
		var isSuperUser = <?=json_encode(SUPER_USER == 1)?>;

		var getSelectElement = function(name, choices, selectedValue, selectAttributes){
			if(!selectAttributes){
				selectAttributes = '';
			}

			var optionsHtml = '';
			for(var i in choices ){
				var choice = choices[i];
				var value = choice.value;

				var optionAttributes = ''
				if(value == selectedValue){
					optionAttributes += 'selected'
				}

				optionsHtml += '<option value="' + getAttributeValueHtml(value) + '" ' + optionAttributes + '>' + choice.name + '</option>';
			}

			return '<select name="' + name + '" ' + selectAttributes + '>' + optionsHtml + '</select>';
		};

		var getInputElement = function(type, name, value, inputAttributes){
			return '<input type="' + type + '" name="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
		};

		var getSettingColumns = function(setting, inputAttributes){
			var html = "<td><label>" + setting.name + ":</label></td>";

			var type = setting.type;
			var key = setting.key
			var value = setting.value

			var inputHtml;
			if(type == 'dropdown'){
				inputHtml = getSelectElement(key, setting.choices, value, inputAttributes);
			}
			else if(type == 'radio'){
				inputHtml = "";
				for(var i in setting.choices ){
					var choice = setting.choices[i];

					var checked = ''
					if(choice.value == value){
						checked += ' checked';
					}

					inputHtml += getInputElement(type, key, choice.value, inputAttributes + checked) + '<label>' + choice.name + '</label><br>';
				}
			}
			else{
				if(type == 'checkbox' && value == 1){
					inputAttributes += ' checked';
				}

				inputHtml = getInputElement(type, key, value, inputAttributes);
			}

			html += "<td>" + inputHtml + "</td>";

			return html;
		};

		var getGlobalSettingColumns = function(setting){
			var columns = getSettingColumns(setting, '');

			if(setting['allow-project-overrides']){
				var overrideChoices = [
					{ value: '', name: 'Superusers Only' },
					{ value: '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS?>', name: 'Project Design and Setup Users' },
				];
				columns += '<td>' + getSelectElement(setting.overrideLevelKey, overrideChoices, setting.overrideLevelValue) + '</td>';
			}
			else{
				columns += '<td></td>';
			}

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

		var getProjectSettingColumns = function(setting, global){
			var inputAttributes = '';
			var overrideCheckboxAttributes = 'data-global-value="' + getAttributeValueHtml(setting.globalValue) + '"';

			if(global && setting.value == setting.globalValue){
				inputAttributes += ' disabled';
			}
			else{
				overrideCheckboxAttributes += ' checked';
			}

			var columns = getSettingColumns(setting, inputAttributes);

			if(global){
				columns += '<td><input type="checkbox" class="override-global-setting" ' + overrideCheckboxAttributes + '></td>';
			}
			else{
				columns += '<td></td>';
			}

			return columns;
		};

		var shouldShowSettingOnProjectManagementPage = function(setting, global) {
			if(!global){
				// Always show project level settings.
				return true;
			}

			if(setting.overrideLevelValue == null && !isSuperUser){
				// Hide this setting since the override level will prevent the non-superuser from actually saving it.
				return false;
			}

			// Checking whether a global setting is actually overridden is necessary for the UI to reflect when
			// settings are overridden prior to allow-project-overrides being set to false.
			var alreadyOverridden = setting.value != setting.globalValue;

			return setting['allow-project-overrides'] || alreadyOverridden;
		}

		var getSettingRows = function(global, configSettings, savedSettings){
			var rowsHtml = ''

			for(var key in configSettings){
				var setting = $.extend({}, configSettings[key]);
				setting.key = key;

				var saved = savedSettings[key];
				if(saved){
					setting.value = saved.value;
					setting.globalValue = saved.global_value;
				}

				setting.overrideLevelKey = key + '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX?>';
				var overrideLevel = savedSettings[setting.overrideLevelKey];
				if(overrideLevel){
					setting.overrideLevelValue = overrideLevel.value
				}

				if(!pid){
					rowsHtml += '<tr>' + getGlobalSettingColumns(setting) + '</tr>';
				}
				else if(shouldShowSettingOnProjectManagementPage(setting, global)){
					rowsHtml += '<tr>' + getProjectSettingColumns(setting, global) + '</tr>';
				}
			}

			return rowsHtml;
		};

		$('#external-modules-enabled').on('click', '.external-modules-configure-button', function(){
			var moduleDirectoryPrefix = $(this).closest('tr').data('module');
			configureModal.data('module', moduleDirectoryPrefix);

			var config = configsByPrefix[moduleDirectoryPrefix];
			configureModal.find('.module-name').html(config.name);
			var tbody = configureModal.find('tbody');
			tbody.html('');
			configureModal.modal('show');

			$.post('ajax/get-settings.php', {pid: pid, moduleDirectoryPrefix: moduleDirectoryPrefix}, function(data){
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
				inputs.prop('disabled', false);
			}
			else{
				var type = inputs[0].type;
				if(type == 'radio'){
					inputs.filter('[value=' + globalValue + ']').click();
				}
				else if(type == 'checkbox'){
					inputs.prop('checked', globalValue);
				}
				else{ // text or select
					inputs.val(globalValue);
				}

				inputs.prop('disabled', true);
			}
		});

		configureModal.on('click', 'button.save', function(){
			configureModal.hide();
			var moduleDirectoryPrefix = configureModal.data('module');

			var data = {};

			configureModal.find('input, select').each(function(index, element){
				var element = $(element);
				var globalValue = element.closest('tr').find('.override-global-setting').data('global-value');
				var name = element.attr('name');
				var type = element[0].type;

				if(!name || (type == 'radio' && !element.is(':checked'))){
					return;
				}

				var value;
				if(type == 'checkbox'){
					if(element.prop('checked')){
						value = '1';
					}
					else{
						value = '0';
					}
				}
				else{
					value = element.val();
				}

				if(value == globalValue){
					value = '';
				}

				data[name] = value;
			});

			var pidString = pid;
			if(pid == null){
				pidString = '';
			}

			$.post('ajax/save-settings.php?pid=' + pidString + '&moduleDirectoryPrefix=' + moduleDirectoryPrefix, data, function(data){
				if(data.status != 'success'){
					alert('An error occurred while saving settings: ' + data);
					configureModal.show();
					return;
				}

				configureModal.modal('hide');
			});
		});
	});
</script>