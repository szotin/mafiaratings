CREATE TABLE `tournament_users` (
  `tournament_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `flags` INT(11) NOT NULL,

  PRIMARY KEY (`tournament_id`, `user_id`),
  KEY (`user_id`),
  CONSTRAINT `c_tournament_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `c_tournament_users_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

RENAME TABLE `event_users` TO `event_users1`;

CREATE TABLE `event_users` (
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `coming_odds` TINYINT(2) NULL,
  `people_with_me` TINYINT(2) NULL,
  `late` INT(11) NULL,
  `nickname` VARCHAR(128) NULL,
  `flags` INT(11) NOT NULL DEFAULT 1,

  PRIMARY KEY (`event_id`, `user_id`),
  KEY (`user_id`),
  CONSTRAINT `c_event_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `c_event_users_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

RENAME TABLE incomers TO event_incomers;

INSERT INTO event_users(event_id, user_id, coming_odds, people_with_me, late, nickname) SELECT eu.event_id, eu.user_id, eu.coming_odds, eu.people_with_me, eu.late, u.name FROM event_users1 eu JOIN users u ON u.id = eu.user_id;
INSERT INTO event_users(event_id, user_id, nickname) SELECT r.event_id, r.user_id, r.nick_name FROM registrations r WHERE r.user_id IS NOT NULL AND r.event_id IS NOT NULL ON DUPLICATE KEY UPDATE nickname = r.nick_name;

DROP TABLE event_users1;
DROP TABLE registrations;

RENAME TABLE `user_clubs` TO `club_users`;
