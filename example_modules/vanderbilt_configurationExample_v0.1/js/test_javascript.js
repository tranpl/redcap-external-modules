ExternalModules.customTextAlert = function(textSelector) {
	textSelector.focus(function() {
		alert($(this).html());
	});
};