use mafia;

ALTER TABLE clubs
   ADD COLUMN email varchar(256) NOT NULL;

ALTER TABLE clubs
   ADD COLUMN phone varchar(256) NOT NULL;

ALTER TABLE clubs
   ADD COLUMN price varchar(256) NOT NULL;

ALTER TABLE club_requests
   ADD COLUMN email varchar(256) NOT NULL;

ALTER TABLE club_requests
   ADD COLUMN phone varchar(256) NOT NULL;

ALTER TABLE events
   ADD COLUMN price varchar(256) NOT NULL;

CREATE TABLE `club_info` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `name` varchar(256) NOT NULL, -- free discussion duration
  `value` text NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`club_id`),
  CONSTRAINT `info_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
