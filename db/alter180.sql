CREATE TABLE `page_profiling` (
	`page` VARCHAR(191) NOT NULL, 
	`num` INT NOT NULL, 
	`mean` DOUBLE NOT NULL, 
	`variance` DOUBLE NOT NULL, 
	`maximum` DOUBLE NOT NULL,

	PRIMARY KEY (`page`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

RENAME TABLE page_profiling TO profiling_pages;

CREATE TABLE `profiling_ips` (
	`ip` CHAR(64) NOT NULL, 
	`agent` VARCHAR(256) NOT NULL, 
	`num` INT NOT NULL, 
	`sum` DOUBLE NOT NULL, 

	PRIMARY KEY (`ip`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
