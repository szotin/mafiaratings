use mafia;

CREATE TABLE `club_ratings` (
  `club_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `role` TINYINT(1) NOT NULL, -- 0 all; 1 red; 2 dark; 3 civil; 4 sheriff; 5 mafia; 6 don
  `rating` INT(11) NOT NULL,
  `games` INT(11) NOT NULL,
  `games_won` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `club_id`, `role`),
  KEY (club_id, role, rating, games DESC, games_won),
  CONSTRAINT `club_rating_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `club_rating_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
