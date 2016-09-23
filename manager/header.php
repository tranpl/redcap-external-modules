<?php
/**
 * Created by PhpStorm.
 * User: mceverm
 * Date: 9/22/2016
 * Time: 10:39 AM
 */

namespace ExternalModules;

# Use the same doctype as REDCap for layout consistency (without this the footer displays strangely).
?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"><?php

require_once '../../redcap_connect.php';
require_once __DIR__ . '/../classes/ExternalModules.php';