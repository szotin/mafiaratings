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
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 46, 1 FROM tournaments WHERE id = 46;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 50, 1 FROM tournaments WHERE id = 50;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 47, 1 FROM tournaments WHERE id = 47;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 48, 2 FROM tournaments WHERE id = 48;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 51, 3 FROM tournaments WHERE id = 51;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 52, 1 FROM tournaments WHERE id = 52;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 53, 3 FROM tournaments WHERE id = 53;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 54, 1 FROM tournaments WHERE id = 54;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 57, 1 FROM tournaments WHERE id = 57;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 55, 1 FROM tournaments WHERE id = 55;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 56, 1 FROM tournaments WHERE id = 56;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 58, 1 FROM tournaments WHERE id = 58;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 60, 1 FROM tournaments WHERE id = 60;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 61, 1 FROM tournaments WHERE id = 61;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 62, 3 FROM tournaments WHERE id = 62;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 64, 1 FROM tournaments WHERE id = 64;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 65, 3 FROM tournaments WHERE id = 65;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 66, 3 FROM tournaments WHERE id = 66;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 67, 3 FROM tournaments WHERE id = 67;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 68, 2 FROM tournaments WHERE id = 68;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 69, 5 FROM tournaments WHERE id = 69;

INSERT INTO series (name, start_time, duration, langs, league_id, rules) SELECT 'Season 2022', 1641024000, 31536000, 3, id, rules FROM leagues WHERE id = 2;
SELECT @id := LAST_INSERT_ID();
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 70, 3 FROM tournaments WHERE id = 70;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 71, 1 FROM tournaments WHERE id = 71;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 72, 2 FROM tournaments WHERE id = 72;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 74, 1 FROM tournaments WHERE id = 74;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 75, 3 FROM tournaments WHERE id = 75;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 77, 2 FROM tournaments WHERE id = 77;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 78, 3 FROM tournaments WHERE id = 78;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 79, 1 FROM tournaments WHERE id = 79;

INSERT INTO leagues (name, langs, scoring_id, flags, rules) SELECT 'МЛМ', langs, scoring_id, flags, rules FROM leagues WHERE id = 2;
SELECT @league_id := LAST_INSERT_ID();
INSERT INTO series (name, start_time, duration, langs, league_id, rules, finals_id) SELECT 'МЛМ-2021', 1609488000, 31536000, 3, id, rules, 65 FROM leagues WHERE id = @league_id;
SELECT @id := LAST_INSERT_ID();
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 52, 1 FROM tournaments WHERE id = 52;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 54, 1 FROM tournaments WHERE id = 54;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 57, 1 FROM tournaments WHERE id = 57;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 55, 1 FROM tournaments WHERE id = 55;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 56, 1 FROM tournaments WHERE id = 56;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 58, 1 FROM tournaments WHERE id = 58;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 60, 1 FROM tournaments WHERE id = 60;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 61, 1 FROM tournaments WHERE id = 61;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 64, 1 FROM tournaments WHERE id = 64;
INSERT INTO series_tournaments (series_id, tournament_id, stars) SELECT @id, 65, 3 FROM tournaments WHERE id = 65;

ALTER TABLE tournaments DROP FOREIGN KEY tournament_league;
ALTER TABLE tournaments DROP COLUMN league_id;
ALTER TABLE tournaments DROP FOREIGN KEY fk_tournaments_request_league;
ALTER TABLE tournaments DROP COLUMN request_league_id;
ALTER TABLE tournaments DROP COLUMN stars;
