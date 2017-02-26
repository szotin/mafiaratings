use mafia;

CREATE TABLE `photos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `ext` VARCHAR(8) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`event_id`),
  CONSTRAINT `photo_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`user_id`),
  CONSTRAINT `photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_photos` (
  `user_id` INT(11) NOT NULL,
  `photo_id` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `photo_id`),
  KEY (`photo_id`),
  CONSTRAINT `user_photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `user_photo_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `photo_comments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `photo_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`photo_id`),
  KEY (`user_id`),
  CONSTRAINT `comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `comment_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
