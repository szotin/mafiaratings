use mafia;

-- st stands for speak time
-- spt stands for speak prompt time
CREATE TABLE `rules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `flags` INT(11) NOT NULL,
  `st_free` MEDIUMINT(3) NOT NULL, -- free discussion duration
  `spt_free` TINYINT(3) NOT NULL,
  `st_reg` MEDIUMINT(3) NOT NULL, -- regular player speech duration
  `spt_reg` TINYINT(3) NOT NULL, 
  `st_killed` MEDIUMINT(3) NOT NULL, -- killed player speech duration
  `spt_killed` TINYINT(3) NOT NULL, 
  `st_def` MEDIUMINT(3) NOT NULL, -- defensive speech duration
  `spt_def` TINYINT(3) NOT NULL, 

  PRIMARY KEY (`id`),
  UNIQUE INDEX (flags, st_free, spt_free, st_reg, spt_reg, st_killed, spt_killed, st_def, spt_def)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO rules (flags, st_free, st_reg, st_killed, st_def, spt_free, spt_reg, spt_killed, spt_def)
  VALUES (28, 0, 60, 30, 30, 0, 10, 5, 5);

SET @rules_id = LAST_INSERT_ID();

ALTER TABLE games
   ADD COLUMN `rules_id` INT(11) NOT NULL;

UPDATE games SET rules_id = @rules_id;

ALTER TABLE games
   ADD KEY `game_rules` (`rules_id`);

ALTER TABLE games
   ADD CONSTRAINT `game_rules` FOREIGN KEY (`rules_id`) REFERENCES `rules` (`id`);

ALTER TABLE clubs
   ADD COLUMN `rules_id` INT(11) NOT NULL;

UPDATE clubs SET rules_id = @rules_id;

ALTER TABLE clubs
   ADD KEY `club_rules` (`rules_id`);

ALTER TABLE clubs
   ADD CONSTRAINT `club_rules` FOREIGN KEY (`rules_id`) REFERENCES `rules` (`id`);

ALTER TABLE events
   ADD COLUMN `rules_id` INT(11) NOT NULL;

UPDATE events SET rules_id = @rules_id;

ALTER TABLE events
   ADD KEY `event_rules` (`rules_id`);

ALTER TABLE events
   ADD CONSTRAINT `event_rules` FOREIGN KEY (`rules_id`) REFERENCES `rules` (`id`);


ALTER TABLE clubs
  DROP COLUMN `country`;

ALTER TABLE clubs
  DROP COLUMN `city`;

ALTER TABLE clubs
  DROP COLUMN `address`;

ALTER TABLE clubs
  ADD COLUMN flags INT(11) NOT NULL;

UPDATE clubs SET flags = 1 WHERE is_banned = true;

ALTER TABLE clubs
  DROP COLUMN `is_banned`;

ALTER TABLE clubs
  ADD COLUMN web_site VARCHAR(256) NOT NULL;

CREATE TABLE `club_rules` (
  `rules_id` INT(11) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,

  PRIMARY KEY (`rules_id`, `club_id`),
  UNIQUE INDEX `name` (`club_id`, `name`),
  CONSTRAINT `club_rules_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  CONSTRAINT `club_rules_rules` FOREIGN KEY (`rules_id`) REFERENCES `rules` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

