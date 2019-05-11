use mafia;

ALTER TABLE tournaments ADD COLUMN request_league_id INT(11) NULL;
ALTER TABLE tournaments ADD KEY i_tournaments_request_league (request_league_id);
ALTER TABLE tournaments ADD CONSTRAINT fk_tournaments_request_league FOREIGN KEY (request_league_id) REFERENCES leagues(id);

DROP table league_accept_tournament;

CREATE TABLE `tournament_approves` (
  `user_id` INT(11) NOT NULL,
  `league_id` INT(11) NOT NULL,
  `tournament_id` INT(11) NOT NULL,
  `stars` float NOT NULL,

  PRIMARY KEY (`user_id`, `league_id`, `tournament_id`),
  KEY (`tournament_id`, `league_id`, `user_id`),
  KEY (`league_id`, `user_id`, `tournament_id`),
  CONSTRAINT `approve_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `approve_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  CONSTRAINT `approve_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

