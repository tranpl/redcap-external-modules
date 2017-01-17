<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../../classes/ExternalModules.php';
?>

<script>
	$(function () {
		var span = $('<span style="position: relative; float: left; left: 4px;"></span>');

		<?php
		foreach(ExternalModules::getControlCenterLinks() as $name=>$link){
			?>
			span.append('<img src="<?= APP_PATH_WEBROOT . 'Resources/images/' . $link['icon'] ?>.png">');
			span.append('&nbsp; ');
			span.append('<a href="<?= $link['url'] ?>"><?= $name ?></a>');
			span.append('<br>');
			<?php
		}
		?>

		var menu = $('#control_center_menu');
		menu.find(':last').remove();
		menu.append('<div style="clear: both;padding-bottom:6px;margin:0 -6px 3px;border-bottom:1px solid #ddd;"></div>');
		menu.append('<b style="position:relative;">External Modules</b>');
		menu.append('<br>');
		menu.append(span);
	});
</script>
