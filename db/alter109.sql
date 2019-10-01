use mafia;

ALTER TABLE games DROP FOREIGN KEY objection_user;
ALTER TABLE games DROP COLUMN `objection_user_id`;
ALTER TABLE games DROP COLUMN `objection`;
ALTER TABLE games ADD COLUMN canceled BOOLEAN NOT NULL;

CREATE TABLE `objections` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `timestamp` INT(11) NOT NULL,
  `objection_id` INT(11) NULL,
  `user_id` INT(11) NOT NULL,
  `game_id` INT(11) NOT NULL,
  `message` TEXT NOT NULL,
  `accept` INT(2) NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),
  KEY (`user_id`),
  CONSTRAINT `objection_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  KEY (`objection_id`),
  CONSTRAINT `objection_objection` FOREIGN KEY (`objection_id`) REFERENCES `objections` (`id`),
  KEY (`game_id`),
  CONSTRAINT `objection_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
