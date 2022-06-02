CREATE TABLE `series` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `league_id` INT(11) NOT NULL,
  `start_time` INT(11) NOT NULL,
  `duration` INT(11) NOT NULL,
  `langs` INT(11) NOT NULL,
  `notes` TEXT,
  `finals_id` INT(11) NULL,
  `flags` INT(11) NOT NULL,
  `rules` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`start_time`),
  KEY (`league_id`, `start_time`),
  CONSTRAINT `series_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  KEY (`finals_id`),
  CONSTRAINT `series_final` FOREIGN KEY (`finals_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `series_tournaments` (
  `tournament_id` INT(11) NOT NULL,
  `series_id` INT(11) NOT NULL,
  `stars` float,

  PRIMARY KEY (`tournament_id`, `series_id`),
  KEY (`series_id`),
  CONSTRAINT `series_tournaments_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  CONSTRAINT `series_tournaments_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE club_seasons;
DROP TABLE league_seasons;

INSERT INTO series (name, start_time, duration, langs, league_id, rules, finals_id) SELECT 'Season 2021', 1609488000, 31536000, 3, id, rules, 69 FROM leagues WHERE id = 2;
SELECT @id := LAST_INSERT_ID();
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 46, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 50, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 47, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 48, 2);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 51, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 52, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 53, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 54, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 57, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 55, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 56, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 58, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 60, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 61, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 62, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 64, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 65, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 66, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 67, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 68, 2);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 69, 5);

INSERT INTO series (name, start_time, duration, langs, league_id, rules) SELECT 'Season 2022', 1641024000, 31536000, 3, id, rules FROM leagues WHERE id = 2;
SELECT @id := LAST_INSERT_ID();
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 70, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 71, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 72, 2);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 74, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 75, 3);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 77, 2);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 78, 3);

INSERT INTO leagues (name, langs, scoring_id, flags, rules) SELECT 'МЛМ', langs, scoring_id, flags, rules FROM leagues WHERE id = 2;
SELECT @league_id := LAST_INSERT_ID();
INSERT INTO series (name, start_time, duration, langs, league_id, rules, finals_id) SELECT 'МЛМ-2021', 1609488000, 31536000, 3, id, rules, 65 FROM leagues WHERE id = @league_id;
SELECT @id := LAST_INSERT_ID();
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 52, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 54, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 57, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 55, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 56, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 58, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 60, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 61, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 64, 1);
INSERT INTO series_tournaments (series_id, tournament_id, stars) VALUES (@id, 65, 3);
