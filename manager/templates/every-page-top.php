<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/Modules.php';
?>

<script>
	$(function () {
		if ($('#project-menu-logo').length > 0) {
			// The project menu is visible.  Add a link to it!
			var linkWrapper = $('a[href*="/DataQuality/index.php"]').parent();
			var newLinkWrapper = linkWrapper.clone();

			newLinkWrapper.find('img').attr('src', '<?=Modules::getIconPath()?>');
			newLinkWrapper.find('a')
				.attr('href', '<?=Modules::$BASE_URL?>manager/project.php?pid=<?=$project_id?>')
				.html('External Modules');

			linkWrapper.after(newLinkWrapper);
		}
	})
</script>
