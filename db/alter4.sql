use mafia;

ALTER TABLE `users`
  ADD COLUMN `is_subscribed` BOOL NOT NULL;

UPDATE `users` SET `is_subscribed` = true;

ALTER TABLE `events`
  ADD COLUMN `send_emails` TINYINT(2) NOT NULL;

ALTER TABLE `events`
  ADD COLUMN `email` TEXT NOT NULL;

CREATE TABLE `event_emails` (
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `code` VARCHAR(32) NOT NULL,
  `send_time` BIGINT(20) NOT NULL, 

  PRIMARY KEY (`event_id`, `user_id`),
  CONSTRAINT `email_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`user_id`),
  CONSTRAINT `email_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

