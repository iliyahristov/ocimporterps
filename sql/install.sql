CREATE TABLE IF NOT EXISTS `PREFIX_ocimp_job` (
  `id_job` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity` VARCHAR(32) NOT NULL,
  `status` ENUM('pending','running','done','error') NOT NULL DEFAULT 'pending',
  `total` INT UNSIGNED NOT NULL DEFAULT 0,
  `processed` INT UNSIGNED NOT NULL DEFAULT 0,
  `errors` INT UNSIGNED NOT NULL DEFAULT 0,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `context` TEXT NULL,
  PRIMARY KEY (`id_job`),
  KEY (`entity`),
  KEY (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_ocimp_job_item` (
  `id_item` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_job` INT UNSIGNED NOT NULL,
  `source_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('pending','ok','error') NOT NULL DEFAULT 'pending',
  `message` TEXT NULL,
  PRIMARY KEY (`id_item`),
  KEY (`id_job`),
  KEY (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_ocimp_map` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity` VARCHAR(32) NOT NULL,
  `source_id` BIGINT UNSIGNED NOT NULL,
  `ps_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_map` (`entity`,`source_id`),
  KEY (`ps_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
