var ExternalModules = {
	sortModuleTable: function(table){
		table.find('tr').sort(function(a, b){
			a = $(a).find('.external-modules-title').text()
			b = $(b).find('.external-modules-title').text()

			return a.localeCompare(b)
		}).appendTo(table)
	}
};

ExternalModules.Settings = function(){}


ExternalModules.Settings.prototype.shouldShowSettingOnProjectManagementPage = function(setting, system) {
	if(!system){
		// Always show project level settings.
		return true;
	}
	if(setting.key == ExternalModules.KEY_ENABLED){
		// Hide the 'enabled' setting on projects, since we have buttons for enabling/disabling now.
		// Also, leaving this setting in place caused the enabled flag to be changed from a boolean to a string (which could cause unexpected behavior).
		return false;
	}
	if(setting.overrideLevelValue == null && !ExternalModules.SUPER_USER){
		// Hide this setting since the override level will prevent the non-superuser from actually saving it.
		return false;
	}
	// Checking whether a system setting is actually overridden is necessary for the UI to reflect when
	// settings are overridden prior to allow-project-overrides being set to false.
	var alreadyOverridden = setting.value != setting.systemValue;
	return setting['allow-project-overrides'] || alreadyOverridden;
}

ExternalModules.Settings.prototype.getAttributeValueHtml = function(s){
	if(typeof s == 'string'){
		s = s.replace(/"/g, '&quot;');
		s = s.replace(/'/g, '&apos;');
	}

	if (typeof s == "undefined") {
		s = "";
	}

	return s;
}

// Function to get the HTML for all the setting rows
ExternalModules.Settings.prototype.getSettingRows = function(system, configSettings, savedSettings,instance){
	var rowsHtml = '';
	var settingsObject = this;
	configSettings.forEach(function(setting){
		var setting = $.extend({}, setting);

		// Will need to clean up because can't use PHP constants in .js file
		setting.overrideLevelKey = setting.key + ExternalModules.OVERRIDE_PERMISSION_LEVEL_SUFFIX;
		var overrideLevel = savedSettings[setting.overrideLevelKey];

		if(overrideLevel){
			setting.overrideLevelValue = overrideLevel.value
		}

		rowsHtml += settingsObject.getSettingColumns(system,setting,savedSettings,instance);
	});

	return rowsHtml;
};

ExternalModules.Settings.prototype.getSettingColumns = function(system,setting,savedSettings,previousInstance) {
	var settingsObject = this;
	var rowsHtml = '';

	if(typeof previousInstance === 'undefined') {
		previousInstance = [];
	}

	var thisSavedSettings = savedSettings[setting.key];

	if(typeof thisSavedSettings === "undefined") {
		thisSavedSettings = [{}];
	}
	else {
		thisSavedSettings = thisSavedSettings.value;
		for(var i = 0; i < previousInstance.length; i++) {
			if(thisSavedSettings.hasOwnProperty(previousInstance[i])) {
				thisSavedSettings = thisSavedSettings[previousInstance[i]];
			}
			else {
				thisSavedSettings = [{}];
			}
		}
	}

	if(typeof thisSavedSettings === 'undefined') {
		thisSavedSettings = [{}];
	}

	if(!Array.isArray(thisSavedSettings)) {
		thisSavedSettings = [thisSavedSettings];
	}

	thisSavedSettings.forEach(function(settingValue,instance) {
		var subInstance  = previousInstance.slice();
		subInstance.push(instance);

		if(setting.type == "sub_settings") {
			rowsHtml += settingsObject.getColumnHtml(system, setting);
			setting.sub_settings.forEach(function(settingDetails){
				rowsHtml += settingsObject.getSettingRows(system,[settingDetails],savedSettings,subInstance);
			});
			rowsHtml += "<tr style='display:none' class='sub_end' field='" + setting.key + "'></tr>";
		}
		else {
			if(typeof settingValue !== "string") {
				settingValue = "";
			}
			rowsHtml += settingsObject.getColumnHtml(system, setting, settingValue);
		}
	});

	return rowsHtml;
};

// Function to use javascript to finish setting up configuration
ExternalModules.Settings.prototype.configureSettings = function(configSettings, savedSettings) {
	var settings = this;

	// For project IDs that have already been set, set up the value
	configSettings.forEach(function(setting){
		var setting = $.extend({}, setting);

		if(setting.type == 'project-id') {
			var saved = savedSettings[setting.key];
			if(saved){
				setting.value = saved.value;
				setting.systemValue = saved.system_value;
			}

			if(setting.value != '' && setting.value != null) {
				$('select[name="' + setting.key + '"]').removeClass('project_id_textbox');
				$.ajax({
					url: 'ajax/get-project-list.php',
					dataType: 'json'
				}).done(function(data) {
					var selectHtml = "";
					for(var key in data.results) {
						if(data.results[key]['id'] == setting.value) {
							selectHtml = "<option value='" + setting.value + "'>" + data.results[key]['text'] + "</option>";
						}
					}
					$('select[name="' + setting.key + '"]').html(selectHtml);

					$('select[name="' + setting.key + '"]').select2({
						width: '100%',
						data: data.results,
						ajax: {
							url: 'ajax/get-project-list.php',
							dataType: 'json',
							delay: 250,
							data: function(params) { return {'parameters':params.term }; },
							method: 'GET',
							cache: true
						}
					});
				});
			}
		}
	});

	// Set up other functions that need configuration
	settings.initializeRichTextFields();

	// Reset the instances so that things will be saved correctly
	settings.resetConfigInstances();
}


ExternalModules.Settings.prototype.getColumnHtml = function(system,setting,value){
	var type = setting.type;
	var key = setting.key;
	var trClass = "";

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
		if (typeof header == "undefined" && typeof value != "undefined" && value !== null) {
			value = value[instance];
		}
		key = this.getInstanceName(key, instance);
	}

	var inputHtml;
	if(type == 'dropdown'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'field-list'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'form-list'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'event-list'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'arm-list'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'user-list'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'user-role-list'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'dag-list'){
		inputHtml = this.getSelectElement(key, setting.choices, value, []);
	}
	else if(type == 'project-id'){
		inputHtml = "<div style='width:200px'>" + this.getSelectElement(key, setting.choices, value, {"class":"project_id_textbox"}) + "</div>";
	}
	else if(type == 'textarea'){
		inputHtml = this.getTextareaElement(key, value, {"rows" : "6"});
	}
	else if(type == 'rich-text') {
		inputHtml = this.getRichTextElement(key, value);
	}
	else if(type == 'sub_settings'){
		inputHtml = "<span class='external-modules-instance-label'>"+instanceLabel+"</span><label name='"+key+"'>" + setting.name + ":</label>";
		trClass += 'sub_start';
	}
	else if(type == 'radio'){
		inputHtml = "";
		for(var i in setting.choices ){
			var choice = setting.choices[i];

			var inputAttributes = [];
			if(choice.value == value) {
				inputAttributes["checked"] = "true";
			}

			inputHtml += this.getInputElement(type, key, choice.value, inputAttributes) + '<label>' + choice.name + '</label><br>';
		}
	}
	else if(type == 'custom') {
		var functionName = setting.functionName;

		inputHtml = this.getInputElement(type, key, value, inputAttributes);
		inputHtml += "<script type='text/javascript'>" + functionName + "($('input[name=\"" + key + "\"]'));</script>";
	} else {
		var inputAttributes = [];
		if(type == 'checkbox' && value == 1){
			inputAttributes['checked'] = 'checked';
		}

		inputHtml = this.getInputElement(type, key, value, inputAttributes);
	}

	html += "<td class='external-modules-input-td'>" + inputHtml + "</td>";

	if(setting.repeatable) {
		// Add repeatable buttons
		html += "<td class='external-modules-add-remove-column'>";
		html += "<button class='external-modules-add-instance' setting='" + setting.key + "'>+</button>";
		html += "<button class='external-modules-remove-instance' >-</button>";
		//html += "<span class='external-modules-original-instance'>original</span>";
		html += "</td>";

		trClass += ' repeatable';
	}
	else {
		html += "<td></td>";
	}

	if(!ExternalModules.PID) {
		if(setting['allow-project-overrides']){
			var overrideChoices = [
				{ value: '', name: 'Superusers Only' },
				// Will need to clean up because can't use PHP constants in .js file
				{ value: ExternalModules.OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS, name: 'Project Admins' },
			];

			var selectAttributes = '';
			// Will need to clean up because can't use PHP constants in .js file
			if(setting.key == ExternalModules.KEY_ENABLED){
				// For now, we've decided that only super users can enable modules on projects.
				// To enforce this, we disable this override dropdown for ExternalModules::KEY_ENABLED.
				selectAttributes = 'disabled'
			}

			html += '<td>' + this.getSelectElement(setting.overrideLevelKey, overrideChoices, setting.overrideLevelValue, selectAttributes) + '</td>';
		}
		else{
			html += '<td></td>';
		}
	}
	var outputHtml = "<tr" + (trClass === "" ? "" : " class='" + trClass + "'") + " field='" + setting.key + "'>" + html + "</tr>";

	return outputHtml;
};

ExternalModules.Settings.prototype.getSelectElement = function(name, choices, selectedValue, selectAttributes){
	if(!selectAttributes){
		selectAttributes = [];
	}

	var optionsHtml = '';
	optionsHtml += '<option value=""></option>';
	for(var i in choices ){
		var choice = choices[i];
		var value = choice.value;

		var optionAttributes = ''
		if(value == selectedValue){
			optionAttributes += 'selected'
		}

		optionsHtml += '<option value="' + this.getAttributeValueHtml(value) + '" ' + optionAttributes + '>' + choice.name + '</option>';
	}

	var defaultAttributes = {"class" : "external-modules-input-element"};
	var attributeString = this.getElementAttributes(defaultAttributes,selectAttributes);

	return '<select ' + attributeString + ' name="' + name + '" ' + selectAttributes + '>' + optionsHtml + '</select>';
}

ExternalModules.Settings.prototype.getInputElement = function(type, name, value, inputAttributes){
	if (typeof value == "undefined") {
		value = "";
	}
	if (type == "file") {
		if (ExternalModules.PID) {
			return this.getProjectFileFieldElement(name, value, inputAttributes);
		} else {
			return this.getSystemFileFieldElement(name, value, inputAttributes);
		}
	} else {
		return '<input type="' + type + '" name="' + name + '" value="' + this.getAttributeValueHtml(value) + '" ' + this.getElementAttributes({"class":"external-modules-input-element"},inputAttributes) + '>';
	}
}

// abstracted because file fields need to be reset in multiple places
ExternalModules.Settings.prototype.getSystemFileFieldElement = function(name, value, inputAttributes) {
	return this.getFileFieldElement(name, value, inputAttributes, "");
}

// abstracted because file fields need to be reset in multiple places
ExternalModules.Settings.prototype.getProjectFileFieldElement = function(name, value, inputAttributes) {
	return this.getFileFieldElement(name, value, inputAttributes, "pid=" + ExternalModules.PID);
}

// abstracted because file fields need to be reset in multiple places
ExternalModules.Settings.prototype.getFileFieldElement = function(name, value, inputAttributes, pid) {
	var attributeString = this.getElementAttributes([],inputAttributes);
	var type = "file";
	if ((typeof value != "undefined") && (value !== "")) {
		var html = '<input type="hidden" name="' + name + '" value="' + this.getAttributeValueHtml(value) + '" >';
		html += '<span class="external-modules-edoc-file"></span>';
		html += '<button class="external-modules-delete-file" '+attributeString+'>Delete File</button>';
		$.post('ajax/get-edoc-name.php?' + pid, { edoc : value }, function(data) {
			$("[name='"+name+"']").closest("tr").find(".external-modules-edoc-file").html("<b>" + data.doc_name + "</b><br>");
		});
		return html;
	} else {
		attributeString = this.getElementAttributes({"class":"external-modules-input-element"},inputAttributes);
		return '<input type="' + type + '" name="' + name + '" value="' + this.getAttributeValueHtml(value) + '" ' + attributeString + '>';
	}
}

ExternalModules.Settings.prototype.getTextareaElement = function(name, value, inputAttributes){
	if (typeof value == "undefined") {
		value = "";
	}

	return '<textarea contenteditable="true" name="' + name + '" ' + this.getElementAttributes([],inputAttributes) + '>'+this.getAttributeValueHtml(value)+'</textarea>';

}

ExternalModules.Settings.prototype.getRichTextElement = function(name, value) {
	if (!value) {
		value = '';
	}

	return '<textarea class="external-modules-rich-text-field" name="' + name + '">' + value + '</textarea>';
};

ExternalModules.Settings.prototype.getElementAttributes = function(defaultAttributes, additionalAttributes) {
	var attributeString = "";

	for (var tag in additionalAttributes) {
		if(defaultAttributes[tag]) {
			attributeString += tag + '="' + defaultAttributes[tag] + ' ' + additionalAttributes[tag] + '" ';
			delete defaultAttributes[tag];
		}
		else {
			attributeString += tag + '="' + additionalAttributes[tag] + '" ';
		}
	}

	for (var tag in defaultAttributes) {
		attributeString += tag + '="' + defaultAttributes[tag] + '" ';
	}

	return attributeString;
}

ExternalModules.Settings.prototype.getInstanceSymbol = function(){
	return "____";
}

ExternalModules.Settings.prototype.findSettings = function(config,name) {
	var configSettings = [config['project-settings'],config['system-settings']];
	var activeSetting = false;
	var systemSetting = false;

	configSettings.forEach(function(configType) {
		var matchedSetting = ExternalModules.Settings.prototype.parseSettings(configType, name);

		if(matchedSetting !== false) {
			activeSetting = matchedSetting;
		}

		// Second pass is system settings, so second loop will be true
		systemSetting = true;
	});

	activeSetting['isSystem'] = systemSetting;

	return activeSetting;
};

ExternalModules.Settings.prototype.parseSettings = function(configType, name) {
	var activeSetting = false;
	configType.forEach(function(setting) {
		if(setting.key == name) {
			activeSetting = setting;
		}
		else if(setting.type == 'sub_settings') {
			var matchedSetting = ExternalModules.Settings.prototype.parseSettings(setting.sub_settings,name);

			if(matchedSetting !== false) {
				activeSetting = matchedSetting;
			}
		}
	});

	return activeSetting;
};

ExternalModules.Settings.prototype.getEndOfSub = function(startTr) {
	var currentTr = startTr;
	var reachedEnd = false;
	var currentDepth = 1;

	// Loop through subsequent <tr> elements until finding its end element
	while(!reachedEnd) {
		currentTr = currentTr.next();

		// If reaching end of a sub-setting, decrement depth and check if reached
		// the end of the original element
		if(currentTr.hasClass("sub_end")) {
			currentDepth--;
			reachedEnd = currentDepth < 1;
		}

		// If nested sub-setting, increment the depth counter
		if(currentTr.hasClass("sub_start")) {
			currentDepth++;
		}
	}

	return currentTr;
};

ExternalModules.Settings.prototype.resetConfigInstances = function() {
	var currentInstance = [];
	var currentFields = [];
	var lastWasEndNode = false;

	// Loop through each config row to find it's place in the loop
	$("#external-modules-configure-modal tr").each(function() {
		var lastField = currentFields.slice(-1);
		lastField = (lastField.length > 0 ? lastField[0] : false);

		// End current count if next node is different field
		if(lastWasEndNode) {
			if($(this).attr("field") != lastField) {
				// If there's only one instance of the previous field, hide "-" button
				if(currentInstance[currentInstance.length - 1] == 1) {
					var previousLoopField = currentFields[currentFields.length - 1];
					var currentTr = $(this).prev();

					// If merely a single repeating field
					if(!currentTr.hasClass("sub_end")) {
						currentTr.find(".external-modules-remove-instance").hide();
					}
					else {
						// Loop backwards until finding a start element matching the previousLoopField
						while((typeof currentTr !== "undefined") && !(currentTr.hasClass("sub_start") && (currentTr.attr("field") == previousLoopField))) {
							currentTr = currentTr.prev();
						}
						currentTr.find(".external-modules-remove-instance").hide();
					}
				}
				currentInstance.pop();
				currentFields.pop();
			}
		}

		// Increment or start count on current loop
		if($(this).hasClass("sub_start") || $(this).hasClass("repeatable")) {
			if(lastField == $(this).attr("field")) {
				currentInstance[currentInstance.length - 1]++;
			}
			else {
				currentInstance.push(1);
				currentFields.push($(this).attr("field"));
			}
		}

		lastWasEndNode = ($(this).hasClass("repeatable") && !$(this).hasClass("sub_start")) || $(this).hasClass("sub_end");

		// Update the number scheme on label and input names
		var currentLabel = "";
		var currentName = "";
		for(var i = 0; i < currentInstance.length; i++) {
			currentLabel += currentInstance[i] + ".";
			currentName += ExternalModules.Settings.prototype.getInstanceSymbol() + currentInstance[i];
		}

		$(this).find(".external-modules-instance-label").html(currentLabel + " ");
		$(this).find("select, input").attr("name",$(this).attr("field") + currentName);
	});
};

ExternalModules.Settings.prototype.initializeRichTextFields = function(){

	$(".project_id_textbox").select2({
		width: '100%',
		ajax: {
			url: 'ajax/get-project-list.php',
			dataType: 'json',
			delay: 250,
			data: function(params) { return {'parameters':params.term }; },
			method: 'GET',
			cache: true
		}
	});

	$('.external-modules-rich-text-field').each(function(index, textarea){
		textarea = $(textarea)
		var expectedId = 'external-modules-rich-text-field_' + textarea.attr('name')
		if(expectedId != textarea.attr('id')){
			// This textarea must have just been added by clicking the repeatable plus button.
			// We need to fix it's id.
			textarea.attr('id', expectedId)

			// Remove the cloned TinyMCE elements (always the previous sibling), so they can be reinitialized.
			textarea.prev().remove()

			// Show the textarea (so TinyMCE will reinitialize it).
			textarea.show()
		}
	})

	tinymce.init({
		mode: 'specific_textareas',
		editor_selector: 'external-modules-rich-text-field',
		height: 200,
		menubar: false,
		branding: false,
		elementpath: false, // Hide this, since it oddly renders below the textarea.
		plugins: ['autolink lists link image charmap hr anchor pagebreak searchreplace code fullscreen insertdatetime media nonbreaking table contextmenu directionality textcolor colorpicker imagetools'],
		toolbar1: 'undo redo | insert | styleselect | bold italic | alignleft aligncenter alignright alignjustify',
		toolbar2: 'outdent indent | bullist numlist | table | forecolor backcolor | searchreplace fullscreen code',
		relative_urls : false, // force image urls to be absolute
		file_picker_callback: function(callback, value, meta){
			var prefix = $('#external-modules-configure-modal').data('module')
			tinymce.activeEditor.windowManager.open({
				url: ExternalModules.BASE_URL + '/manager/rich-text/get-uploaded-file-list.php?prefix=' + prefix + '&pid=' + pid,
				width: 500,
				height: 300,
				title: 'Files'
			});

			ExternalModules.currentFilePickerCallback = function(url){
				tinymce.activeEditor.windowManager.close()
				callback(url)
			}
		}
	});
}

$(function(){
	var settings = new ExternalModules.Settings();

	var onValueChange = function() {
		var val;
		if (this.type == "checkbox") {
			val = $(this).is(":checked");
		} else {
			val = $(this).val();
		}
		var overrideButton = $(this).closest('tr').find('button.external-modules-use-system-setting');
		if (overrideButton) {
			var systemValue = overrideButton.data('system-value');
			if (typeof systemValue != "undefined") {
				if (systemValue == val) {
					overrideButton.hide();
				} else {
					overrideButton.show();
				}
			}
		}
	};

	$('#external-modules-configure-modal').on('change', '.external-modules-input-element', onValueChange);
	$('#external-modules-configure-modal').on('check', '.external-modules-input-element', onValueChange);

	/**
	 * Function to add new elements
	 */
	$('#external-modules-configure-modal').on('click', '.external-modules-add-instance-subsettings, .external-modules-add-instance', function(){
		// Get the full configuration for the active module from the global variable
		var config = ExternalModules.configsByPrefix[configureModal.data('module')];

		// Find the setting currently being added to and its configuration
		var name = $(this).attr('setting');
		var setting = ExternalModules.Settings.prototype.findSettings(config,name);
		//console.log(config);
		//console.log(name);
		//console.log(setting);
		if(typeof setting !== "undefined") {
			// Create new html for this setting
			var html = ExternalModules.Settings.prototype.getSettingRows(setting.isSystem,[setting],[{}]);

			var thisTr = $(this).closest("tr");

			if(thisTr.hasClass("sub_start")) {
				thisTr = ExternalModules.Settings.prototype.getEndOfSub(thisTr);
			}
			thisTr.after(html);
		}

		settings.initializeRichTextFields();

		settings.resetConfigInstances();
	});

	/**
	 * function to remove the elements
	 */
	$('#external-modules-configure-modal').on('click', '.external-modules-remove-instance-subsettings, .external-modules-remove-instance', function(){
		var startTr = $(this).closest('tr');

		// If this element is a sub_setting element, loop through until reaching the end
		// of this setting's rows
		if(startTr.hasClass("sub_start")) {
			var lastTr = ExternalModules.Settings.prototype.getEndOfSub(startTr);

			// Remove all the elements between start and end. Then remove last element.
			startTr.nextUntil(lastTr).remove();
			lastTr.remove();
		}

		// Clean up by removing the original element
		startTr.remove();

		tinymce.editors.forEach(function(editor, index){
			if(!document.contains(editor.getElement())){
				// The element for this editor was removed from the DOM.  Destroy the editor.
				editor.remove()
			}
		})

		settings.resetConfigInstances();
	});

	// Merged from updated enabled-modules, may need to reconfigure
	ExternalModules.configsByPrefix = ExternalModules.configsByPrefixJSON;
	ExternalModules.versionsByPrefix = ExternalModules.versionsByPrefixJSON;

	var pid = ExternalModules.PID;
	var pidString = pid;
	if(pid === null){
		pidString = '';
	}
	var configureModal = $('#external-modules-configure-modal');
	// may need to reconfigure
	var isSuperUser = (ExternalModules.SUPER_USER == 1);

	// Shared function for combining 2 arrays to produce an attribute string for an HTML object
	$('#external-modules-enabled').on('click', '.external-modules-configure-button', function(){
		// find the module directory prefix from the <tr>
		var moduleDirectoryPrefix = $(this).closest('tr').data('module');
		configureModal.data('module', moduleDirectoryPrefix);

		var config = ExternalModules.configsByPrefix[moduleDirectoryPrefix];
		configureModal.find('.module-name').html(config.name);
		var tbody = configureModal.find('tbody');
		tbody.html('');
		configureModal.modal('show');

		// Param list to pass to get-settings.php
		var params = {moduleDirectoryPrefix: moduleDirectoryPrefix};
		if (pid) {
			params['pid'] = pidString;
		}

		// Get the existing values for this module through ajax
		$.post('ajax/get-settings.php', params, function(data){
			if(data.status != 'success'){
				return;
			}

			var savedSettings = data.settings;

			// Get the html for the configuration
			var settingsHtml = "";
			settingsHtml += settings.getSettingRows(true, config['system-settings'], savedSettings);

			if(pid) {
				settingsHtml += settings.getSettingRows(false, config['project-settings'], savedSettings);
			}

			// Add blank tr to end of table to make resetConfigInstances work better
			settingsHtml += "<tr style='display:none'></tr>";

			tbody.html(settingsHtml);

			// Post HTML scripting
			if(pid) {
				settings.configureSettings(config['project-settings'], savedSettings);
			}
			else {
				settings.configureSettings(config['system-settings'], savedSettings);
			}
		});
	});


	var deleteFile = function(ob) {
		var moduleDirectoryPrefix = configureModal.data('module');

		var row = ob.closest("tr");
		var input = row.find("input[type=hidden]");
		var disabled = input.prop("disabled");
		var deleteFileButton = row.find("button.external-modules-delete-file");
		if (deleteFileButton) {
			deleteFileButton.hide();
		}

		$.post("ajax/delete-file.php?pid="+pidString, { moduleDirectoryPrefix: moduleDirectoryPrefix, key: input.attr('name'), edoc: input.val() }, function(data) {
			if (data.status == "success") {
				var inputAttributes = "";
				if (disabled) {
					inputAttributes = "disabled";
				}
				row.find(".external-modules-edoc-file").html(settings.getProjectFileFieldElement(input.attr('name'), "", inputAttributes));
				input.remove();
			} else {		// failure
				alert("The file was not able to be deleted. "+JSON.stringify(data));
			}

			var overrideButton = row.find("button.external-modules-use-system-setting");
			var systemValue = overrideButton.data("system-value");

			if (systemValue != "") {	// compare to new value
				overrideButton.show();
			} else {
				overrideButton.hide();
			}
		});
	};
	configureModal.on('click', '.external-modules-delete-file', function() {
		deleteFile($(this));
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

	configureModal.on('click', '.external-modules-use-system-setting', function(){
		var overrideButton = $(this);
		var systemValue = overrideButton.data('system-value');
		var row = overrideButton.closest('tr');
		var inputs = row.find('td:nth-child(2)').find('input, select');

		var type = inputs[0].type;
		if(type == 'radio'){
			inputs.filter('[value=' + systemValue + ']').click();
		}
		else if(type == 'checkbox'){
			inputs.prop('checked', systemValue);
		}
		else if((type == 'hidden') && (inputs.closest("tr").find(".external-modules-edoc-file").length > 0)) {   // file
			deleteFile($(this));
			resetSaveButton("");
		}
		else if(type == 'file') {
			// if a real value
			if (!isNaN(systemValue)) {
				var edocLine = row.find(".external-modules-input-td");
				if (edocLine) {
					var inputAttributes = "";
					if (inputs.prop("disabled")) {
						inputAttributes = "disabled";
					}
					edocLine.html(settings.getSystemFileFieldElement(inputs.attr('name'), systemValue, inputAttributes));
					resetSaveButton(systemValue);
					row.find(".external-modules-delete-file").show();
				}
			}
		}
		else{ // text or select
			inputs.val(systemValue);
		}
		overrideButton.hide();
	});

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
						alert(returnData.status+" One or more of the files could not be saved."+JSON.stringify(returnData));
					}

					// proceed anyways to save data
					callbackWithNoArgs();
				},
				error: function(e) {
					alert("One or more of the files could not be saved."+JSON.stringify(e));
					callbackWithNoArgs();
				}
			});
		} else {
			callbackWithNoArgs();
		}
	}

	// helper method for saving
	var saveSettings = function(pidString, moduleDirectoryPrefix, version, data) {
	   $.post('ajax/save-settings.php?pid=' + pidString + '&moduleDirectoryPrefix=' + moduleDirectoryPrefix, data).done( function(returnData){
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
		var version = ExternalModules.versionsByPrefix[moduleDirectoryPrefix];

		var data = {};
		var files = {};
		
		configureModal.find('input, select, textarea').each(function(index, element){
			var element = $(element);
			var systemValue = element.closest('tr').find('.override-system-setting').data('system-value');
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
				else if(element.hasClass('external-modules-rich-text-field')){
					var id = element.attr('id');
					value = tinymce.get(id).getContent();
				}
				else{
					value = element.val();
				}

				if(value == systemValue){
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

	configureModal.on('hidden.bs.modal', function () {
		tinymce.remove()
	})

	$('.external-modules-usage-button').click(function(){
		var row = $(this).closest('tr');
		var prefix = row.data('module')
		$.get('ajax/usage.php', {prefix: prefix}, function(data){
			if(data == ''){
				data = 'None'
			}

			var modal = $('#external-modules-usage-modal')
			modal.find('.modal-title').html('Project Usage:<br><b>' + row.find('.external-modules-title').text() + '</b>')
			modal.find('.modal-body').html(data)
			modal.modal('show')
		})
	})
});
