CREATE TABLE `bug_reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL,
  `table_num` INT(11) NOT NULL,
  `round_num` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `game` TEXT NOT NULL, 
  `log` TEXT NULL,
  `comment` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`event_id`),
  CONSTRAINT `bug_report_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`user_id`),
  CONSTRAINT `bug_report_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
