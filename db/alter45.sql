use mafia;

CREATE TABLE `log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL,
  `club_id` INT(11) NULL,
  `time` INT(11) NOT NULL,
  `obj` VARCHAR(128) NOT NULL,
  `obj_id` INT(11) NULL,
  `ip` VARCHAR(32) NULL,
  `details` TEXT,

  PRIMARY KEY (`id`),
  KEY (`user_id`, `time`),
  CONSTRAINT `log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  KEY (`club_id`, `time`),
  CONSTRAINT `log_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  KEY (`time`),
  KEY (`obj`, `time`),
  KEY (`obj_id`, `obj`, `time`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
