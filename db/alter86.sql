use mafia;

CREATE TABLE `stats_calculators` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `description` VARCHAR(128) NOT NULL,
  `code` TEXT NOT NULL,
  `owner_id` INT(11) NOT NULL,
  `published` BOOLEAN NOT NULL,

  PRIMARY KEY (`id`),
  KEY(`name`),
  KEY (`owner_id`),
  CONSTRAINT `stats_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
