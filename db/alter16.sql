use mafia;

-- DROP table photo_comments;

CREATE TABLE `forum_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `on_object` TINYINT(2), -- 0 - no object; 1 - event; 2 - photo; 3 - game
  `object_id` INT(11) NULL,
  `visibility` TINYINT(2), -- 0 - everyone; 1 - users; 2 - club; 3 - group; 4 - user
  `visibility_id` INT(11) NULL,
  `user_id` INT(11) NULL,
  `body` TEXT NOT NULL,
  `language` INT(11) NOT NULL,
  `send_time` INT(11) NOT NULL,
  `update_time` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`on_object`, `object_id`),
  KEY (`visibility`, `visibility_id`),
  KEY (`update_time`),
  KEY (`user_id`),
  CONSTRAINT `message_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `forum_responses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `message_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `body` TEXT NOT NULL,
  `language` INT(11) NOT NULL,
  `send_time` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`message_id`, `send_time`),
  KEY (`user_id`),
  CONSTRAINT `response_message` FOREIGN KEY (`message_id`) REFERENCES `forum_messages` (`id`),
  CONSTRAINT `response_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE addresses
  ADD COLUMN has_picture BOOL NOT NULL;

UPDATE addresses SET has_picture = IF(picture = '', false, true);

ALTER TABLE addresses
  DROP COLUMN picture;

UPDATE users SET flags = flags | 256 WHERE photo_ext <> '';

ALTER TABLE users
  DROP COLUMN photo_ext;

ALTER TABLE photos
  DROP COLUMN ext;
