use mafia;

CREATE TABLE `seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `name` VARCHAR(11) NOT NULL,
  `start_time` INT(11) NOT NULL,
  `end_time` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`club_id`, `start_time`),
  CONSTRAINT `season_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

