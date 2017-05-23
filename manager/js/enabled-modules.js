$(function(){
		// Merged from updated enabled-modules, may need to reconfigure
		ExternalModules.configsByPrefix = ExternalModules.configsByPrefixJSON;
		ExternalModules.versionsByPrefix = ExternalModules.versionsByPrefixJSON;

		var pid = ExternalModules.PID;
		var pidString = pid;
		//if(pid == null){
		//	pidString = '';
		//}
	var configureModal = $('#external-modules-configure-modal');
		// may need to reconfigure
		var isSuperUser = (ExternalModules.SUPER_USER == 1);

	var settings = new ExternalModules.Settings();

	// Shared function for combining 2 arrays to produce an attribute string for an HTML object


	$('#external-modules-enabled').on('click', '.external-modules-configure-button', function(){
		var moduleDirectoryPrefix = $(this).closest('tr').data('module');
		configureModal.data('module', moduleDirectoryPrefix);

		var config = ExternalModules.configsByPrefix[moduleDirectoryPrefix];
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
			settingsHtml += settings.getSettingRows(true, config['system-settings'], savedSettings);

			if(pid) {
				settingsHtml += settings.getSettingRows(false, config['project-settings'], savedSettings);
			}

			tbody.html(settingsHtml);

			if(pid) {
				ExternalModules.configureSettings(config['project-settings'], savedSettings);
			}
			else {
				ExternalModules.configureSettings(config['system-settings'], savedSettings);
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

			if (systemValue != "") {    // compare to new value
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
		var version = ExternalModules.versionsByPrefix[moduleDirectoryPrefix];

		var data = {};
		var files = {};

		var richTextIndex = 0;
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
					value = tinyMCE.get(richTextIndex).getContent()
					richTextIndex++
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
});
