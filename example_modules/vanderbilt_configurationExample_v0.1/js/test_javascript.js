ExternalModules.customTextAlert = function(textSelector) {
	textSelector.focus(function() {
		console.log($(this).val());
	});
};