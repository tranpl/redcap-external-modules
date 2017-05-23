/**
 * Created by mcguffk on 12/28/2016.
 */

ExternalModules.configureSettings = function(configSettings, savedSettings) {
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

	$(function(){
		tinyMCE.init({
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
				tinyMCE.activeEditor.windowManager.open({
					url: 'rich-text/get-uploaded-file-list.php?prefix=' + prefix + '&pid=' + pid,
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
	})
}
