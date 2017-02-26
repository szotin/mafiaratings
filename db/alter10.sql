use mafia;

CREATE TABLE `signup` (
  `name` VARCHAR(128) NOT NULL,
  `password` VARCHAR(32) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `email` VARCHAR(256) NOT NULL,
  `email_code` VARCHAR(32) NOT NULL,
  `is_male` BOOL NOT NULL,
  `request_time` INT NOT NULL,

  PRIMARY KEY (`name`),
  KEY `signup_club` (`club_id`),
  CONSTRAINT `signup_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE event_newcomers;

