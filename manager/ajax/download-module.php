<?php
include_once(dirname(dirname(dirname(dirname(__FILE__))))."/redcap_connect.php");
\ExternalModules\ExternalModules::downloadModule($_GET['module_id']);