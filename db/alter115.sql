use mafia;

CREATE TABLE `sounds` (
`id` INT(11) NOT NULL AUTO_INCREMENT,
`club_id` INT(11) NULL,
`name` VARCHAR(128) NOT NULL,

PRIMARY KEY (`id`),
KEY (`club_id`, `name`),
CONSTRAINT `sound_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
