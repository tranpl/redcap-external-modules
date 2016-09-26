<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../../classes/ExternalModules.php';

$project_id = $args[0];

?>

<script>
	$(function () {
		if ($('#project-menu-logo').length > 0) {
			// The project menu is visible.  Add a link to it!
			var linkWrapper = $('a[href*="/DataQuality/index.php"]').parent();
			var newLinkWrapper = linkWrapper.clone();

			newLinkWrapper.find('img').attr('src', '<?=ExternalModules::getIconPath()?>');
			newLinkWrapper.find('a')
				.attr('href', '<?=ExternalModules::$BASE_URL?>manager/project.php?pid=<?=$project_id?>')
				.html('External Modules');

			linkWrapper.after(newLinkWrapper);
		}
	})
</script>
