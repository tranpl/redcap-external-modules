$(function() {

	 var reloadPage = function(){
		  $('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		  var loc = window.location;
		  window.location = loc.protocol + '//' + loc.host + loc.pathname + loc.search;
	 }

	 $('.external-modules-disable-button').click(function (event) {
		  var button = $(event.target);
		  button.attr('disabled', true);

		  var row = $(event.target).closest('tr');
		  var prefix = row.data('module');

		  $.post('ajax/disable-module.php?pid=' + pid, { module: prefix }, function(data){
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
