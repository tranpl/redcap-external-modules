<?php
namespace ExternalModules;
require_once '../../classes/ExternalModules.php';

$projects = ExternalModules::getEnabledProjects($_GET['prefix']);

while($project = db_fetch_assoc($projects)){
	$url = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . $project['project_id'];
	?><a href="<?=$url?>" style="text-decoration: underline;"><?=$project['name']?></a><br><?php
}