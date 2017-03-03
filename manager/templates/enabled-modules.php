<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';

$sql = ExternalModules::getSqlToRunIfDBOutdated();
if($sql !== ""){
	echo '<p>Your current database table structure does not match REDCap\'s expected table structure for External Modules, which means that database tables and/or parts of tables are missing. Copy the SQL in the box below and execute it in the MySQL database named '.$db.' where the REDCap database tables are stored. Once the SQL has been executed, reload this page to run this check again.</p>';
	echo '<textarea style="width: 100%; height: 300px" onclick="this.focus();this.select()" readonly="readonly">' . $sql . '</textarea>';
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
				<td><div class='external-modules-title'><?= $config['name'] . ' - ' . $version ?></div><div class='external-modules-description'><?php echo $config['description'] ? $config['description'] : ''; ?></div></td>
				<td class="external-modules-action-buttons">
					<button class='external-modules-configure-button'>Configure</button>
					<?php if (!isset($pid)) { ?>
						<br><button class='external-modules-disable-button'>Disable</button>
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
			if (typeof value == "undefined") {
				value = "";
			}
			if (type == "file") {
				if (pid) {
					return getProjectFileFieldElement(name, value, inputAttributes);
				} else {
					return getGlobalFileFieldElement(name, value, inputAttributes);
				}
			} else {
				return '<input type="' + type + '" name="' + name + '" value="' + getAttributeValueHtml(value) + '" ' + inputAttributes + '>';
			}
		};
		
		var getTextareaElement = function(name, value, inputAttributes){
			if (typeof value == "undefined") {
				value = "";
			}

			return '<textarea name="' + name + '" ' + inputAttributes + '>'+getAttributeValueHtml(value)+'</textarea>';

		};

		var getSubSettingsElements = function(name, value, instance){
			if (typeof value == "undefined") {
				value = "";
			}

			var html = '';
			for(var i=0; i<value.length;i++){
				html += '<tr class = "subsettings-table">'+getSettingColumns(value[i], '')+'<td></td></tr>';
			}
			return html;

		};

		// abstracted because file fields need to be reset in multiple places
		var getGlobalFileFieldElement = function(name, value, inputAttributes) {
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

		var getSettingColumns = function(setting, inputAttributes, instance, header){
			var type = setting.type;
			var key = setting.key;
			var value = setting.value;

			var instanceLabel = "";
			if (typeof instance != "undefined") {
				instanceLabel = (instance+1)+". ";
			}
			var html = "<td></td>";
			if(type != 'sub_settings') {
				html = "<td><span class='external-modules-instance-label'>" + instanceLabel + "</span><label>" + setting.name + ":</label></td>";
			}

			if (typeof instance != "undefined") {
				// for looping for repeatable elements
                if( (header < 1 || typeof header == "undefined")){
                    value = value[instance];
                }
				if (instance > 0) {
					key = key + "____" + instance;
				}
			}

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
			else if(type == 'textarea'){
				inputAttributes += ' rows = "6" cols="45"';
				inputHtml = getTextareaElement(key, value, inputAttributes);
			}
			else if(type == 'sub_settings'){
				inputHtml = "<span class='external-modules-instance-label'>"+instanceLabel+"</span><label name='"+key+"'>" + setting.name + ":</label>";
			}
			else if(type == 'radio'){
				inputHtml = "";
				for(var i in setting.choices ){
					var choice = setting.choices[i];

					var checked = ''
					if(choice.value == value) {
						checked += ' checked';
					}

					inputHtml += getInputElement(type, key, choice.value, inputAttributes + checked) + '<label>' + choice.name + '</label><br>';
				}
			} else {
				if(type == 'checkbox' && value == 1){
					inputAttributes += ' checked';
				}
				// TODO Is this only triggered when a project is overriding the global value, but now allow-project-overrides is disabled?
				var alreadyOverridden = setting.value != setting.globalValue;
				if ((type == 'file') && (!setting['allow-project-overrides'] && alreadyOverridden)) {
					inputAttributes += "disabled";
				}

				inputHtml = getInputElement(type, key, value, inputAttributes);
			}

			html += "<td>" + inputHtml + "</td>";

			// no repeatable files allowed
			if (setting.repeatable && (type != "file")) {
				// fill with + and - buttons and hide when appropriate
				// set original sign for first item when + is not displayed
				var addButtonStyle = " style='display: none;'";
				var removeButtonStyle = " style='display: none;'";
				var originalTagStyle = " style='display: none;'";


                if ((typeof setting.value == "undefined") ||  (typeof instance == "undefined") || (instance + 1 >=  setting.value.length)) {
                    addButtonStyle = "";
                }

                if ((typeof instance != "undefined") && (instance > 0)) {
                    removeButtonStyle = "";
                }

                if ((addButtonStyle == "") && (removeButtonStyle == "") && (typeof instance != "undefined") && (instance === 0)) {
                    originalTagStyle = "";
                }

                //we are on the original element
                if(type == 'sub_settings' && (instance === 0) && header > 0){
                    originalTagStyle = "";
                    addButtonStyle = " style='display: none;'";
                    removeButtonStyle = " style='display: none;'";
                }


				var settingsClass = '';
				if(type == 'sub_settings'){
					settingsClass = "-subsettings";
				}
				html += "<td class='external-modules-add-remove-column'>";
				html += "<button class='external-modules-add-instance"+settingsClass+"'" + addButtonStyle + ">+</button>";
				html += "<button class='external-modules-remove-instance"+settingsClass+"'" + removeButtonStyle + ">-</button>";
				html += "<span class='external-modules-original-instance"+settingsClass+"'" + originalTagStyle + ">original</span>";
				html += "</td>";
			} else {
				html += "<td></td>";
			}

			//we add it after repeateable as it is a sub-setting and depends on it
			if(type == 'sub_settings' &&  (header < 1 || typeof header == "undefined")){
				html += getSubSettingsElements(key, setting.sub_settings, instance);
			}

			return html;
		};

		var getGlobalSettingColumns = function(setting){
			var columns = getSettingColumns(setting, '');

			if(setting['allow-project-overrides']){
				var overrideChoices = [
					{ value: '', name: 'Superusers Only' },
					{ value: '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS?>', name: 'Project Admins' },
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

			if (typeof s == "undefined") {
				s = "";
			}

			return s;
		}

		var getProjectSettingColumns = function(setting, global, instance, header){
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

			var columns = getSettingColumns(setting, inputAttributes, instance, header);

			if(global){
				columns += '<td class="external-modules-override-column"><input type="checkbox" class="override-global-setting" ' + overrideCheckboxAttributes + '></td>';
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
            var rowsHtml = '';

            configSettings.forEach(function(setting){
                var setting = $.extend({}, setting);
                var saved = savedSettings[setting.key];

                var indexSubSet = 0;
                if (setting.sub_settings) {
                    var i = 0;
                    setting.sub_settings.forEach(function(subSetting) {

                        if (savedSettings[subSetting.key]) {
                            setting.sub_settings[i].value = savedSettings[subSetting.key].value;
                            setting.sub_settings[i].globalValue =  savedSettings[subSetting.key].global_value;

							//we keep the length of the array to know the number of elements
							if(subSetting.value && Array.isArray(subSetting.value)){
								indexSubSet = subSetting.value.length;
							}
                            i++;
                        }
                    });
                } else if(saved) {
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
                    var rowTitleSubSetHtml = '';
                    if(setting.sub_settings) { //SUB_SETTINGS
                        if (setting.repeatable && (Object.prototype.toString.call(setting.value) === '[object Undefined]')) {
                            if(indexSubSet == 0){
                                rowsHtml += '<tr>' + getProjectSettingColumns(setting, global) + '</tr>';
                            }
                        }
                        for (var instance = 0; instance < indexSubSet; instance++) {
                            //we add the sub_settings header
                            if(indexSubSet == 0){ //if values empty NEW form
                                rowsHtml += '<tr>' + getProjectSettingColumns(setting, global) + '</tr>';
                            }else{
                                rowsHtml += '<tr>' + getProjectSettingColumns(setting, global, instance, indexSubSet) + '</tr>';
                            }

                            setting.sub_settings.forEach(function (subSetting) {
                                rowsHtml += '<tr class = "subsettings-table">' + getProjectSettingColumns(subSetting, global, instance) + '</tr>';
                            });
                        }
                    }else if (setting.repeatable && (Object.prototype.toString.call(setting.value) === '[object Array]')) {
                        for (var instance=0; instance < setting.value.length; instance++) {
                            rowsHtml += '<tr>' + getProjectSettingColumns(setting, global, instance) + '</tr>';
                        }
                    }else{
                        rowsHtml += '<tr>' + getProjectSettingColumns(setting, global) + '</tr>';
                    }
                }
            })

            return rowsHtml;
        };

		/**
		 * Function that given a position, returns the element name
		 * @param positionElement
		 * @returns {*}
		 */
		function getOldName(positionElement){
			var oldName = positionElement.find('input').attr('name');
			if (!oldName) {
				oldName = positionElement.find('select').attr('name');
			}
			if (!oldName) {
				oldName = positionElement.find('textarea').attr('name');
			}
			return oldName;
		}

		/**
		 * Function that given a name returns the name modified
		 * @param oldName
		 * @returns {string}
		 */
		function getNewName(oldName){
			var idx = 1;
			var newName = oldName + "____" + idx;   // default: guess that this is the second variable
			var ary;
			if (ary = oldName.match(/____(\d+)$/)) {
				// transfer number (old + 1)
				idx = Number(ary[1]) + 1;
				newName = oldName.replace("____" + ary[1], "____" + idx);
			}
			setIdx(idx);
			return newName;
		}

		/**
		 * Set/Get of the element index when creating the new name
		 * @type {number}
		 */
		var idx_g = 1;
		function setIdx(idx){
			idx_g = idx;
		}
		function getIdx(){
			return idx_g;
		}

		/**
		 * Function to add new elements
		 */
		$('#external-modules-configure-modal').on('click', '.external-modules-add-instance-subsettings, .external-modules-add-instance', function(){
			// see RULE on external-modules-add-instance
			// we must maintain said RULE here
			// RULE 2: Cannot remove first item

			var newInstanceTotal = "";
			var newclass = "";
			if($(this).hasClass('external-modules-add-instance-subsettings')) {
				$(this).closest('tr').nextAll('tr.subsettings-table').each(function () {


					var oldName = getOldName($(this).find('td:nth-child(2)'));
					var newName = getNewName(oldName);
					var idx = getIdx();

					//we copy the info
					var $newInstance = $(this).clone();

					// rename new instance of input/select and set value to empty string
					$newInstance.find('[name="' + oldName + '"]').attr('name', newName);
					$newInstance.find('[name="' + newName + '"]').val('');

					// rename label
					$newInstance.closest("tr").find('span.external-modules-instance-label').html((idx + 1) + ". ");
					$(this).closest("tr").find('span.external-modules-instance-label').html((idx) + ". ");

					newInstanceTotal += '<tr class = "subsettings-table">' + $newInstance.html() + '</tr>';
				});
				var oldName = $(this).closest('tr').find('label').attr('name');
				newclass = "-subsettings";
			}else if($(this).hasClass('external-modules-add-instance')) {
				var oldName = getOldName($(this).closest('tr'));
			}

			// show original sign if previous was first item
			if (!oldName.match(/____/)) {
				$("[name='"+oldName+"']").closest("tr").find(".external-modules-original-instance"+newclass).show();
			}

			//We show which one is the original
//			$(this).closest("tr").find(".external-modules-original-instance"+newclass).show();

			var newName = getNewName(oldName);
			var idx = getIdx();

			var $newInstanceTitle = $(this).closest('tr').clone();
			$newInstanceTitle.find(".external-modules-remove-instance"+newclass).show();
			$newInstanceTitle.find(".external-modules-original-instance"+newclass).hide();
			$newInstanceTitle.find('[name="'+oldName+'"]').attr('name', newName);
			$newInstanceTitle.find('[name="'+newName+'"]').val('');
			$newInstanceTitle.find('span.external-modules-instance-label').html((idx+1)+". ");

			//We add the whole new block at the end
			if($(this).hasClass('external-modules-add-instance-subsettings')) {
				$(this).closest('tr').nextAll('tr.subsettings-table').last().after("<tr>"+$newInstanceTitle.html()+"</tr>"+newInstanceTotal);
			}else if($(this).hasClass('external-modules-add-instance')) {
				$newInstanceTitle.insertAfter($(this).closest('tr'));
			}

			// rename new instance of input/select and set value to empty string
			$newInstanceTitle.find('[name="'+oldName+'"]').attr('name', newName);
			$newInstanceTitle.find('[name="'+newName+'"]').val('');

			// rename label
			$(this).closest("tr").find('span.external-modules-instance-label').html((idx)+". ");

			// show only last +
			$(this).hide();
		});

		/**
		 * Function that given a name returns removes the elements
		 * @param oldName
		 * @param newclass
		 * @returns {string}
		 */
		function removeElements(newclass,oldName){
			var oldNameParts = oldName.split(/____/);
			var baseName = oldNameParts[0];
			var i = 1;
			var j = 1;
			while ($("[name='" + baseName + "____" + i + "']").length) {
				if (i == oldNameParts[1]) {
					// remove tr
					$("[name='" + baseName + "____" + i + "']").closest('tr').remove();
				} else {
					// rename label
					$("[name='" + baseName + "____" + i + "']").closest("tr").find('span.external-modules-instance-label').html((j + 1) + ". ");
					// rename tr: i --> j
					$("[name='" + baseName + "____" + i + "']").attr('name', baseName + "____" + j);
					j++;
				}
				i++;
			}
			if (j > 1) {
				$("[name='" + baseName + "____" + (j - 1) + "']").closest("tr").find(".external-modules-add-instance"+newclass).show();
			} else {
				$("[name='" + baseName + "']").closest("tr").find(".external-modules-add-instance"+newclass).show();
				$("[name='" + baseName + "']").closest("tr").find(".external-modules-original-instance"+newclass).hide();
			}
			return j;
		}

		/**
		 * function to remove the elements
		 */
		$('#external-modules-configure-modal').on('click', '.external-modules-remove-instance-subsettings, .external-modules-remove-instance', function(){
			// see RULE on external-modules-add-instance
			// we must maintain said RULE here
			// RULE 2: Cannot remove first item

			var newInstanceTotal = "";
			var index = 0;
			var newclass = "";
			if($(this).hasClass('external-modules-remove-instance-subsettings')) {
				$(this).closest('tr').nextAll('tr.subsettings-table').each(function () {
					newclass = "-subsettings";
					var oldName = getOldName($(this).find('td:nth-child(2)'));
					index = removeElements(newclass,oldName);
				});

				//we remove the 'parent' element
				var oldNameParts = $(this).closest('tr').find('label').attr('name').split(/____/);
				var baseName = oldNameParts[0];
				if (index > 1) {
					$("[name='"+baseName+"____"+(index-1)+"']").closest("tr").find(".external-modules-add-instance-subsettings").show();
					$("[name='"+baseName+"____"+(index-1)+"']").closest("tr").find(".external-modules-original-instance-subsettings").hide();
				} else {
					$("[name='"+baseName+"']").closest("tr").find(".external-modules-add-instance-subsettings").show();
					$("[name='"+baseName+"']").closest("tr").find(".external-modules-original-instance-subsettings").hide();
				}

			}else if($(this).hasClass('external-modules-remove-instance')) {
				var oldName = getOldName($(this).closest('tr'));
				index = removeElements(newclass,oldName);
			}

			$(this).closest('tr').remove();

		});

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

				ExternalModules.configureSettings(config['global-settings'], savedSettings);
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
					inputs.closest("td").html(getGlobalFileFieldElement(inputs.attr('name'), globalValue, "disabled"));
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
				// AJAX rather than $.post
				$.ajax({
					url: url,
					data: formData,
					processData: false,
					contentType: false,
					async: false,
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

			configureModal.find('input, select, textarea').each(function(index, element){
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
