var request = new XMLHttpRequest();
request.onreadystatechange = function() {
	if (request.readyState == XMLHttpRequest.DONE ) {
		var messageElement = document.getElementById('external-modules-message');
		if(request.responseText == 'success'){
			messageElement.innerHTML = 'The "<?=$activeModulePrefix?>" external module was automatically disabled in order to allow REDCap to function properly.  The REDCap administrator has been notified.  Please save a copy of the above error and fix it before re-enabling the module.';
		}
		else{
			messageElement.innerHTML += '<br>An error occurred while disabling the "<?=$activeModulePrefix?>" module: ' + request.responseText;
		}
	}
};

request.open("POST", "<?=self::$BASE_URL?>/manager/ajax/disable-module.php?<?=self::DISABLE_EXTERNAL_MODULE_HOOKS?>");
request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
request.send("module=<?=$activeModulePrefix?>");
