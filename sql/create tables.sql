CREATE TABLE IF NOT EXISTS `redcap_external_modules` (
  `external_module_id` int(11) NOT NULL AUTO_INCREMENT,
  `directory_prefix` varchar(255) NOT NULL,
  PRIMARY KEY (`external_module_id`),
  UNIQUE KEY `directory_name` (`directory_prefix`)
);

CREATE TABLE IF NOT EXISTS `redcap_external_module_settings` (
  `external_module_id` int(11) NOT NULL,
  `project_id` int(11),
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  KEY `value` (`value`),
  KEY `key` (`key`),
  KEY `FK_redcap_external_module_settings_redcap_external_modules` (`external_module_id`),
  KEY `FK_redcap_external_module_settings_redcap_projects` (`project_id`),
  CONSTRAINT `FK_redcap_external_module_settings_redcap_external_modules` FOREIGN KEY (`external_module_id`) REFERENCES `redcap_external_modules` (`external_module_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_redcap_external_module_settings_redcap_projects` FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE
);
