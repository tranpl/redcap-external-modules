	$(function(){
		var configureModal = $('#external-modules-configure-modal');
	
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
			else if(type == 'field-list'){
				inputHtml = getSelectElement(key, setting.choices, value, inputAttributes);
			}
			else if(type == 'form-list'){
				inputHtml = getSelectElement(key, setting.choices, value, inputAttributes);
			}
			else if(type == 'project-id'){
				inputAttributes += ' class="project_id_textbox" id="test-id"';
				inputHtml = "<div style='width:200px'>" + getSelectElement(key, setting.choices, value, inputAttributes) + "</div>";
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
	
		var getSystemSettingColumns = function(setting){
			var columns = getSettingColumns(setting, '');
	
			if(setting['allow-project-overrides']){
				var overrideChoices = [
					{ value: '', name: 'Superusers Only' },
					{ value: '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS?>', name: 'Project Design and Setup Users' },
				];
	
				var selectAttributes = '';
				if(setting.key == '<?=ExternalModules::KEY_ENABLED?>'){
					// For now, we've decided that only super users can enable modules on projects.
					// To enforce this, we disable this override dropdown for ExternalModules::KEY_ENABLED.
					selectAttributes = 'disabled'
				}
	
				columns += '<td>' + getSelectElement(setting.overrideLevelKey, overrideChoices, setting.overrideLevelValue, selectAttributes) + '</td>';
			}
			else{
				columns += '<td></td>';
			}
	
			return columns;
		};
	
		var getAttributeValueHtml = function(s){
			if(typeof s == 'string'){
				s = s.replace(/"/g, '&quot;');
				s = s.replace(/'/g, '&apos;');
			}
	
			return s
		}
	
		var getProjectSettingColumns = function(setting, system){
			var setting = $.extend({}, setting);
			var projectName = setting['project-name'];
			if(projectName){
			        setting.name = projectName;
			}
	
			var inputAttributes = '';
			var overrideCheckboxAttributes = 'data-system-value="' + getAttributeValueHtml(setting.systemValue) + '"';
	
			if(system && setting.value == setting.systemValue){
				inputAttributes += ' disabled';
			}
			else{
				overrideCheckboxAttributes += ' checked';
			}
	
			var columns = getSettingColumns(setting, inputAttributes);
	
			if(system){
				columns += '<td><input type="checkbox" class="override-system-setting" ' + overrideCheckboxAttributes + '></td>';
			}
			else{
				columns += '<td></td>';
			}
	
			return columns;
		};
	
		var shouldShowSettingOnProjectManagementPage = function(setting, system) {
			if(!system){
				// Always show project level settings.
				return true;
			}
	
			if(setting.overrideLevelValue == null && !isSuperUser){
				// Hide this setting since the override level will prevent the non-superuser from actually saving it.
				return false;
			}
	
			// Checking whether a system setting is actually overridden is necessary for the UI to reflect when
			// settings are overridden prior to allow-project-overrides being set to false.
			var alreadyOverridden = setting.value != setting.systemValue;
	
			return setting['allow-project-overrides'] || alreadyOverridden;
		}
	
		var getSettingRows = function(system, configSettings, savedSettings){
			var rowsHtml = ''
	
			configSettings.forEach(function(setting){
				var setting = $.extend({}, setting);
				var saved = savedSettings[setting.key];
				if(saved){
					setting.value = saved.value;
					setting.systemValue = saved.system_value;
				}
	
				setting.overrideLevelKey = setting.key + '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX?>';
				var overrideLevel = savedSettings[setting.overrideLevelKey];
				if(overrideLevel){
					setting.overrideLevelValue = overrideLevel.value
				}
	
				if(!pid){
					rowsHtml += '<tr>' + getSystemSettingColumns(setting) + '</tr>';
				}
				else if(shouldShowSettingOnProjectManagementPage(setting, system)){
					rowsHtml += '<tr>' + getProjectSettingColumns(setting, system) + '</tr>';
				}
			});
	
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
				settingsHtml += getSettingRows(true, config['system-settings'], savedSettings);
	
				if(pid) {
					settingsHtml += getSettingRows(false, config['project-settings'], savedSettings);
				}
	
				tbody.html(settingsHtml);
	
				configureSettings(config['system-settings'], savedSettings);
			});
		});
	
		configureModal.on('click', '.override-system-setting', function(){
			var overrideCheckbox = $(this);
			var systemValue = overrideCheckbox.data('system-value');
			var inputs = overrideCheckbox.closest('tr').find('td:nth-child(2)').find('input, select');
	
			if(overrideCheckbox.prop('checked')){
				inputs.prop('disabled', false);
			}
			else{
				var type = inputs[0].type;
				if(type == 'radio'){
					inputs.filter('[value=' + systemValue + ']').click();
				}
				else if(type == 'checkbox'){
					inputs.prop('checked', systemValue);
				}
				else{ // text or select
					inputs.val(systemValue);
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
				var systemValue = element.closest('tr').find('.override-system-setting').data('system-value');
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
	
				if(value == systemValue){
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
	
				// Reload the page reload after saving settings, in case a settings affects some page behavior (like which menu items are visible).
				location.reload();
			});
		});
	});
