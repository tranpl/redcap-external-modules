<?php
namespace ExternalModules;
require_once __DIR__ . '/../classes/ExternalModules.php';
require_once ExternalModules::getProjectHeaderPath();

if(!ExternalModules::hasDesignRights()){
	echo "You don't have permission to manage external modules on this project.";
	return;
}

ExternalModules::addResource('css/style.css');
?>

<h4 style="margin-top: 0;">
	 <img src="<?= '../images/puzzle_medium.png' ?>">
	 Module Management for Your Project
</h4>

<?php
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
	 var pid = <?=$_GET['pid']?>;
</script>
<script src='js/project.js'>
</script>

<?php

require_once ExternalModules::getProjectFooterPath();
