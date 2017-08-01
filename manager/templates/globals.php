<?php

namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';

if(empty($versionsByPrefixJSON)) {
    $versionsByPrefixJSON = "''";
}

if(empty($configsByPrefixJSON)) {
    $configsByPrefixJSON = "''";
}


// The decision to use TinyMCE was not taken lightly.  I actually tried integrating Quill, Trix, and Summernote as well, but they either
// didn't work as well out of the box when placed inside the configuration model, or were not as flexible/customizable.
?><script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.6.1/tinymce.min.js" integrity="sha256-GnWmLZ0UK0TTmZEj5w4U6SLOnEJlalLnsOLDcUXzYyc=" crossorigin="anonymous"></script><?php
ExternalModules::addResource('select2/dist/css/select2.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css', 'sha256-xJOZHfpxLR/uhh1BwYFS5fhmOAdIRQaiOul5F/b7v3s=');
ExternalModules::addResource('select2/dist/js/select2.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', 'sha256-+mWd/G69S4qtgPowSELIeVAv7+FuL871WXaolgXnrwQ=');

ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'globals.js');
?>
<script>
    ExternalModules.PID = <?=json_encode(@$_GET['pid'])?>;
    ExternalModules.SUPER_USER = <?=SUPER_USER?>;
    ExternalModules.KEY_ENABLED = <?=json_encode(ExternalModules::KEY_ENABLED)?>;
    ExternalModules.OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS = <?=json_encode(ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS)?>;
    ExternalModules.OVERRIDE_PERMISSION_LEVEL_SUFFIX = <?=json_encode(ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX)?>;
    ExternalModules.BASE_URL = <?=json_encode(ExternalModules::$BASE_URL)?>;
    ExternalModules.configsByPrefixJSON = <?=$configsByPrefixJSON?>;
    ExternalModules.versionsByPrefixJSON = <?=$versionsByPrefixJSON?>;
	ExternalModules.LIB_URL = '<?=APP_PATH_WEBROOT_FULL?>consortium/modules/login.php?referer=<?=urlencode(PAGE_FULL)?>'
							+ '&php_version=<?=urlencode(PHP_VERSION)?>&redcap_version=<?=urlencode(REDCAP_VERSION)?>'
							+ '&downloaded_modules=<?=urlencode(implode(",", getDirFiles(dirname(APP_PATH_DOCROOT).DS.'modules'.DS)))?>';
    
    $(function () {
        var disabledModal = $('#external-modules-disabled-modal');
        $('#external-modules-enable-modules-button').click(function(){
            var form = disabledModal.find('.modal-body form');
            var loadingIndicator = $('<div class="loading-indicator"></div>');

            var pid = ExternalModules.PID;
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
        $('#external-modules-download-modules-button').click(function(){
			window.location.href = ExternalModules.LIB_URL;
		});
		if (isNumeric(getParameterByName('download_module_id')) && getParameterByName('download_module_name') != '') {
			$('#external-modules-download').dialog({ title: 'Download external module?', bgiframe: true, modal: true, width: 550, 
				close: function() { 
					modifyURL('<?=PAGE_FULL?>');
				},
				buttons: {
					'Cancel': function() {
						$(this).dialog('close'); 
					},
					'Download': function() {
						showProgress(1);
						$.get('<?=APP_URL_EXTMOD?>manager/ajax/download-module.php?module_id='+getParameterByName('download_module_id'),{},function(data){
							showProgress(0,0);
							if (data == '0') {
								simpleDialog("An error occurred because the External Module could not be found.","ERROR");
							} else if (data == '1') {
								simpleDialog("An error occurred because the External Module zip file could not be written to the REDCap temp directory before extracting it.","ERROR");
							} else if (data == '2' || data == '3') {
								simpleDialog("An error occurred because the External Module zip file could not be extracted or could not create a new modules directory on the REDCap web server.","ERROR");
							} else if (data == '4') {
								simpleDialog("An error occurred because the External Module directory already exists on the REDCap web server. Thus, it cannot be used for this module.","ERROR");
							} else {
								simpleDialog(data,"SUCCESS",null,null,function(){
									$('#external-modules-enable-modules-button').trigger('click');
								},"Close");
								// Append module name to ExternalModules.LIB_URL
								ExternalModules.LIB_URL += '%2C'+getParameterByName('download_module_name');
							}
						});
						$(this).dialog('close'); 
					}
				} 
			});
		}
    });
</script>
