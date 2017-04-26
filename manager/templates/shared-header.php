<?php
namespace ExternalModules;
require_once __DIR__ . '/../../classes/ExternalModules.php';

ExternalModules::addResource('select2/dist/css/select2.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css', 'sha256-xJOZHfpxLR/uhh1BwYFS5fhmOAdIRQaiOul5F/b7v3s=');
ExternalModules::addResource('select2/dist/js/select2.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', 'sha256-+mWd/G69S4qtgPowSELIeVAv7+FuL871WXaolgXnrwQ=');

ExternalModules::addResource('css/style.css');