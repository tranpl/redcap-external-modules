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

<h3>Enabled Modules</h3>

<table id='external-modules-enabled' class="table">
	<?php

	$versionsByPrefix = ExternalModules::getEnabledModules();
	$configsByPrefix = array();

	if (empty($versionsByPrefix)) {
		echo 'None';
	} else {
		foreach ($versionsByPrefix as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version,$pid);
			$configsByPrefix[$prefix] = $config;
			?>
			<tr data-module='<?= $prefix ?>'>
				<td><?= $config['name'] . ' - ' . $version ?></td>
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
$versionsByPrefixJSON = json_encode($versionsByPrefix, JSON_PARTIAL_OUTPUT_ON_ERROR);
if($versionsByPrefixJSON == null){
	echo '<script>alert(' . json_encode('An error occurred while converting the versions to JSON: ' . json_last_error_msg()) . ');</script>';
	die();
}
?>

<script>
	$(function(){
		var pid = <?=json_encode($pid)?>;
		var configsByPrefix = <?=$configsByPrefixJSON?>;
		var versionsByPrefix = <?=$versionsByPrefixJSON?>;
		var pidString = pid;
		if(pid == null){
			pidString = '';
		}
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
			if (type == "file") {
				if (pid) {
					return getProjectFileFieldElement(name, value, inputAttributes);
				} else {
					return setGlobalFileFieldElement(name, value, inputAttributes);
				}
			} else {
				return '<input type="' + type + '" name="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
			}
		};

		// abstracted because file fields need to be reset in multiple places
		var setGlobalFileFieldElement = function(name, value, inputAttributes) {
			return getFileFieldElement(name, value, inputAttributes, "");
		}

		// abstracted because file fields need to be reset in multiple places
		var getProjectFileFieldElement = function(name, value, inputAttributes) {
			return getFileFieldElement(name, value, inputAttributes, "pid=" + pidString);
		}

		// abstracted because file fields need to be reset in multiple places
		var getFileFieldElement = function(name, value, inputAttributes, pidString) {
			var type = "file";
			if ((typeof value != "undefined") && (value !== "")) {
				var html = '<input type="hidden" name="' + name + '" value="' + getAttributeValueHtml(value) + '" >';
                                html += '<span class="external-modules-edoc-file"></span>';
                                html += '<button class="external-modules-delete-file" '+inputAttributes+'>Delete File</button>';
                                $.post('ajax/get-edoc-name.php?' + pidString, { edoc : value }, function(data) {
                                        $("[name='"+name+"']").closest("tr").find(".external-modules-edoc-file").html("<b>" + data.doc_name + "</b><br>");
                                });
                                return html;
			} else {
				return '<input type="' + type + '" name="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
			}
		}

		var getSettingColumns = function(setting, inputAttributes, global){
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
			} else {
				if(type == 'checkbox' && value == 1){
					inputAttributes += ' checked';
				}
				var alreadyOverridden = setting.value != setting.globalValue;
				if ((type == 'file') && (!setting['allow-project-overrides'] && global && alreadyOverridden)) {
					inputAttributes += "disabled";
				}

				inputHtml = getInputElement(type, key, value, inputAttributes);
			}

			html += "<td>" + inputHtml + "</td>";

			return html;
		};

		var getGlobalSettingColumns = function(setting){
			var columns = getSettingColumns(setting, '', true);

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
			if(typeof s == 'string'){
				s = s.replace(/"/g, '&quot;');
				s = s.replace(/'/g, '&apos;');
			}

			if (typeof s == "undefined") {
				s = "";
			}

			return s;
		}

		var getProjectSettingColumns = function(setting, global){
			var setting = $.extend({}, setting);
			var projectName = setting['project-name'];
			if(projectName){
				 setting.name = projectName;
			}

			var inputAttributes = '';
			var overrideCheckboxAttributes = 'data-global-value="' + getAttributeValueHtml(setting.globalValue) + '"';

			if(global && setting.value == setting.globalValue){
				inputAttributes += ' disabled';
			}
			else{
				overrideCheckboxAttributes += ' checked';
			}

			var columns = getSettingColumns(setting, inputAttributes, global);

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

			configSettings.forEach(function(setting){
				var setting = $.extend({}, setting);
				var saved = savedSettings[setting.key];
				if(saved){
					setting.value = saved.value;
					setting.globalValue = saved.global_value;
				}

				setting.overrideLevelKey = setting.key + '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX?>';
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
			})

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

			$.post('ajax/get-settings.php', {pid: pidString, moduleDirectoryPrefix: moduleDirectoryPrefix}, function(data){
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

				configureSettings(config['global-settings'], savedSettings);
			});
		});

		configureModal.on('click', '.external-modules-delete-file', function() {
			var moduleDirectoryPrefix = configureModal.data('module');

			var row = $(this).closest("tr");
			var input = row.find("input[type=hidden]");
			var disabled = input.prop("disabled");
			$(this).hide();

			$.post("ajax/delete-file.php?pid="+pidString, { moduleDirectoryPrefix: moduleDirectoryPrefix, key: input.attr('name'), edoc: input.val() }, function(data) { 
				if (data.status == "success") {
					var inputAttributes = "";
					if (disabled) {
						inputAttributes = "disabled";
					}
					row.find(".external-modules-edoc-file").html(getProjectFileFieldElement(input.attr('name'), "", inputAttributes));
					input.remove();
				} else {		// failure
					alert("The file was not able to be deleted. "+JSON.stringify(data));
				}
			});
		});

		configureModal.on('click', '.override-global-setting', function(){
			var overrideCheckbox = $(this);
			var globalValue = overrideCheckbox.data('global-value');
			var inputs = overrideCheckbox.closest('tr').find('td:nth-child(2)').find('input, select');

			if(overrideCheckbox.prop('checked')){
				inputs.prop('disabled', false);
				inputs.closest("tr").find(".external-modules-delete-file").prop("disabled", false);
				resetSaveButton();
			}
			else{
				var type = inputs[0].type;
				if(type == 'radio'){
					inputs.filter('[value=' + globalValue + ']').click();
				}
				else if(type == 'checkbox'){
					inputs.prop('checked', globalValue);
				}
				else if((type == 'hidden') && (inputs.closest("tr").find(".external-modules-edoc-file").length > 0)) {   // file
					inputs.closest("td").html(setGlobalFileFieldElement(inputs.attr('name'), globalValue, "disabled"));
					resetSaveButton();
 				}
				else{ // text or select
					inputs.val(globalValue);
				}

				inputs.prop('disabled', true);
			}
		});

		var resetSaveButton = function() {
			if ($(this).val() != "") {
				$(".save").html("Save and Upload");
			}
			var allEmpty = true;
			$("input[type=file]").each(function() {
				if ($(this).val() !== "") {
					allEmpty = false;
				}
			});
			if (allEmpty) {
				$(".save").html("Save");
			}
		}

		configureModal.on('change', 'input[type=file]', resetSaveButton);

		// helper method for saving
		var saveFilesIfTheyExist = function(url, files, callbackWithNoArgs) {
			var lengthOfFiles = 0;
			var formData = new FormData();
			for (var name in files) {
				lengthOfFiles++;
				formData.append(name, files[name]);   // filename agnostic
			}
			if (lengthOfFiles > 0) {
				$.ajax({
					url: url,
					data: formData,
					processData: false,
					contentType: false,
					type: 'POST',
					success: function(returnData) {
						if (returnData.status != 'success') {
							alert("One or more of the files could not be saved. "+JSON.stringify(data));
						}

						// proceed anyways to save data
						callbackWithNoArgs();
					},
					error: function(e) {
						alert("One or more of the files could not be saved. "+JSON.stringify(e));
						callbackWithNoArgs();
					}
				});
			} else {
				callbackWithNoArgs();
			}
		}

		// helper method for saving
		var saveSettings = function(pidString, moduleDirectoryPrefix, version, data) {
			$.post('ajax/save-settings.php?pid=' + pidString + '&moduleDirectoryPrefix=' + moduleDirectoryPrefix + "&moduleDirectoryVersion=" + version, data, function(returnData){
				if(returnData.status != 'success'){
					alert('An error occurred while saving settings: ' + returnData);
					configureModal.show();
					return;
				}

				// Reload the page reload after saving settings,
				// in case a settings affects some page behavior (like which menu items are visible).
				location.reload();
			});
		}

		configureModal.on('click', 'button.save', function(){
			configureModal.hide();
			var moduleDirectoryPrefix = configureModal.data('module');
			var version = versionsByPrefix[moduleDirectoryPrefix];

			var data = {};
                        var files = {};

			configureModal.find('input, select').each(function(index, element){
				var element = $(element);
				var globalValue = element.closest('tr').find('.override-global-setting').data('global-value');
				var name = element.attr('name');
				var type = element[0].type;

				if(!name || (type == 'radio' && !element.is(':checked'))){
					return;
				}

				if (type == 'file') {
					// only store one file per variable - the first file
					jQuery.each(element[0].files, function(i, file) {
						if (typeof files[name] == "undefined") {
							files[name] = file;
						}
					});
				} else {
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
				}
			});

			var url = 'ajax/save-file.php?pid=' + pidString +
						     '&moduleDirectoryPrefix=' + moduleDirectoryPrefix +
						     '&moduleDirectoryVersion=' + version;
                        saveFilesIfTheyExist(url, files, function() {
				saveSettings(pidString, moduleDirectoryPrefix, version, data);
			});
		});
	});
</script>
