$(function () {
	// Make Control Center the active tab
	$('#sub-nav li.active').removeClass('active');
	$('#sub-nav a[href*="ControlCenter"]').closest('li').addClass('active');

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

