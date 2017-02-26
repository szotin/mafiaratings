use mafia;

CREATE TABLE `rating_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name_en` VARCHAR(128) NOT NULL,
  `name_ru` VARCHAR(128) NOT NULL,
  `span` INT(11) NOT NULL,
  `renew_span` INT(11) NOT NULL,
  `renew_time` INT(11) NOT NULL,
  `def` BOOL NOT NULL,

  PRIMARY KEY (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO rating_types (name_en, name_ru, span, renew_span, renew_time, def) VALUES ('All time', '?? ??? ?????', 0, 0, 0, FALSE);
INSERT INTO rating_types (name_en, name_ru, span, renew_span, renew_time, def) VALUES ('Last year', '?? ????????? ???', 31536000, 604800, 0, TRUE);

DROP TABLE ratings;

CREATE TABLE `ratings` (
  `user_id` INT(11) NOT NULL,
  `type_id` INT(11) NOT NULL,
  `role` TINYINT(1) NOT NULL,
  `rating` INT(11) NOT NULL,
  `games` INT(11) NOT NULL,
  `games_won` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `type_id`, `role`),
  KEY (type_id, role, rating, games DESC, games_won),
  CONSTRAINT  `rating_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT  `rating_rating_type` FOREIGN KEY (`type_id`) REFERENCES `rating_types` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT user_id, 1, 0, SUM(rating), count(*), SUM(won) FROM players GROUP BY user_id; -- all
INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT user_id, 1, 1, SUM(rating), count(*), SUM(won) FROM players WHERE role <= 1 GROUP BY user_id; -- red
INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT user_id, 1, 2, SUM(rating), count(*), SUM(won) FROM players WHERE role >= 2 GROUP BY user_id; -- dark
INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT user_id, 1, 3 + role, SUM(rating), count(*), SUM(won) FROM players GROUP BY user_id, role; -- others

INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT p.user_id, 2, 0, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 GROUP BY user_id; -- all
INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT p.user_id, 2, 1, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 AND p.role <= 1 GROUP BY user_id; -- red
INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT p.user_id, 2, 2, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 AND p.role >= 2 GROUP BY user_id; -- dark
INSERT
  INTO ratings (user_id, type_id, role, rating, games, games_won) 
  SELECT p.user_id, 2, 3 + p.role, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 GROUP BY user_id, role; -- others


DROP TABLE club_ratings;

CREATE TABLE `club_ratings` (
  `club_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `type_id` INT(11) NOT NULL,
  `role` TINYINT(1) NOT NULL,
  `rating` INT(11) NOT NULL,
  `games` INT(11) NOT NULL,
  `games_won` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `club_id`, `type_id`, `role`),
  KEY (`club_id`, type_id, role, rating, games DESC, games_won),
  KEY (type_id),
  CONSTRAINT  `club_rating_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  CONSTRAINT  `club_rating_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT  `club_rating_rating_type` FOREIGN KEY (`type_id`) REFERENCES `rating_types` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 1, 0, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id GROUP BY g.club_id, p.user_id; -- all
INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 1, 1, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE p.role <= 1 GROUP BY g.club_id, p.user_id; -- red
INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 1, 2, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE p.role >= 2 GROUP BY g.club_id, p.user_id; -- dark
INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 1, 3 + p.role, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id GROUP BY g.club_id, p.user_id, p.role; -- others


INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 2, 0, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 GROUP BY g.club_id, p.user_id; -- all
INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 2, 1, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 AND p.role <= 1 GROUP BY g.club_id, p.user_id; -- red
INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 2, 2, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 AND p.role >= 2 GROUP BY g.club_id, p.user_id; -- dark
INSERT
  INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) 
  SELECT g.club_id, p.user_id, 2, 3 + p.role, SUM(p.rating), count(*), SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE g.start_time > UNIX_TIMESTAMP() - 31536000 GROUP BY g.club_id, p.user_id, p.role; -- others

