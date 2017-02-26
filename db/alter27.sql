use mafia;

CREATE TABLE `email_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `subject` VARCHAR(128) NOT NULL,
  `body` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX `name` (`club_id`, `name`),
  CONSTRAINT `email_template_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `clubs`
  ADD COLUMN `langs` INT(11) NOT NULL;

UPDATE clubs SET langs = 3;

CREATE TABLE `event_emails` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL,
  `subject` VARCHAR(128) NOT NULL,
  `body` TEXT NOT NULL,
  `send_time` INT(11) NOT NULL,
  `send_count` INT(11) NOT NULL,
  `status` TINYINT(1) NOT NULL, -- 0 - waiting; 1 - sending; 2 - completed; 3 - canceled

  PRIMARY KEY (`id`),
  KEY (`event_id`, `send_time`),
  KEY (`send_time`),
  CONSTRAINT `event_emails_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO event_emails (event_id, subject, body, send_time, send_count, status) 
  SELECT id, email_subject, email, start_time - send_emails * 3600 * 24, 0, 0 FROM events WHERE send_emails > 0;

SET @users_count = (SELECT count(*) FROM users WHERE (flags & 0x40) <> 0);
UPDATE event_emails SET send_count = @users_count, status = 2 WHERE send_time < UNIX_TIMESTAMP();

UPDATE emails e SET e.obj_id = (SELECT max(id) FROM event_emails WHERE event_id = e.obj_id) WHERE e.obj = 0;

ALTER TABLE `events`
  DROP COLUMN send_emails;

ALTER TABLE `events`
  DROP COLUMN email;

ALTER TABLE `events`
  DROP COLUMN email_subject;

ALTER TABLE `events`
  ADD COLUMN `vis` TINYINT(1) NOT NULL;

ALTER TABLE `events`
  ADD COLUMN `vis_id` INT(11) NOT NULL;

UPDATE events SET vis = 0, vis_id = 0;

ALTER TABLE addresses
  ADD COLUMN flags INT(11) NOT NULL;

UPDATE addresses SET flags = has_picture;

ALTER TABLE addresses
  DROP COLUMN has_picture;