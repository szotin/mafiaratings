CREATE TABLE `event_places` (
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `place` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `event_id`),
  KEY (`event_id`, `place`),
  CONSTRAINT `event_place_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  CONSTRAINT `event_place_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tournament_places` (
  `tournament_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `place` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `tournament_id`),
  KEY (`tournament_id`, `place`),
  CONSTRAINT `tournament_place_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  CONSTRAINT `tournament_place_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `series_places` (
  `series_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `place` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `series_id`),
  KEY (`series_id`, `place`),
  CONSTRAINT `series_place_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  CONSTRAINT `series_place_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

UPDATE events SET flags = flags & ~16;
UPDATE tournaments SET flags = flags & ~128;