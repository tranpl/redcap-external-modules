CREATE TABLE IF NOT EXISTS `redcap_external_modules` (
  `external_module_id` int(11) NOT NULL AUTO_INCREMENT,
  `directory_prefix` varchar(255) NOT NULL,
  PRIMARY KEY (`external_module_id`),
  UNIQUE KEY `directory_prefix` (`directory_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `redcap_external_module_settings` (
  `external_module_id` int(11) NOT NULL,
  `project_id` int(11) NULL DEFAULT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  KEY `value` (`value`),
  KEY `key` (`key`),
  KEY `external_module_id` (`external_module_id`),
  KEY `project_id` (`project_id`),
  FOREIGN KEY (`external_module_id`) REFERENCES `redcap_external_modules` (`external_module_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
