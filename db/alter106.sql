use mafia;

ALTER TABLE games DROP COLUMN round_num;
ALTER TABLE events DROP COLUMN round_num;
ALTER TABLE events DROP COLUMN planned_games;
