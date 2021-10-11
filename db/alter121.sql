ALTER TABLE game_issues ADD COLUMN feature_flags INT(11) NOT NULL;
ALTER TABLE game_issues ADD COLUMN new_feature_flags INT(11) NOT NULL;
ALTER TABLE game_issues DROP PRIMARY KEY, ADD PRIMARY KEY(game_id, feature_flags);

ALTER TABLE games DROP FOREIGN KEY game_best_player;
ALTER TABLE games DROP COLUMN best_player_id;

