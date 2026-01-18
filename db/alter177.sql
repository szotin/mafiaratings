CREATE TABLE `mr_bonus_stats` (
  `game_id` INT NOT NULL,
  `time` INT NOT NULL,
  `red_num` INT NOT NULL,
  `red_mean` FLOAT NOT NULL,
  `red_variance` FLOAT NOT NULL,
  `black_num` INT NOT NULL,
  `black_mean` FLOAT NOT NULL,
  `black_variance` FLOAT NOT NULL,

  PRIMARY KEY (`game_id`),
  KEY (`time`, `game_id`),
  CONSTRAINT `mr_bonus_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
