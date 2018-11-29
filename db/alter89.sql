use mafia;

ALTER TABLE `log` ADD COLUMN `league_id` INT(11) NULL;

ALTER TABLE `log`
  ADD KEY(`league_id`);

ALTER TABLE `log`
  ADD CONSTRAINT `log_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`);
  
UPDATE `log` SET obj = 'club request' WHERE obj = 'club_request';
UPDATE `log` SET obj = 'email template' WHERE obj = 'email_template';
UPDATE `log` SET obj = 'event emails' WHERE obj = 'event_emails';
UPDATE `log` SET obj = 'league request' WHERE obj = 'league_request';

UPDATE `log` SET league_id = club_id WHERE obj = 'league';
UPDATE `log` SET club_id = NULL WHERE obj = 'league';

ALTER TABLE `clubs` ADD COLUMN `parent_id` INT(11) NULL;

ALTER TABLE `clubs`
  ADD KEY(`parent_id`, `name`);

ALTER TABLE `clubs`
  ADD CONSTRAINT `parent_club` FOREIGN KEY (`parent_id`) REFERENCES `clubs` (`id`);

ALTER TABLE `club_requests` ADD COLUMN `club_id` INT(11) NULL;
ALTER TABLE `club_requests` ADD COLUMN `parent_id` INT(11) NULL;
