ALTER TABLE games CHANGE game_table table_num INT(11) NULL;
ALTER TABLE games CHANGE game_number game_num INT(11) NULL;

UPDATE games SET game_num = game_num + 1 WHERE game_num IS NOT NULL;
UPDATE games SET table_num = table_num + 1 WHERE table_num IS NOT NULL;

ALTER TABLE current_games CHANGE round_num game_num INT(11) NOT NULL;

UPDATE current_games SET game_num = game_num + 1, table_num = table_num + 1;

ALTER TABLE bug_reports CHANGE round_num game_num INT(11) NOT NULL;

UPDATE bug_reports SET game_num = game_num + 1, table_num = table_num + 1;
