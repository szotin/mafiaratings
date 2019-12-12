use mafia;

ALTER TABLE games DROP COLUMN round_num;
ALTER TABLE events DROP COLUMN round_num;
ALTER TABLE events DROP COLUMN planned_games;

CREATE TABLE scoring_versions (
	scoring_id INT(11) NOT NULL,
	version INT(11) NOT NULL,
	scoring TEXT NOT NULL,

	PRIMARY KEY (scoring_id, version),
	CONSTRAINT scoring_fk FOREIGN KEY (scoring_id) REFERENCES scorings (id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE scorings ADD COLUMN league_id INT(11) NULL;
ALTER TABLE scorings ADD KEY (league_id);
ALTER TABLE scorings ADD CONSTRAINT system_league FOREIGN KEY(league_id) REFERENCES leagues(id);
ALTER TABLE scorings ADD COLUMN flags INT(11) NOT NULL;
ALTER TABLE scorings ADD COLUMN version INT(11) NULL DEFAULT 1;

ALTER TABLE events ADD COLUMN scoring_version INT(11) NOT NULL;
ALTER TABLE events ADD COLUMN scoring_options TEXT NULL;
ALTER TABLE tournaments ADD COLUMN scoring_version INT(11) NOT NULL;
ALTER TABLE tournaments ADD COLUMN scoring_options TEXT NULL;

-- Империя Мафии
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (2, 1, '{"main":[{"matter":2,"roles":5,"points":1},{"matter":2,"roles":10,"points":2}],"prima_nocta":[{"matter":1024,"roles":3,"points":1}],"extra":[{"matter":32,"points":1},{"matter":64,"points":1}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 2;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 2;
-- Для чемпионата
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (3, 1, '{"main":[{"matter":2,"points":1}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.1}],"extra":[{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":32768,"roles":1,"points":0.2},{"matter":131072,"roles":12,"points":0.1},{"matter":524288,"roles":8,"points":0.1},{"matter":1048576,"roles":2,"points":0.2}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 3;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 3;
-- ТТ
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (5, 1, '{"main":[{"matter":2,"points":3,"roles":1},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}],"prima_nocta":[{"matter":1024,"roles":3,"points":1}],"extra":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":131072,"roles":8,"points":1},{"matter":1048576,"roles":2,"points":1}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 5;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 5;
-- Мафия в городе
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (6, 1, '{"main":[{"matter":2,"points":3,"roles":1},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}],"prima_nocta":[{"matter":1024,"roles":3,"points":1}],"extra":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":1048576,"roles":2,"points":1},{"matter":131072,"roles":8,"points":1}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 6;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 6;
-- Theatrum
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (8, 1, '{"main":[{"matter":2,"points":1}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.1}],"extra":[{"matter":32,"points":0.3},{"matter":64,"points":0.2}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 8;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 8;
-- Club Main
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (9, 1, '{"main":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.5}],"extra":[{"matter":32,"points":0.5},{"matter":64,"points":0.25}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 9;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 9;
-- ФИИМ с баллами за трудность и за отстрелы
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (10, 1, '{"main":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25}],"penalty":[{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}],"night1":[{"matter":256,"min_night1":0,"min_points":0,"max_night1":0.4,"max_points":0.4}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 10;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 10;
-- WaVaCa-2017
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (11, 1, '{"main":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.5}],"extra":[{"matter":32,"points":1}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 11;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 11;
-- 3-4-4-5
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (13, 1, '{"main":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":4,"roles":10,"points":-1}],"prima_nocta":[{"matter":1024,"roles":3,"points":1}],"extra":[{"matter":32,"points":1}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 13;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 13;
-- University
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (14, 1, '{"main":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.5}],"extra":[{"matter":32,"points":1},{"matter":64,"points":0.5}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 14;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 14;
-- WaVaCa-2018
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (15, 1, '{"main":[{"matter":2,"roles":5,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2},{"matter":2,"roles":10,"min_difficulty":0.5,"min_points":1.05,"max_difficulty":1,"max_points":2.05},{"matter":4,"roles":10,"points":-0.05}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.2},{"matter":2048,"roles":3,"points":0.1},{"matter":16,"points":-0.1},{"matter":262144,"roles":8,"points":0.05},{"matter":1048576,"roles":2,"points":0.1}],"extra":[{"matter":32,"points":0.3},{"matter":64,"points":0.2}],"penalty":[{"matter":4096,"points":-0.2},{"matter":8192,"points":-0.4}],"night1":[{"matter":256,"roles":3,"min_night1":0.15,"min_points":0,"max_night1":0.4,"max_points":0.3}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 15;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 15;
-- test
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (16, 1, '{"main":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}],"prima_nocta":[{"matter":1024,"roles":3,"points":1}],"extra":[{"matter":32,"points":1}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 16;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 16;
-- ФИИМ с баллами за трудность
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (17, 1, '{"main":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25}],"penalty":[{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 17;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 17;
-- ФИИМ с баллами за отстрелы
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (18, 1, '{"main":[{"matter":2,"points":1}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25}],"penalty":[{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}],"night1":[{"matter":256,"min_night1":0,"min_points":0,"max_night1":0.4,"max_points":0.4}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 18;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 18;
-- ФИИМ
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (19, 1, '{"main":[{"matter":2,"points":1}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25}],"penalty":[{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 19;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 19;
-- VaWaCa
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (21, 1, '{"main":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2},{"matter":2,"roles":10,"points":0.05},{"matter":4,"roles":10,"points":-0.05}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.2},{"matter":2048,"roles":3,"points":0.1}],"extra":[{"matter":16,"points":-0.1},{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":262144,"roles":8,"points":0.05},{"matter":1048576,"roles":2,"points":0.1}],"penalty":[{"matter":4096,"points":-0.2},{"matter":8192,"points":-0.4}],"night1":[{"matter":256,"roles":3,"min_night1":0.15,"min_points":0,"max_night1":0.4,"max_points":0.3}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 21;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 21;
-- MafClub
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (22, 1, '{"main":[{"matter":2,"points":4}],"prima_nocta":[{"matter":1024,"roles":1,"points":1},{"matter":1024,"roles":2,"points":0.5},{"matter":2048,"roles":1,"points":0.5}],"extra":[{"matter":8,"points":0.2},{"matter":32,"points":2},{"matter":64,"points":1.5}],"penalty":[{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5},{"matter":16384,"points":-0.5}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 22;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 22;
-- Как ФИИМ
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (23, 1, '{"main":[{"matter":2,"points":1}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25}],"extra":[{"matter":32,"points":0.7}],"penalty":[{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}');
UPDATE events SET scoring_version = 1 WHERE scoring_id = 23;
UPDATE tournaments SET scoring_version = 1 WHERE scoring_id = 23;

ALTER TABLE events ADD KEY (scoring_id, scoring_version);
ALTER TABLE events ADD CONSTRAINT event_scoring_version FOREIGN KEY(scoring_id, scoring_version) REFERENCES scoring_versions(scoring_id, version);
ALTER TABLE events DROP FOREIGN KEY fk_events_scoring;

ALTER TABLE tournaments ADD KEY (scoring_id, scoring_version);
ALTER TABLE tournaments ADD CONSTRAINT tournament_scoring_version FOREIGN KEY(scoring_id, scoring_version) REFERENCES scoring_versions(scoring_id, version);
ALTER TABLE tournaments DROP FOREIGN KEY tournament_scoring;

ALTER TABLE scorings DROP COLUMN sorting;
DROP TABLE scoring_rules;

RENAME TABLE seasons TO club_seasons;

CREATE TABLE league_seasons (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `league_id` INT(11) NOT NULL,
  `name` VARCHAR(256) NOT NULL,
  `start_time` INT(11) NOT NULL,
  `end_time` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY `league` (`league_id`),
  CONSTRAINT `season_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE scorings ADD KEY (id, version);
ALTER TABLE scorings ADD CONSTRAINT system_version FOREIGN KEY(id, version) REFERENCES scoring_versions(scoring_id, version);

ALTER TABLE games ADD COLUMN tournament_id INT(11) NULL;
ALTER TABLE games ADD KEY (tournament_id);
ALTER TABLE games ADD CONSTRAINT game_tournament FOREIGN KEY(tournament_id) REFERENCES tournaments(id);

UPDATE games g, events e SET g.tournament_id = e.tournament_id WHERE e.id = g.event_id;

ALTER TABLE photo_albums ADD COLUMN tournament_id INT(11) NULL;
ALTER TABLE photo_albums ADD KEY (tournament_id);
ALTER TABLE photo_albums ADD CONSTRAINT photo_album_tournament FOREIGN KEY(tournament_id) REFERENCES tournaments(id);
UPDATE photo_albums a SET a.tournament_id = (SELECT e.tournament_id FROM events e WHERE e.id = a.event_id) WHERE a.event_id IS NOT NULL;

ALTER TABLE videos ADD COLUMN tournament_id INT(11) NULL;
ALTER TABLE videos ADD KEY (tournament_id);
ALTER TABLE videos ADD CONSTRAINT video_tournament FOREIGN KEY(tournament_id) REFERENCES tournaments(id);
UPDATE videos v, games g SET v.event_id = g.event_id, v.tournament_id = g.tournament_id WHERE g.video_id = v.id;

ALTER TABLE tournaments DROP COLUMN standings_settings;
ALTER TABLE tournaments ADD COLUMN standings_settings VARCHAR(256) NULL;
