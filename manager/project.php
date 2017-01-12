<?php
namespace ExternalModules;
require_once __DIR__ . '/../classes/ExternalModules.php';
require_once ExternalModules::getProjectHeaderPath();

if(!ExternalModules::hasDesignRights()){
	echo "You don't have permission to manage external modules on this project.";
	return;
}

ExternalModules::addResource('css/style.css');

ExternalModules::safeRequireOnce('templates/enabled-modules.php');

?>

<style>
	#external-modules-configure-modal th:nth-child(2),
	#external-modules-configure-modal td:nth-child(3) {
		text-align: center;
	}
</style>

<div id="external-modules-configure-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Configure Module: <span class="module-name"></span></h4>
			</div>
			<div class="modal-body">
				<table class="table table-no-top-row-border">
					<thead>
						<tr>
							<th>Project Settings</th>
							<th style='text-align: center;'>Value</th>
							<th style='min-width: 75px; text-align: center;'></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<div class="modal-footer">
				<button data-dismiss="modal">Cancel</button>
				<button class="save">Save</button>
			</div>
		</div>
	</div>
</div>

<script>
        $(function() {
                var pid = <?=$_GET['pid']?>;

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
                        data['<?=ExternalModules::KEY_ENABLED?>'] = false;
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
</script>

<?php

require_once ExternalModules::getProjectFooterPath();
