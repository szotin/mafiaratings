use mafia;

ALTER TABLE games DROP COLUMN round_num;
ALTER TABLE events DROP COLUMN round_num;
ALTER TABLE events DROP COLUMN planned_games;

CREATE TABLE scoring_versions (
	id INT(11) NOT NULL AUTO_INCREMENT,
	scoring_id INT(11) NOT NULL,
	version INT(11) NOT NULL,
	scoring TEXT NOT NULL,

	PRIMARY KEY (id),
	KEY (scoring_id, version),
	CONSTRAINT scoring_fk FOREIGN KEY (scoring_id) REFERENCES scorings (id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE scorings ADD COLUMN league_id INT(11) NULL;
ALTER TABLE scorings ADD KEY (league_id);
ALTER TABLE scorings ADD CONSTRAINT system_league FOREIGN KEY(league_id) REFERENCES leagues(id);
ALTER TABLE scorings ADD COLUMN flags INT(11) NOT NULL;

ALTER TABLE events ADD COLUMN scoring_version_id INT(11) NULL;
ALTER TABLE tournaments ADD COLUMN scoring_version_id INT(11) NULL;

-- Империя Мафии
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (2, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":1},{"matter":2,"roles":10,"points":2}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":1024,"roles":3,"points":1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 2;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 2;
-- Для чемпионата
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (3, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":1024,"roles":3,"points":0.1},{"matter":32768,"roles":1,"points":0.2},{"matter":131072,"roles":12,"points":0.1},{"matter":524288,"roles":8,"points":0.1},{"matter":1048576,"roles":2,"points":0.2}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 3;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 3;
-- ТТ
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (5, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":1024,"roles":3,"points":1},{"matter":131072,"roles":8,"points":1},{"matter":1048576,"roles":2,"points":1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 5;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 5;
-- Мафия в городе
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (6, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":1024,"roles":3,"points":1},{"matter":131072,"roles":8,"points":1},{"matter":1048576,"roles":2,"points":1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 6;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 6;
-- Theatrum
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (8, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":1024,"roles":3,"points":0.1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 8;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 8;
-- Club Main
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (9, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}]},{"name":"extra","policies":[{"matter":32,"points":0.5},{"matter":64,"points":0.25},{"matter":1024,"roles":3,"points":0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 9;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 9;
-- ФИИМ с баллами за трудность и за отстрелы
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (10, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2}]},{"name":"extra","policies":[{"matter":256,"min_night1":0,"min_points":0,"max_night1":0.4,"max_points":0.4},{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 10;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 10;
-- WaVaCa-2017
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (11, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":1024,"roles":3,"points":0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 11;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 11;
-- 3-4-4-5
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (13, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":1024,"roles":3,"points":1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 13;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 13;
-- University
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (14, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":0.5},{"matter":1024,"roles":3,"points":0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 14;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 14;
-- WaVaCa-2018
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (15, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2},{"matter":2,"roles":10,"min_difficulty":0.5,"min_points":1.05,"max_difficulty":1,"max_points":2.05},{"matter":4,"roles":10,"points":-0.05}]},{"name":"extra","policies":[{"matter":16,"points":-0.1},{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":256,"roles":3,"min_night1":0.15,"min_points":0,"max_night1":0.4,"max_points":0.3},{"matter":1024,"roles":3,"points":0.2},{"matter":2048,"roles":3,"points":0.1},{"matter":4096,"points":-0.2},{"matter":8192,"points":-0.4},{"matter":262144,"roles":8,"points":0.05},{"matter":1048576,"roles":2,"points":0.1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 15;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 15;
-- test
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (16, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":1024,"roles":3,"points":1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 16;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 16;
-- ФИИМ с баллами за трудность
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (17, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2}]},{"name":"extra","policies":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 17;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 17;
-- ФИИМ с баллами за отстрелы
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (18, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":256,"min_night1":0,"min_points":0,"max_night1":0.4,"max_points":0.4},{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 18;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 18;
-- ФИИМ
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (19, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 19;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 19;
-- VaWaCa
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (21, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2},{"matter":2,"roles":10,"points":0.05},{"matter":4,"roles":10,"points":-0.05}]},{"name":"extra","policies":[{"matter":16,"points":-0.1},{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":256,"roles":3,},{"matter":1024,"roles":3,"points":0.2},{"matter":2048,"roles":3,"points":0.1},{"matter":4096,"points":-0.2},{"matter":8192,"points":-0.4},{"matter":262144,"roles":8,"points":0.05},{"matter":1048576,"roles":2,"points":0.1}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 21;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 21;
-- MafClub
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (22, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":4}]},{"name":"extra","policies":[{"matter":8,"points":0.2},{"matter":32,"points":2},{"matter":64,"points":1.5},{"matter":1024,"roles":1,"points":1},{"matter":1024,"roles":2,"points":0.5},{"matter":2048,"roles":1,"points":0.5},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5},{"matter":16384,"points":-0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 22;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 22;
-- Как ФИИМ
INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (23, 1, '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":32,"points":0.7},{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}');
SELECT @id := LAST_INSERT_ID();
UPDATE events SET scoring_version_id = @id WHERE scoring_id = 23;
UPDATE tournaments SET scoring_version_id = @id WHERE scoring_id = 23;

ALTER TABLE events ADD KEY (scoring_version_id);
ALTER TABLE events ADD CONSTRAINT event_scoring_version FOREIGN KEY(scoring_version_id) REFERENCES scoring_versions(id);
ALTER TABLE events DROP FOREIGN KEY fk_events_scoring;
ALTER TABLE events DROP COLUMN scoring_id;

ALTER TABLE tournaments ADD KEY (scoring_version_id);
ALTER TABLE tournaments ADD CONSTRAINT tournament_scoring_version FOREIGN KEY(scoring_version_id) REFERENCES scoring_versions(id);
ALTER TABLE tournaments DROP FOREIGN KEY tournament_scoring;
ALTER TABLE tournaments DROP COLUMN scoring_id;

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


-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":1},{"matter":2,"roles":10,"points":2}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":1024,"roles":3,"points":1}]}]}' WHERE scoring_id = 2;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":1024,"roles":3,"points":0.1},{"matter":32768,"roles":1,"points":0.2},{"matter":131072,"roles":12,"points":0.1},{"matter":524288,"roles":8,"points":0.1},{"matter":1048576,"roles":2,"points":0.2}]}]}' WHERE scoring_id = 3;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":1024,"roles":3,"points":1},{"matter":131072,"roles":8,"points":1},{"matter":1048576,"roles":2,"points":1}]}]}' WHERE scoring_id = 5;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":1},{"matter":1024,"roles":3,"points":1},{"matter":131072,"roles":8,"points":1},{"matter":1048576,"roles":2,"points":1}]}]}' WHERE scoring_id = 6;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":1024,"roles":3,"points":0.1}]}]}' WHERE scoring_id = 8;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}]},{"name":"extra","policies":[{"matter":32,"points":0.5},{"matter":64,"points":0.25},{"matter":1024,"roles":3,"points":0.5}]}]}' WHERE scoring_id = 9;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2}]},{"name":"extra","policies":[{"matter":256,"min_night1":0,"min_points":0,"max_night1":0.4,"max_points":0.4},{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}' WHERE scoring_id = 10;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":1024,"roles":3,"points":0.5}]}]}' WHERE scoring_id = 11;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":1024,"roles":3,"points":1}]}]}' WHERE scoring_id = 13;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"points":2},{"matter":2,"roles":10,"points":2.5},{"matter":4,"roles":10,"points":-0.5}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":64,"points":0.5},{"matter":1024,"roles":3,"points":0.5}]}]}' WHERE scoring_id = 14;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":5,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2},{"matter":2,"roles":10,"min_difficulty":0.5,"min_points":1.05,"max_difficulty":1,"max_points":2.05},{"matter":4,"roles":10,"points":-0.05}]},{"name":"extra","policies":[{"matter":16,"points":-0.1},{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":256,"roles":3,"min_night1":0.15,"min_points":0,"max_night1":0.4,"max_points":0.3},{"matter":1024,"roles":3,"points":0.2},{"matter":2048,"roles":3,"points":0.1},{"matter":4096,"points":-0.2},{"matter":8192,"points":-0.4},{"matter":262144,"roles":8,"points":0.05},{"matter":1048576,"roles":2,"points":0.1}]}]}' WHERE scoring_id = 15;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"roles":1,"points":3},{"matter":2,"roles":6,"points":4},{"matter":2,"roles":8,"points":5},{"matter":4,"roles":10,"points":-1}]},{"name":"extra","policies":[{"matter":32,"points":1},{"matter":1024,"roles":3,"points":1}]}]}' WHERE scoring_id = 16;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2}]},{"name":"extra","policies":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}' WHERE scoring_id = 17;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":256,"min_night1":0,"min_points":0,"max_night1":0.4,"max_points":0.4},{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}' WHERE scoring_id = 18;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}' WHERE scoring_id = 19;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"min_difficulty":0.5,"min_points":1,"max_difficulty":1,"max_points":2},{"matter":2,"roles":10,"points":0.05},{"matter":4,"roles":10,"points":-0.05}]},{"name":"extra","policies":[{"matter":16,"points":-0.1},{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":256,"roles":3,},{"matter":1024,"roles":3,"points":0.2},{"matter":2048,"roles":3,"points":0.1},{"matter":4096,"points":-0.2},{"matter":8192,"points":-0.4},{"matter":262144,"roles":8,"points":0.05},{"matter":1048576,"roles":2,"points":0.1}]}]}' WHERE scoring_id = 21;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":4}]},{"name":"extra","policies":[{"matter":8,"points":0.2},{"matter":32,"points":2},{"matter":64,"points":1.5},{"matter":1024,"roles":1,"points":1},{"matter":1024,"roles":2,"points":0.5},{"matter":2048,"roles":1,"points":0.5},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5},{"matter":16384,"points":-0.5}]}]}' WHERE scoring_id = 22;
-- UPDATE scoring_versions SET scoring = '{"sorting":"acgk","groups":[{"name":"main","policies":[{"matter":2,"points":1}]},{"name":"extra","policies":[{"matter":32,"points":0.7},{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25},{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}]}]}' WHERE scoring_id = 23;
