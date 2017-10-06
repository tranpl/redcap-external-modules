UPDATE `redcap_external_module_settings` SET `type` = 'boolean', `value` = 'true' WHERE `key` = 'enabled' AND value = 1;
UPDATE `redcap_external_module_settings` SET `type` = 'boolean', `value` = 'false' WHERE `key` = 'enabled' AND value = 0;
