$(function () {
	// Make Control Center the active tab
	$('#sub-nav li.active').removeClass('active');
	$('#sub-nav a[href*="ControlCenter"]').closest('li').addClass('active');

	var disabledModal = $('#external-modules-disabled-modal');
	$('#external-modules-enable-modules-button').click(function(){
		var form = disabledModal.find('.modal-body form');
		var loadingIndicator = $('<div class="loading-indicator"></div>');
		new Spinner().spin(loadingIndicator[0]);
		form.html('');
		form.append(loadingIndicator);

		// This ajax call was originally written thinking the list of available modules would come from a central repo.
		// It may not be necessary any more.
		$.post('ajax/get-disabled-modules.php', null, function (html) {
			form.html(html);
		});

		disabledModal.modal('show');
	});

	var configureModal = $('#external-modules-configure-modal');
	configureModal.on('show.bs.modal', function () {
		var button = $(event.target);
		var moduleName = $(button.closest('tr').find('td')[0]).html();
		configureModal.find('.module-name').html(moduleName);
	});

	$('.external-modules-disable-button').click(function (event) {
		var button = $(event.target);
		button.attr('disabled', true);

		var row = button.closest('tr');
		var module = row.data('module');
		$.post('ajax/disable-module.php', {module: module}, function (data) {
			if (data == 'success') {
				    button.attr('disabled', false);
				var table = row.closest('table');
				row.remove();
    
				if(table.find('tr').length == 0){
					table.html('None');
				}
			}
			else {
				alert('An error occurred while disabling the ' + module + ' module: ' + data);
			}
		});
	});
});

