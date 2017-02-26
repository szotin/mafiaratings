use mafia;

ALTER TABLE `registrations`
  ADD COLUMN `start_time1` INT(11);

UPDATE `registrations` SET start_time1 = UNIX_TIMESTAMP(start_time);

ALTER TABLE `registrations`
  DROP INDEX `registration_club_time`;

ALTER TABLE `registrations`
  DROP INDEX `registration_user_time`;

ALTER TABLE `registrations`
  DROP COLUMN `start_time`;

ALTER TABLE `registrations`
  CHANGE COLUMN `start_time1` `start_time` INT(11);

ALTER TABLE `registrations`
  CHANGE COLUMN `duration` `duration` INT(11);

UPDATE `registrations` SET duration = duration * 3600;

ALTER TABLE `registrations`
  ADD INDEX `registration_club_time` (`club_id`, `start_time`);

ALTER TABLE `registrations`
  ADD INDEX `registration_user_time` (`user_id`, `start_time`);

ALTER TABLE `games`
  ADD COLUMN `start_time1` INT(11);

ALTER TABLE `games`
  ADD COLUMN `end_time1` INT(11);

UPDATE `games` SET start_time1 = UNIX_TIMESTAMP(start_time), end_time1 = UNIX_TIMESTAMP(end_time);

ALTER TABLE `games`
  DROP COLUMN `start_time`;

ALTER TABLE `games`
  DROP COLUMN `end_time`;

ALTER TABLE `games`
  CHANGE COLUMN `start_time1` `start_time` INT(11);

ALTER TABLE `games`
  CHANGE COLUMN `end_time1` `end_time` INT(11);
