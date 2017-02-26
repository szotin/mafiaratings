use mafia;

CREATE TABLE `news` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `timestamp` INT(11) NOT NULL,
  `message` text NOT NULL,
  `lang` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`club_id`),
  CONSTRAINT `news_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
