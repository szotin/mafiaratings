use mafia;

CREATE TABLE `mwt_games` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL,
  `time` INT(11) NOT NULL,
  `json` TEXT NULL,
  `game_id` INT(11) NULL,

  PRIMARY KEY (`id`),
  KEY (`game_id`),
  CONSTRAINT `mwt_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  KEY (`user_id`),
  CONSTRAINT `mwt_game_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
