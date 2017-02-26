use mafia;

ALTER TABLE `events`
  ADD COLUMN `duration` TINYINT(2) NOT NULL;

ALTER TABLE `events`
  ADD COLUMN `flags` INT NOT NULL;

UPDATE `events` SET `duration` = 6, `flags` = 1;

ALTER TABLE `registrations`
  ADD COLUMN `event_id` INT(11) NULL;

ALTER TABLE `registrations`
  ADD INDEX `registration_event_user` (`event_id`, `user_id`);

ALTER TABLE `registrations`
  ADD CONSTRAINT `registration_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

UPDATE registrations r 
  SET r.event_id = (SELECT e.id FROM events e WHERE e.club_id = r.club_id AND e.start_time <= r.start_time AND e.start_time + e.duration * 3600 > r.start_time);

ALTER TABLE `games`
  ADD COLUMN `event_id` INT(11) NULL;

ALTER TABLE `games`
  ADD INDEX `game_event_id` (`event_id`, `id`);

ALTER TABLE `games`
  ADD CONSTRAINT `game_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

UPDATE games g 
  SET g.event_id = (SELECT e.id FROM events e WHERE e.club_id = g.club_id AND e.start_time <= g.start_time AND e.start_time + e.duration * 3600 > g.start_time);
