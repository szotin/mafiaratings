CREATE TABLE `gainings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `league_id` INT(11) NULL,
  `name` VARCHAR(128) NOT NULL,
  `version` INT(11) NULL,

  PRIMARY KEY (`id`),
  KEY (`league_id`, `name`),
  CONSTRAINT `gaining_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `gaining_versions` (
  `gaining_id` INT(11) NOT NULL,
  `version` INT(11) NOT NULL,
  `gaining` TEXT NOT NULL,

  PRIMARY KEY (`gaining_id`, `version`),
  CONSTRAINT `gaining_fk` FOREIGN KEY (`gaining_id`) REFERENCES `gainings` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE series ADD COLUMN gaining_id INT(11) NULL;
ALTER TABLE series ADD COLUMN gaining_version INT(11) NULL;
ALTER TABLE series ADD KEY (gaining_id, gaining_version);
ALTER TABLE series ADD CONSTRAINT series_gaining_version FOREIGN KEY(gaining_id, gaining_version) REFERENCES gaining_versions(gaining_id, version);

ALTER TABLE leagues ADD COLUMN gaining_id INT(11) NULL;
ALTER TABLE leagues ADD CONSTRAINT leagues_gaining FOREIGN KEY(gaining_id) REFERENCES gainings(id);

