$(function () {
	var disabledModal = $('#external-modules-disabled-modal');
	$('#external-modules-enable-modules-button').click(function(){
		var form = disabledModal.find('.modal-body form');
		var loadingIndicator = $('<div class="loading-indicator"></div>');
		if (!pid) {
                	new Spinner().spin(loadingIndicator[0]);
		}
		form.html('');
		form.append(loadingIndicator);

		// This ajax call was originally written thinking the list of available modules would come from a central repo.
		// It may not be necessary any more.
		var url = "ajax/get-disabled-modules.php";
		if (pid) {
			url += "?pid="+pid;
		}
		$.post(url, { }, function (html) {
			form.html(html);
		});

	disabledModal.modal('show');
	});
});
