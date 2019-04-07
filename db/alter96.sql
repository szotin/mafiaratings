use mafia;

CREATE TABLE `event_extra_points` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `reason` VARCHAR(128) NOT NULL,
  `details` TEXT NOT NULL,
  `points` FLOAT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`user_id`, `event_id`, `time`),
  CONSTRAINT `points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  KEY (`event_id`, `time`),
  CONSTRAINT `points_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`reason`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
