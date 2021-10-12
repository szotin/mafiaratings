DROP TABLE rebuild_ratings;

CREATE TABLE `rebuild_ratings` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`start_time` INT(11) NOT NULL,
	`end_time` INT(11) NOT NULL,
	`game_id` INT(11) NOT NULL,

	PRIMARY KEY (`id`),
	KEY `game` (`game_id`),
	CONSTRAINT `game_fk` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
	UNIQUE INDEX `start` (`start_time`),
	UNIQUE INDEX `end` (`end_time`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE games DROP COLUMN as_is;