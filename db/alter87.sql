use mafia;

CREATE TABLE `league_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `langs` INT(11) NOT NULL,
  `web_site` VARCHAR(256) NOT NULL,
  `email` VARCHAR(256) NOT NULL,
  `phone` VARCHAR(256) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`user_id`),
  CONSTRAINT `league_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `leagues` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `langs` INT(11) NOT NULL,
  `web_site` VARCHAR(256) NOT NULL,
  `email` VARCHAR(256) NOT NULL,
  `phone` VARCHAR(256) NOT NULL,
  `rules_id` INT(11) NOT NULL,
  `scoring_id` INT(11) NOT NULL,
  `flags` INT(11) NOT NULL, // ******

  PRIMARY KEY (`id`),
  KEY (`name`),
  KEY (`rules_id`),
  CONSTRAINT `league_rules` FOREIGN KEY (`rules_id`) REFERENCES `rules` (`id`),
  KEY (`scoring_id`),
  CONSTRAINT `league_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `league_managers` (
  `league_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  
  PRIMARY KEY (`league_id`, `user_id`),
  CONSTRAINT `manager_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  KEY (`user_id`),
  CONSTRAINT `league_manager` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
	
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `league_clubs` (
  `league_id` INT(11) NOT NULL,
  `club_id` INT(11) NOT NULL,
  
  PRIMARY KEY (`league_id`, `club_id`),
  CONSTRAINT `club_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  KEY (`club_id`),
  CONSTRAINT `league_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)
	
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tournaments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `league_id` INT(11) NULL,
  `club_id` INT(11) NOT NULL,
  `address_id` INT(11) NOT NULL,
  `start_time` INT(11) NOT NULL,
  `duration` INT(11) NOT NULL,
  `langs` INT(11) NOT NULL,
  `notes` TEXT,
  `price` VARCHAR(128) NOT NULL,
  `rules_id` INT(11) NOT NULL,
  `scoring_id` INT(11) NOT NULL,
  `standings_settings` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`start_time`),
  KEY (`league_id`, `start_time`),
  CONSTRAINT `tournament_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  KEY (`club_id`, `start_time`),
  CONSTRAINT `tournament_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  KEY (`address_id`, `start_time`),
  CONSTRAINT `tournament_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
  KEY (`rules_id`, `start_time`),
  CONSTRAINT `tournament_rules` FOREIGN KEY (`rules_id`) REFERENCES `rules` (`id`),
  KEY (`scoring_id`, `start_time`),
  CONSTRAINT `tournament_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `events` ADD COLUMN `tournament_id` INT(11) NULL;
ALTER TABLE `events` ADD KEY(`tournament_id`, `start_time`);
ALTER TABLE `events` ADD CONSTRAINT `event_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`);

ALTER TABLE `events` DROP COLUMN `vis`;
ALTER TABLE `events` DROP COLUMN `vis_id`;


