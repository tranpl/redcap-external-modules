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

		  var version = row.data('version');
		  var version_str = '';
		  if (version) {
			   version_str = "&version="+version;
		  }

		  var data = {};
		  data[keyEnabled] = false;
		  $.post('ajax/save-settings.php?pid=' + pid + '&moduleDirectoryPrefix=' + prefix + version_str, data, function(data){
			   if (data.status == 'success') {
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
