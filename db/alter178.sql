CREATE TABLE `pairs` (
  `user1_id` INT NOT NULL,
  `user2_id` INT NOT NULL,
  `policy` INT NOT NULL, -- 0 - separate; 1 - avoid; 2 - no separation; 3 - as many as possible

  PRIMARY KEY (`user1_id`, `user2_id`),
  KEY (`user2_id`),
  CONSTRAINT `pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `league_pairs` (
  `league_id` INT NOT NULL,
  `user1_id` INT NOT NULL,
  `user2_id` INT NOT NULL,
  `policy` INT NOT NULL, -- 0 - separate; 1 - avoid; 2 - no separation; 3 - as many as possible

  PRIMARY KEY (`league_id`, `user1_id`, `user2_id`),
  KEY (`user1_id`),
  KEY (`user2_id`),
  CONSTRAINT `league_pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  CONSTRAINT `league_pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`),
  CONSTRAINT `league_pair_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `club_pairs` (
  `club_id` INT NOT NULL,
  `user1_id` INT NOT NULL,
  `user2_id` INT NOT NULL,
  `policy` INT NOT NULL, -- 0 - separate; 1 - avoid; 2 - no separation; 3 - as many as possible

  PRIMARY KEY (`club_id`, `user1_id`, `user2_id`),
  KEY (`user1_id`),
  KEY (`user2_id`),
  CONSTRAINT `club_pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  CONSTRAINT `club_pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`),
  CONSTRAINT `club_pair_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tournament_pairs` (
  `tournament_id` INT NOT NULL,
  `user1_id` INT NOT NULL,
  `user2_id` INT NOT NULL,
  `policy` INT NOT NULL, -- 0 - separate; 1 - avoid; 2 - no separation; 3 - as many as possible

  PRIMARY KEY (`tournament_id`, `user1_id`, `user2_id`),
  KEY (`user1_id`),
  KEY (`user2_id`),
  CONSTRAINT `tournament_pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tournament_pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tournament_pair_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
