use mafia;

CREATE TABLE `series_extra_points` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `series_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `reason` VARCHAR(128) NOT NULL,
  `details` TEXT NULL,
  `points` FLOAT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`user_id`, `series_id`, `time`),
  CONSTRAINT `series_points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  KEY (`series_id`, `time`),
  CONSTRAINT `points_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  KEY (`reason`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
