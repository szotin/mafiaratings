
CREATE TABLE `maintenance_tasks` (
	`name` VARCHAR(200) NOT NULL,
	`batches` BIGINT NOT NULL,
	`runs` BIGINT NOT NULL,
	`items` BIGINT NOT NULL,
	`times` BIGINT NOT NULL,
	`items_times` BIGINT NOT NULL,
	`items_items` BIGINT NOT NULL,
	`last_items_count` BIGINT NOT NULL,
	`current_run_items` INT NOT NULL,
	`vars` TEXT NOT NULL,

	PRIMARY KEY (`name`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

UPDATE users SET flags = flags & ~524288;