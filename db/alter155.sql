CREATE TABLE `current_games` (
  `event_id` INT(11) NOT NULL,
  `table_num` INT(11) NOT NULL,
  `round_num` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `game` TEXT NOT NULL, 

  PRIMARY KEY (`event_id`, `table_num`, `round_num`),
  CONSTRAINT `current_game_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`user_id`),
  CONSTRAINT `current_game_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
