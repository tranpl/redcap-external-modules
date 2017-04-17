$(function(){
	// first show disabledModal and then show enableModal
	var disabledModal = $('#external-modules-disabled-modal');
	var enableModal = $('#external-modules-enable-modal');

	var reloadThisPage = function(){
		$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		var loc = window.location;
		window.location = loc.protocol + '//' + loc.host + loc.pathname + loc.search;
	}

	disabledModal.find('.enable-button').click(function(event){
		disabledModal.hide();
		$('#external-modules-enable-modal-error').hide();

		var row = $(event.target).closest('tr');
		var prefix = row.data('module');
		var version = row.find('[name="version"]').val();

		if (!pid) {
			var enableButton = enableModal.find('.enable-button');
			enableButton.html('Enable');
			enableModal.find('button').attr('disabled', false);

			var list = enableModal.find('.modal-body ul');
			list.html('');

			disabledModules[prefix][version].permissions.forEach(function(permission){
				list.append("<li>" + permission + "</li>");
			});

			enableButton.off('click'); // disable any events attached from other modules
			enableButton.click(function(){
				enableButton.html('Enabling...');
				enableModal.find('button').attr('disabled', true);

				$.post('ajax/enable-module.php', {prefix: prefix, version: version}, function (data) {
					jsonAjax = jQuery.parseJSON(data);
					console.log("Message: "+jsonAjax['error_message']);
					if (jsonAjax['error_message'] != "") {
						$('#external-modules-enable-modal-error').show();
						$('#external-modules-enable-modal-error').html(jsonAjax['error_message']);
						$('.close-button').attr('disabled', false);
						enableButton.hide();
					}else if (jsonAjax['message'] == 'success') {
						reloadThisPage();
						disabledModal.modal('hide');
						enableModal.modal('hide');
					}else{
						var message = 'An error occurred while enabling the module: ' + jsonAjax;
						console.log('AJAX Request Error:', message);
						enableModal.modal('hide');
					}
				});
			});
			enableButton.show();
			enableModal.modal('show');
			return false;
		} else {   // pid
			$.post('ajax/enable-module.php?pid=' + pid, {prefix: prefix, version: version}, function(data){
				jsonAjax = jQuery.parseJSON(data);
				$('#external-modules-enable-modal-error').hide();
				if (jsonAjax['error_message'] != "") {
					$('#external-modules-enable-modal-error').show();
					$('#external-modules-enable-modal-error').html(jsonAjax['error_message']);
					$('.close-button').attr('disabled', false);
					enableButton.hide();
				}else if (jsonAjax['message'] == 'success') {
					reloadThisPage();
					disabledModal.modal('hide');
					enableModal.modal('hide');
				}else{
					var message = 'An error occurred while enabling the module: ' + jsonAjax;
					console.log('AJAX Request Error:', message);
					alert(message);
					enableModal.modal('hide');
				}
			});
			return false;
		}
	});

	if (enableModal) {
		enableModal.on('hide.bs.modal', function(){
			if($('#external-modules-disabled-table tr').length === 0){
				// Reload since there aren't any more disabled modules to enable.
				reloadThisPage();
			}
			else{
				disabledModal.show();
			}
		});
	}
});
