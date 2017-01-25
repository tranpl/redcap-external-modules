		  $(function () {
			   // Make Control Center the active tab
			   $('#sub-nav li.active').removeClass('active');
			   $('#sub-nav a[href*="ControlCenter"]').closest('li').addClass('active');
	 
			   var disabledModal = $('#external-modules-disabled-modal');
			   $('#external-modules-enable-modules-button').click(function(){
				    var form = disabledModal.find('.modal-body form');
				    var loadingIndicator = $('<div class="loading-indicator"></div>');
				    form.html('');
				    form.append(loadingIndicator);
	 
				    // This ajax call was originally written thinking the list of available modules would come from a central repo.
				    // It may not be necessary any more.
				    $.post('ajax/get-disabled-modules.php?pid='+pid, { }, function (html) {
					     form.html(html);
				    });
	 
				    disabledModal.modal('show');
			   });
		  });
