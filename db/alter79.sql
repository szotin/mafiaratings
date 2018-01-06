use mafia;

CREATE TABLE `event_comments` (

  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,
  `event_id` INT(11) NOT NULL,
  `lang` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY `event_comment_event` (`event_id`, `time`),
  KEY `event_comment_user` (`user_id`, `time`),
  CONSTRAINT `event_comment_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  CONSTRAINT `event_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO event_comments (`time`, `user_id`, `comment`, `event_id`, `lang`) SELECT send_time, user_id, body, obj_id, language FROM messages WHERE obj = 2;

CREATE TABLE `photo_comments` (

  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,
  `photo_id` INT(11) NOT NULL,
  `lang` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY `photo_comment_photo` (`photo_id`, `time`),
  KEY `photo_comment_user` (`user_id`, `time`),
  CONSTRAINT `photo_comment_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`),
  CONSTRAINT `photo_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO photo_comments (`time`, `user_id`, `comment`, `photo_id`, `lang`) SELECT send_time, user_id, body, obj_id, language FROM messages WHERE obj = 3;

CREATE TABLE `game_comments` (

  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,
  `game_id` INT(11) NOT NULL,
  `lang` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY `game_comment_game` (`game_id`, `time`),
  KEY `game_comment_user` (`user_id`, `time`),
  CONSTRAINT `game_comment_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  CONSTRAINT `game_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO game_comments (`time`, `user_id`, `comment`, `game_id`, `lang`) SELECT send_time, user_id, body, obj_id, language FROM messages WHERE obj = 4;

DROP TABLE messages_tree;
DROP TABLE messages;
ALTER TABLE users DROP COLUMN forum_last_view;

