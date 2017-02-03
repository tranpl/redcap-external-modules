ALTER TABLE `redcap_external_module_settings` ADD COLUMN `type` VARCHAR(12) NOT NULL DEFAULT 'string' AFTER `key`;

UPDATE `redcap_external_module_settings` SET `type` = 'boolean', `value` = 'true' WHERE `key` = 'enabled' AND value = 1;
UPDATE `redcap_external_module_settings` SET `type` = 'boolean', `value` = 'false' WHERE `key` = 'enabled' AND value = 0;
