CREATE TABLE `series_series` (
  `parent_id` INT(11) NOT NULL,
  `child_id` INT(11) NOT NULL,
  `stars` float,
  `flags` INT(11) NOT NULL,
  `fee` INT(11) NULL,

  PRIMARY KEY (`parent_id`, `child_id`),
  KEY (`child_id`),
  CONSTRAINT `series_parent` FOREIGN KEY (`parent_id`) REFERENCES `series` (`id`),
  CONSTRAINT `series_child` FOREIGN KEY (`child_id`) REFERENCES `series` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
