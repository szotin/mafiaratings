use mafia;

CREATE TABLE `systems` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `digits` TINYINT(2) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`club_id`, `name`),
  CONSTRAINT `system_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `points` (
  `system_id` INT(11) NOT NULL,
  `flag` INT(11) NOT NULL,
  `points` INT(11) NOT NULL,

  PRIMARY KEY (`system_id`, `flag`),
  CONSTRAINT `point_system` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE clubs
  ADD COLUMN system_id INT(11) NULL;

ALTER TABLE clubs
  ADD KEY(system_id);

ALTER TABLE clubs
  ADD CONSTRAINT `club_system` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`);

ALTER TABLE `events`
  ADD COLUMN system_id INT(11) NULL;

ALTER TABLE `events`
  ADD KEY(system_id);

ALTER TABLE `events`
  ADD CONSTRAINT `event_system` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`);

