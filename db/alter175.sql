DROP table `maintenance_tasks`;

CREATE TABLE `maintenance_scripts` (
	`name` VARCHAR(128) NOT NULL,
	`filename` VARCHAR(128) NOT NULL,

	PRIMARY KEY (`name`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `maintenance_tasks` (
	`script_name` VARCHAR(128) NOT NULL,
	`name` VARCHAR(128) NOT NULL,
	`num` INT NOT NULL,
	`batches` BIGINT NOT NULL,
	`runs` BIGINT NOT NULL,
	`items` BIGINT NOT NULL,
	`times` BIGINT NOT NULL,
	`items_times` BIGINT NOT NULL,
	`items_items` BIGINT NOT NULL,
	`last_items_count` BIGINT NOT NULL,
	`current_run_items` INT NOT NULL,
	`vars` TEXT NOT NULL,

	PRIMARY KEY (`script_name`, `name`),
	CONSTRAINT script_fk FOREIGN KEY (`script_name`) REFERENCES `maintenance_scripts` (`name`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

