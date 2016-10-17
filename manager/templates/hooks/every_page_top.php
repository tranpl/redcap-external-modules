<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../../classes/ExternalModules.php';

$project_id = $arguments[0];

?>

<script>
	$(function () {
		if ($('#project-menu-logo').length > 0) {
			var newPanel = $('#app_panel').clone()
			newPanel.attr('id', 'external_modules_panel')
			newPanel.find('.x-panel-header div:first-child').html("External Modules")

			var menubox = newPanel.find('.x-panel-body .menubox .menubox')
			var exampleLink = menubox.find('.hang:first-child').clone()
			menubox.html('')

			var newLink
			<?php
			foreach(ExternalModules::getProjectLinks() as $name=>$link){
				?>
				newLink = exampleLink.clone()
				newLink.find('img').attr('src', '<?= APP_PATH_WEBROOT . 'Resources/images/' . $link['icon'] ?>.png')
				newLink.find('a').attr('href', '<?= $link['url'] ?>?pid=<?= $project_id ?>')
				newLink.find('a').html('<?= $name ?>')
				menubox.append(newLink)
				<?php
			}
			?>

			newPanel.insertBefore('#help_panel')
		}
	})
</script>
