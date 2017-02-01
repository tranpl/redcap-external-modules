<?php
namespace ExternalModules;
require_once __DIR__ . '/../classes/ExternalModules.php';
require_once ExternalModules::getProjectHeaderPath();

if(!ExternalModules::hasDesignRights()){
	echo "You don't have permission to manage external modules on this project.";
	return;
}

ExternalModules::addResource('css/style.css');
ExternalModules::addResource('js/globals.js');
ExternalModules::addResource('js/project_lookup.js');

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
							<th colspan="3">Project Settings</th>
							<th>Override Global Setting</th>
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

<?php

require_once ExternalModules::getProjectFooterPath();
