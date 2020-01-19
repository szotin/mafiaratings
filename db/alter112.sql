use mafia;

ALTER TABLE events ADD COLUMN scoring_ops VARCHAR(256) NOT NULL;
UPDATE events SET scoring_ops = '{}' WHERE scoring_options = 0;
UPDATE events SET scoring_ops = '{"flags":1}' WHERE scoring_options = 1;
UPDATE events SET scoring_ops = '{"flags":2}' WHERE scoring_options = 2;
UPDATE events SET scoring_ops = '{"flags":3}' WHERE scoring_options = 3;
ALTER TABLE events DROP COLUMN scoring_options;
ALTER TABLE events CHANGE scoring_ops scoring_options VARCHAR(256) NOT NULL;

ALTER TABLE tournaments ADD COLUMN scoring_ops VARCHAR(256) NOT NULL;
UPDATE tournaments SET scoring_ops = '{}' WHERE scoring_options = 0;
UPDATE tournaments SET scoring_ops = '{"flags":1}' WHERE scoring_options = 1;
UPDATE tournaments SET scoring_ops = '{"flags":2}' WHERE scoring_options = 2;
UPDATE tournaments SET scoring_ops = '{"flags":3}' WHERE scoring_options = 3;
ALTER TABLE tournaments DROP COLUMN scoring_options;
ALTER TABLE tournaments CHANGE scoring_ops scoring_options VARCHAR(256) NOT NULL;

