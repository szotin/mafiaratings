use mafia;

ALTER TABLE `clubs`
  ADD COLUMN `timezone` VARCHAR(64) NOT NULL;

CREATE TABLE `addresses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `address` VARCHAR(256) NOT NULL,
  `map_url` VARCHAR(1024) NOT NULL,
  `timezone` VARCHAR(64) NOT NULL,
  `picture` VARCHAR(8) NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX `address_club` (`club_id`, `name`),
  CONSTRAINT `address_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `address_id` INT(11) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `start_time` BIGINT(20) NOT NULL, 

  PRIMARY KEY (`id`),
  KEY `event_address` (`address_id`),
  CONSTRAINT `event_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
  KEY `event_club` (`club_id`),
  CONSTRAINT `event_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  UNIQUE INDEX `event_start` (`start_time`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `event_users` (
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `coming_odds` TINYINT(2) NOT NULL,
  `people_with_me` TINYINT(2) NOT NULL,

  PRIMARY KEY (`event_id`, `user_id`),
  CONSTRAINT `user_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY `event_user` (`user_id`),
  CONSTRAINT `event_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `event_newcomers` (
  `event_id` INT(11) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `coming_odds` TINYINT(2) NOT NULL,
  `people_with_me` TINYINT(2) NOT NULL,

  PRIMARY KEY (`event_id`, `name`),
  CONSTRAINT `newcomer_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
