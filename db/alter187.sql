CREATE TABLE `series_regs` (
  `series_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `flags` INT(11) NOT NULL DEFAULT 1,

  PRIMARY KEY (`series_id`, `user_id`),
  KEY (`user_id`),
  CONSTRAINT `c_series_regs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `c_series_regs_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
