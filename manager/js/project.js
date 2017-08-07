$(function() {

	 var reloadPage = function(){
		  $('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		  var loc = window.location;
		  window.location = loc.protocol + '//' + loc.host + loc.pathname + loc.search;
	 }

	$('.external-modules-disable-button').click(function (event) {	
		var button = $(event.target);
		var row = button.closest('tr');
		var module = row.data('module');
		var version = row.data('version');
		$('#external-modules-disable-confirm-modal').modal('show');
		$('#external-modules-disable-confirm-module-name').html(module);
		$('#external-modules-disable-confirm-module-version').html(version);
	});
		
	$('#external-modules-disable-button-confirmed').click(function (event) {
		var button = $(event.target);
		button.attr('disabled', true);
		var module = $('#external-modules-disable-confirm-module-name').text();
		$.post('ajax/disable-module.php?pid=' + ExternalModules.PID, { module: module }, function(data){
		   if (data == 'success') {
				reloadPage();
		   }
		   else {
				var message = 'An error occurred while enabling the module: ' + data;
				console.log('AJAX Request Error:', message);
				alert(message);
		   }
		});
	});
});
