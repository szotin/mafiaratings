use mafia;

CREATE TABLE `game_settings` (
  `user_id` INT(11) NOT NULL,
  `autosave` INT(11) NOT NULL,
  `flags` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`),
  CONSTRAINT `game_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO game_settings SELECT id, 60, flags FROM users WHERE (flags & 98304) <> 0;
UPDATE game_settings SET flags = ((flags >> 15) & 3);

UPDATE users SET flags = (flags & ~98304);