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

			var rv = '<select class="check-system-setting-change" name="'+name+'" id="'+name+'" ' + selectAttributes + '>'+optionsHtml+'</select>';
                        return rv;
		};

		var getInputElement = function(type, name, value, inputAttributes, suggestedSetting){
			var rv = '<input ';
                        if (type == 'radio') {
                                rv += 'class="check-system-setting-change" type="' + type + '" name="' + name + '" id="' + name + '___' + getAttributeValueHtml(value) + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
                        } else if (type == 'checkbox') {
                                rv += 'class="check-system-setting-check" type="' + type + '" name="' + name + '" id="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
                        } else {
                                rv += 'class="check-system-setting-change" type="' + type + '" name="' + name + '" id="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
                        }
                        return rv;
		};

		var getSettingColumns = function(setting, inputAttributes){
			var html = "<td><label>" + setting.name + ":</label></td>";

			var type = setting.type;
			var key = setting.key;
			var value = setting.value;


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
				inputHtml = "<div style='text-align: left; display: inline-block;'>";
				for(var i in setting.choices ){
					var choice = setting.choices[i];

					var checked = ''
					if(choice.value == value){
						checked += ' checked';
					}

					inputHtml += getInputElement(type, key, choice.value, inputAttributes + checked) + '<label>' + choice.name + '</label><br>';
				}
                                inputHtml += "</div>";
			}
			else{
				if(type == 'checkbox' && value == 'true'){
					inputAttributes += ' checked';
				}

				inputHtml = getInputElement(type, key, value, inputAttributes);
			}

			html += "<td style='text-align: center;'>" + inputHtml + "</td>";

			return html;
		};

		var getSystemSettingColumns = function(setting){
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

			return s;
		}

		var getProjectSettingColumns = function(setting, system, prefix){
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
                                if (pid) {
				        columns += '<td style="text-align: center; width: 150px;"><!--'+JSON.stringify(setting)+'-->';
                                        if (typeof setting.systemValue != "undefined") {
                                                var style = "";
                                                if ((typeof setting.value != "undefined") && (setting.systemValue == setting.value)) {
                                                    style = "display: none;";
                                                }
                                                columns += '<button class="override-system-setting" style="'+style+'">Use System Setting</button>';
                                        }
                                        columns += '</td>';
                                } else {
				        columns += '<td></td>';
                                }
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

		var getSettingRows = function(system, configSettings, savedSettings, prefix){
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
					rowsHtml += '<tr style="vertical-align: middle; height: 50px;">' + getSystemSettingColumns(setting) + '</tr>';
				}
				else if(shouldShowSettingOnProjectManagementPage(setting, system)){
					rowsHtml += '<tr ';
                                        if (typeof setting.systemValue != "undefined") {
                                                rowsHtml += 'data-system-value="'+setting.systemValue+'" ';
                                        }
                                        rowsHtml += 'style="vertical-align: middle; height: 50px;">' + getProjectSettingColumns(setting, system, prefix) + '</tr>';
				}
			});

			return rowsHtml;
		};

                var enableForProject = function(pid, prefix) {
                
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

				if(pid) {
				        settingsHtml += getSettingRows(false, config['system-settings'], savedSettings, moduleDirectoryPrefix);
					settingsHtml += getSettingRows(false, config['project-settings'], savedSettings, moduleDirectoryPrefix);
				} else {
				        settingsHtml += getSettingRows(true, config['system-settings'], savedSettings, moduleDirectoryPrefix);
                                }

				tbody.html(settingsHtml);

				configureSettings(config['system-settings'], savedSettings);
			});
		});

                var checkSetting = function (ob) {
                        var val = ob.val();
			var systemValue = ob.closest('tr').data("system-value");
                        if (typeof systemValue != "undefined") {
			        var buttons = ob.closest('tr').find('td:nth-child(3)').find('button');
                                if (val != systemValue) {
                                        buttons.show();
                                } else {
                                        buttons.hide();
                                }
                        }
                };

		configureModal.on('click', '.check-system-setting-check', function(){
                        checkSetting($(this));
                });

		configureModal.on('change', '.check-system-setting-change', function(){
                        checkSetting($(this));
                });

		configureModal.on('click', '.override-system-setting', function(){
			var overrideButton= $(this);
			var systemValue = overrideButton.closest('tr').data('system-value');
			var inputs = overrideButton.closest('tr').find('td:nth-child(2)').find('input, select');
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
                        overrideButton.hide();
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
						value = true;
					}
					else{
						value = false;
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
			if(pid === null){
				pidString = '';
			}

			$.post('ajax/save-settings.php?pid=' + pidString + '&moduleDirectoryPrefix=' + moduleDirectoryPrefix, data, function(data){
				if(data.status != 'success'){
					alert('An error occurred while saving settings: ' + data);
					configureModal.show();
					return;
				}

				// Reload the page reload after saving settings, in case a settings affects some page behavior (like which menu items are visible).
                                var loc = window.location;
                                window.location = loc.protocol + '//' + loc.host + loc.pathname + loc.search;
			});
		});
	});
