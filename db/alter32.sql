use mafia;

CREATE TABLE `user_clubs`(
  `user_id` INT(11) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `flags` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `club_id`),
  CONSTRAINT `user_club_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  KEY `user_club_club` (`club_id`),
  CONSTRAINT `user_club_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE = INNODB DEFAULT CHARSET = utf8;

INSERT INTO user_clubs (`user_id`, `club_id`, `flags`)
SELECT id
     , club_id
     , (flags & 0xCF)
FROM
  users;

UPDATE users SET flags = (flags & 0xFB0);

ALTER TABLE users
  ADD COLUMN timezone VARCHAR(64) NOT NULL;

UPDATE users u SET u.timezone = (SELECT c.timezone FROM clubs c WHERE c.id = u.club_id);

ALTER TABLE users
  DROP FOREIGN KEY user_club;

ALTER TABLE users
  DROP COLUMN club_id;

