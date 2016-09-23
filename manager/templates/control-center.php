<?php
namespace Modules;
require_once dirname(__FILE__) . '/../../classes/Modules.php';
?>

<script>
	$(function () {
		var link = $('a[href*="ControlCenter/modules_settings.php"]');
		var newLink = link.clone();
		newLink.attr('href', '<?=Modules::$BASE_URL?>manager/control_center.php');
		newLink.html('External Modules');

		link.after(newLink);
		newLink
		.before('<br>')
		.before("<img src='<?=Modules::getIconPath()?>'>&nbsp; ")
	})
</script>
