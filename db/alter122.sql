DROP TABLE rebuild_ratings;

CREATE TABLE `rebuild_ratings` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`start_time` INT(11) NOT NULL,
	`end_time` INT(11) NOT NULL,
	`game_id` INT(11) NOT NULL,
	`current_game_id` INT(11) NULL,
	`average_game_proceeding_time` DOUBLE NOT NULL,
	`batch_size` INT(11) NOT NULL,
	`games_proceeded` INT(11) NOT NULL,
	`ratings_changed` INT(11) NOT NULL,

	PRIMARY KEY (`id`),
	KEY `game` (`game_id`),
	CONSTRAINT `game_fk` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
	UNIQUE INDEX `start` (`start_time`),
	INDEX `end` (`end_time`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE games DROP INDEX best_player_id;
CREATE INDEX end_time ON games (end_time, id);
ALTER TABLE games DROP COLUMN as_is;

ALTER TABLE players ADD COLUMN game_end_time INT(11) NOT NULL;
UPDATE players p SET p.game_end_time = (SELECT g.end_time FROM games g WHERE g.id = p.game_id);
ALTER TABLE players DROP INDEX player_game;
ALTER TABLE players DROP INDEX player_user;
CREATE INDEX player_user_time_game ON players (user_id, game_end_time, game_id);

