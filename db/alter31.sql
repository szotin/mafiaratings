use mafia;

CREATE TABLE `gate_sessions` (
  `token` VARCHAR(32) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `last_activity` INT(11) NOT NULL,
  `version` INT(11) NOT NULL,

  PRIMARY KEY (`token`),
  CONSTRAINT `gate_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE registrations
  DROP FOREIGN KEY registration_event;
ALTER TABLE registrations
  DROP INDEX registration_event_user;
ALTER TABLE registrations
  ADD UNIQUE INDEX registration_event_user(event_id, user_id);
ALTER TABLE registrations
  ADD CONSTRAINT registration_event FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

