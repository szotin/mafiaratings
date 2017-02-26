use mafia;

ALTER TABLE `events`
  ADD COLUMN `email_subject` VARCHAR(128) NOT NULL;

ALTER TABLE `events`
  ADD COLUMN `notes` TEXT NOT NULL;

UPDATE `events` SET email_subject = 'Mafia';
