<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';

if(!ExternalModules::areTablesPresent()){
	echo 'Before using External Modules, you must run the following sql to create the appropriate tables:<br><br>';
	echo '<textarea style="width: 100%; height: 300px">' . htmlspecialchars(file_get_contents(__DIR__ . '/../../sql/create tables.sql')) . '</textarea>';
	return;
}

$pid = $_GET['pid'];
?>

<div id="external-modules-disabled-modal" class="modal fade" role="dialog" data-backdrop="static">
        <div class="modal-dialog">
                <div class="modal-content">
                        <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title">Available Modules</h4>
                        </div>
                        <div class="modal-body">
                                <form>
                                </form>
                        </div>
                </div>
        </div>
</div>

<br>
<?php if (isset($_GET['pid'])) { ?>

<p>External modules combine and replace what REDCap previously has called plugins and hooks.
Below is a list of enabled modules that can be used in this project. You can see what other modules are
available by searching for additional modules. These are groups of code from outside sources
that enhance REDCap functioning for specific purposes.</p> 

<?php } else { ?>

<p>External modules combine and replace what REDCap previously has called plugins and hooks.
Below is a list of enabled modules (consisting of hooks and plugins) that are available for your users' use.
They can be enabled system-wide or they can be enabled (opt-in style) on a project-level. Default values for each module,
where desired, have been set by the author of the module. Each system can override these defaults by configuring them
here. In turn, each project can override this set of defaults with their own value.</p>

<?php } ?>
<br>
<button id="external-modules-enable-modules-button">Search for Additional Module(s)</button>
<br>
<br>

<?php if (isset($_GET['pid'])) { ?>
<h3>Currently Enabled Modules</h3>
<?php } else { ?>
<h3>Modules Currently Available on this System</h3>
<?php } ?>

<?php if (isset($_GET['pid'])) { ?>
        <script>
	        var pid = <?=json_encode($pid)?>;
                $(function () {
                        // Make Control Center the active tab
                        $('#sub-nav li.active').removeClass('active');
                        $('#sub-nav a[href*="ControlCenter"]').closest('li').addClass('active');
        
                        var disabledModal = $('#external-modules-disabled-modal');
                        $('#external-modules-enable-modules-button').click(function(){
                                var form = disabledModal.find('.modal-body form');
                                var loadingIndicator = $('<div class="loading-indicator"></div>');
                                form.html('');
                                form.append(loadingIndicator);
        
                                // This ajax call was originally written thinking the list of available modules would come from a central repo.
                                // It may not be necessary any more.
                                $.post('ajax/get-disabled-modules.php?pid='+pid, { }, function (html) {
                                        form.html(html);
                                })
        
                                disabledModal.modal('show');
                        });
                });
        </script>
<?php } ?>

<table id='external-modules-enabled' class="table">
	<?php

	$versionsByPrefix = ExternalModules::getEnabledModules();
	$configsByPrefix = array();

	if (empty($versionsByPrefix)) {
		echo 'None';
	} else {
		foreach ($versionsByPrefix as $prefix => $version) {
                        if (isset($_GET['pid'])) {
			        $config = ExternalModules::getConfig($prefix, $version, $_GET['pid']);
                        } else {
			        $config = ExternalModules::getConfig($prefix, $version);
                        }
			$configsByPrefix[$prefix] = $config;
                        $enabled = false;
                        if (isset($_GET['pid'])) {
                                $enabled = ExternalModules::getSetting($prefix, $_GET['pid'], ExternalModules::KEY_ENABLED);
                                if ($enabled == "false") {
                                        $enabled = false;
                                } else if ($enabled == "true") {
                                        $enabled = true;
                                }
                        }
                        if ((isset($_GET['pid']) && $enabled) || (!isset($_GET['pid']) && isset($config['system-settings']))) {
			?>
			        <tr data-module='<?= $prefix ?>' data-version='<?= $version ?>'>
				        <td><?= $config['name'] . ' - ' . $version ?></td>
				        <td class="external-modules-action-buttons">
					        <button class='external-modules-configure-button'>Configure</button>
						<button class='external-modules-disable-button'>Disable</button>
				        </td>
			        </tr>
			<?php
                        }
		}
	}

	?>
</table>

<?php
// JSON_PARTIAL_OUTPUT_ON_ERROR was added here to fix an odd conflict between field-list and form-list types
// and some Hebrew characters on the "Israel: Healthcare Personnel (Hebrew)" project that could not be json_encoded.
// This workaround allows configs to be encoded anyway, even though the unencodable characters will be excluded
// (causing form-list and field-list to not work for any fields with unencodeable characters).
// I spent a couple of hours trying to find a solution, but was unable.  This workaround will have to do for now.
$configsByPrefixJSON = json_encode($configsByPrefix, JSON_PARTIAL_OUTPUT_ON_ERROR);
if($configsByPrefixJSON == null){
	echo '<script>alert(' . json_encode('An error occurred while converting the configurations to JSON: ' . json_last_error_msg()) . ');</script>';
	die();
}
?>

<script>
	$(function(){
<<<<<<< HEAD
		var configsByPrefix = <?=json_encode($configsByPrefix)?>;
=======
		var pid = <?=json_encode($pid)?>;
		var configsByPrefix = <?=$configsByPrefixJSON?>;
>>>>>>> origin/master
		var configureModal = $('#external-modules-configure-modal');
		var isSuperUser = <?=json_encode(SUPER_USER == 1)?>;

		var getSelectElement = function(name, choices, selectedValue, selectAttributes, default_setting){
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

			var rv = '<select ';
                        if (default_setting !== "") {
                                rv += 'onchange="if ($(\'#button_'+name+'\')) { if ((this.value == \''+default_setting+'\') { $(\'#button_'+name+'\').hide(); } else { $(\'#button_'+name+'\').show(); } }" ';
                        }
                        rv += 'name="'+name+'" id="'+name+'" ' + selectAttributes + '>'+optionsHtml+'</select>';
                        return rv;
		};

		var getInputElement = function(type, name, value, inputAttributes, default_setting){
			var rv = '<input ';
                        if (type == 'radio') {
                                if (default_setting !== "") {
                                        rv += 'onclick="if ($(\'#button_'+name+'\')) { if (this.value == \''+default_setting+'\') { $(\'#button_'+name+'\').hide(); } else { $(\'#button_'+name+'\').show(); } }" ';
                                }
                                rv += 'type="' + type + '" name="' + name + '" id="' + name + '___' + getAttributeValueHtml(value) + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
                        } else if (type == 'checkbox') {
                                if (default_setting !== "") {
			                rv += 'onchange="if ($(\'#button_'+name+'\')) { if ($(this).is(\':checked\') == eval(\''+default_setting+'\')) { $(\'#button_'+name+'\').hide(); } else { $(\'#button_'+name+'\').show(); } }" ';
                                }
                                rv += 'type="' + type + '" name="' + name + '" id="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
                        } else {
                                if (default_setting !== "") {
			                rv += 'onblur="if ($(\'#button_'+name+'\')) { if (this.value == \''+default_setting+'\') { $(\'#button_'+name+'\').hide(); } else { $(\'#button_'+name+'\').show(); } }" ';
                                }
                                rv += 'type="' + type + '" name="' + name + '" id="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
                        }
                        return rv;
		};

		var getSettingColumns = function(setting, inputAttributes){
			var html = "<td><label>" + setting.name + ":</label></td>";

			var type = setting.type;
			var key = setting.key;
			var value = setting.value;
                        var default_setting = "";
                        if (typeof setting.default != "undefined") {
                                default_setting = setting.default;
                        }


			var inputHtml;
			if(type == 'dropdown'){
				inputHtml = getSelectElement(key, setting.choices, value, inputAttributes, default_setting);
			}
			else if(type == 'field-list'){
				inputHtml = getSelectElement(key, setting.choices, value, inputAttributes, default_setting);
			}
			else if(type == 'form-list'){
				inputHtml = getSelectElement(key, setting.choices, value, inputAttributes, default_setting);
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

					inputHtml += getInputElement(type, key, choice.value, inputAttributes + checked, default_setting) + '<label>' + choice.name + '</label><br>';
				}
                                inputHtml += "</div>";
			}
			else{
				if(type == 'checkbox' && value == 'true'){
					inputAttributes += ' checked';
				}

				inputHtml = getInputElement(type, key, value, inputAttributes, default_setting);
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
                                        if (typeof setting.default != "undefined") {
                                                var style = "";
                                                if ((typeof setting.value != "undefined") && (setting.default == setting.value)) {
                                                    style = "display: none;";
                                                }
                                                if (setting.type == "checkbox") {
                                                        columns += '<button style="'+style+'" id="button_'+setting.key+'" onclick="$(\'#'+setting.key+'\').prop(\'checked\', '+getAttributeValueHtml(setting.default)+'); $(\'#button_'+setting.key+'\').hide();">Use System Setting</button>';
                                                } else if (setting.type == "radio") {
                                                        columns += '<button style="'+style+'" id="button_'+setting.key+'" onclick="$(\'#'+setting.key+'___'+getAttributeValueHtml(setting.default)+'\').prop(\'checked\', true); $(\'#button_'+setting.key+'\').hide();">Use System Setting</button>';
                                                } else {
                                                        columns += '<button style="'+style+'" id="button_'+setting.key+'" onclick="$(\'#'+setting.key+'\').val(\''+getAttributeValueHtml(setting.default)+'\'); $(\'#button_'+setting.key+'\').hide();">Use System Setting</button>';
                                                }
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
					rowsHtml += '<tr style="vertical-align: middle; height: 50px;">' + getProjectSettingColumns(setting, system, prefix) + '</tr>';
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

				configureSettings(config['global-settings'], savedSettings);
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
                                var loc = window.location;
                                window.location = loc.protocol + '//' + loc.host + loc.pathname + loc.search;
			});
		});
	});
</script>
