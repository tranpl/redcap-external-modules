<?php
namespace ExternalModules;
$exampleModule = ExternalModules::getModuleInstance();

ExternalModules::getProjectHeader();

?>

<h1>This is the Example Module homepage!</h1>
<p>Here is some data from the database: <?= $exampleModule->selectData('...') ?><p>

<?php

ExternalModules::getProjectFooter();