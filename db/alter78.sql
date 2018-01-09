use mafia;

CREATE TABLE `videos` (

  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(256) NOT NULL,
  `video` VARCHAR(1024) NOT NULL,
  `type` INT(11) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `event_id` INT(11) NULL,
  `lang` INT(11) NOT NULL,
  `time` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY `video_club` (`club_id`, `type`, `time`),
  KEY `video_event` (`event_id`, `type`, `time`),
  KEY `video_type` (`type`, `time`),
  KEY `video_time` (`time`),
  CONSTRAINT `video_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  CONSTRAINT `video_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_videos` (

  `user_id` INT(11) NOT NULL,
  `video_id` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `video_id`),
  KEY `video` (`video_id`),
  CONSTRAINT `user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

