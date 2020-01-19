use mafia;

ALTER TABLE events ADD COLUMN scoring_flags INT(11) NOT NULL;
UPDATE events SET scoring_flags = 0 WHERE scoring_options = '{"main-1":true,"night1-0":true}';
UPDATE events SET scoring_flags = 1 WHERE scoring_options = '{"main-1":true}';
UPDATE events SET scoring_flags = 2 WHERE scoring_options = '{"night1-0":true}';
ALTER TABLE events DROP COLUMN scoring_options;
ALTER TABLE events CHANGE scoring_flags scoring_options INT(11) NOT NULL;

ALTER TABLE tournaments ADD COLUMN scoring_flags INT(11) NOT NULL;
UPDATE tournaments SET scoring_flags = 0 WHERE scoring_options = '{"main-1":true,"night1-0":true}';
UPDATE tournaments SET scoring_flags = 1 WHERE scoring_options = '{"main-1":true}';
UPDATE tournaments SET scoring_flags = 2 WHERE scoring_options = '{"night1-0":true}';
ALTER TABLE tournaments DROP COLUMN scoring_options;
ALTER TABLE tournaments CHANGE scoring_flags scoring_options INT(11) NOT NULL;

UPDATE scoring_versions SET scoring = '{"main":[{"matter":2,"points":1},{"matter":2,"roles":10,"points":0.05},{"matter":4,"roles":10,"points":-0.05},{"matter":2,"min_difficulty":0.5,"min_points":0,"max_difficulty":1,"max_points":1}],"prima_nocta":[{"matter":1024,"roles":3,"points":0.2},{"matter":2048,"roles":3,"points":0.1}],"extra":[{"matter":16,"points":-0.1},{"matter":32,"points":0.3},{"matter":64,"points":0.2},{"matter":262144,"roles":8,"points":0.05},{"matter":1048576,"roles":2,"points":0.1}],"penalty":[{"matter":4096,"points":-0.2},{"matter":8192,"points":-0.4}],"night1":[{"matter":256,"roles":3,"min_night1":0.15,"min_points":0,"max_night1":0.4,"max_points":0.3}]}' WHERE scoring_id = 21 AND version = 2;
UPDATE events SET scoring_options = 3 WHERE id = 8517;
