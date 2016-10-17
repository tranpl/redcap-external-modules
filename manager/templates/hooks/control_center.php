<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../../classes/ExternalModules.php';
?>

<script>
	$(function () {
		var span = $('<span style="position: relative; float: left; left: 4px;"></span>')
		span.append('<img src="<?=ExternalModules::getIconPath()?>">')
		span.append('&nbsp;')
		span.append('<a href="<?=ExternalModules::$BASE_URL?>manager/control_center.php">Manage External Modules</a>')
		span.append('<br>')

		var menu = $('#control_center_menu')
		menu.find(':last').remove()
		menu.append('<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>')
		menu.append('<b style="position:relative;">External Modules</b>')
		menu.append('<br>')
		menu.append(span)
	})
</script>
