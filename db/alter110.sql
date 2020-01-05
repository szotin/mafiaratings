use mafia;

DROP TABLE rounds;

UPDATE clubs SET scoring_id = 21 WHERE scoring_id = 15;
UPDATE events SET scoring_id = 21 WHERE scoring_id = 15;
UPDATE tournaments SET scoring_id = 21 WHERE scoring_id = 15;
UPDATE scorings SET version = NULL WHERE id = 15;
DELETE FROM scoring_versions WHERE scoring_id = 15;
DELETE FROM scorings WHERE id = 15;

UPDATE scorings SET version = NULL WHERE id = 21;
INSERT INTO scoring_versions (scoring_id, version, scoring) SELECT 21, 3, v.scoring FROM scoring_versions v WHERE scoring_id = 11 AND version = 1;
UPDATE events SET scoring_version = 3 WHERE scoring_id = 21;
UPDATE tournaments SET scoring_version = 3 WHERE scoring_id = 21;
UPDATE scoring_versions SET version = 2 WHERE scoring_id = 21 AND version = 1;
UPDATE events SET scoring_version = 2 WHERE scoring_id = 21;
UPDATE tournaments SET scoring_version = 2 WHERE scoring_id = 21;
UPDATE scoring_versions SET version = 1 WHERE scoring_id = 21 AND version = 3;
UPDATE scorings SET version = 2 WHERE id = 21;
UPDATE clubs SET scoring_id = 21 WHERE scoring_id = 11;
UPDATE events SET scoring_id = 21, scoring_version = 1 WHERE scoring_id = 11;
UPDATE tournaments SET scoring_id = 21, scoring_version = 1 WHERE scoring_id = 11;
UPDATE scorings SET version = NULL WHERE id = 11;
DELETE FROM scoring_versions WHERE scoring_id = 11;
DELETE FROM scorings WHERE id = 11;

UPDATE scoring_versions SET scoring='{"main":[{"matter":2,"points":1},{"matter":2,"min_difficulty":0.5,"min_points":0,"max_difficulty":1,"max_points":1,"option_name":"баллы за трудность игры","def":false}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.4},{"matter":2048,"roles":3,"points":0.25}],"penalty":[{"matter":4096,"points":-0.5},{"matter":8192,"points":-0.5}],"night1":[{"matter":260,"figm_first_night_score":0.4,"option_name":"баллы за отстрел в первую ночь","def":false}]}' WHERE scoring_id = 19 AND version = 1;
UPDATE clubs SET scoring_id = 19 WHERE scoring_id = 10;
UPDATE leagues SET scoring_id = 19 WHERE scoring_id = 10;
UPDATE events SET scoring_id = 19, scoring_options='{"main-1":true,"night1-0":true}' WHERE scoring_id = 10;
UPDATE tournaments SET scoring_id = 19, scoring_options='{"main-1":true,"night1-0":true}' WHERE scoring_id = 10;
UPDATE clubs SET scoring_id = 19 WHERE scoring_id = 17;
UPDATE events SET scoring_id = 19, scoring_options='{"main-1":true}' WHERE scoring_id = 17;
UPDATE tournaments SET scoring_id = 19, scoring_options='{"main-1":true}' WHERE scoring_id = 17;
UPDATE clubs SET scoring_id = 19 WHERE scoring_id = 18;
UPDATE events SET scoring_id = 19, scoring_options='{"night1-0":true}' WHERE scoring_id = 18;
UPDATE tournaments SET scoring_id = 19, scoring_options='{"night1-0":true}' WHERE scoring_id = 18;

UPDATE scorings SET version = NULL WHERE id = 10;
DELETE FROM scoring_versions WHERE scoring_id = 10;
DELETE FROM scorings WHERE id = 10;
UPDATE scorings SET version = NULL WHERE id = 17;
DELETE FROM scoring_versions WHERE scoring_id = 17;
DELETE FROM scorings WHERE id = 17;
UPDATE scorings SET version = NULL WHERE id = 18;
DELETE FROM scoring_versions WHERE scoring_id = 18;
DELETE FROM scorings WHERE id = 18;

UPDATE scorings SET version = NULL WHERE id = 16;
DELETE FROM scoring_versions WHERE scoring_id = 16;
DELETE FROM scorings WHERE id = 16;
