use mafia;

CREATE TABLE `league_accept_tournament` (
  `user_id` INT(11) NOT NULL,
  `league_id` INT(11) NOT NULL,
  `tournament_id` INT(11) NOT NULL,
  `stars` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `league_id`, `tournament_id`),
  KEY (`tournament_id`, `league_id`, `user_id`),
  KEY (`league_id`, `user_id`, `tournament_id`),
  CONSTRAINT `accept_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `accept_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  CONSTRAINT `accept_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `tournaments` ADD COLUMN `stars` FLOAT NOT NULL;
