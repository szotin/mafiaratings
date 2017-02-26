use mafia;

CREATE TABLE `incomers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY(`event_id`, `name`),
  CONSTRAINT `incomer_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `incomers` AUTO_INCREMENT = 2;

CREATE TABLE `incomer_suspects` (
  `reg_id` INT(11) NOT NULL,
  `incomer_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,

  PRIMARY KEY (`reg_id`, `incomer_id`, `user_id`),
  KEY (`user_id`),
  KEY (`incomer_id`),
  CONSTRAINT `suspect_reg` FOREIGN KEY (`reg_id`) REFERENCES `registrations` (`id`),
  CONSTRAINT `suspect_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `suspect_incomer` FOREIGN KEY (`incomer_id`) REFERENCES `incomers` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `registrations`
  CHANGE user_id user_id INT(11) NULL;

ALTER TABLE `registrations`
  ADD COLUMN `incomer_id` INT(11) NULL;

ALTER TABLE `registrations`
  ADD KEY (`incomer_id`);

ALTER TABLE `registrations`
  ADD CONSTRAINT `reg_incomer` FOREIGN KEY (`incomer_id`) REFERENCES `incomers` (`id`);

ALTER TABLE `games`
  CHANGE moderator_id moderator_id INT(11) NULL;
