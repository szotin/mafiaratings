CREATE TABLE `series` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `league_id` INT(11) NOT NULL,
  `start_time` INT(11) NOT NULL,
  `duration` INT(11) NOT NULL,
  `langs` INT(11) NOT NULL,
  `notes` TEXT,
  `final_id` INT(11) NULL,
  `flags` INT(11) NOT NULL,
  `rules` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`start_time`),
  KEY (`league_id`, `start_time`),
  CONSTRAINT `series_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  KEY (`final_id`),
  CONSTRAINT `series_final` FOREIGN KEY (`final_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tournament_series` (
  `tournament_id` INT(11) NOT NULL,
  `series_id` INT(11) NOT NULL,
  `stars` float,

  PRIMARY KEY (`tournament_id`, `series_id`),
  KEY (`series_id`),
  CONSTRAINT `tournament_series_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  CONSTRAINT `tournament_series_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE club_seasons;
DROP TABLE league_seasons;