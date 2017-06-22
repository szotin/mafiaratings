use mafia;

UPDATE clubs SET system_id = (SELECT id FROM scoring_systems WHERE name = '') WHERE system_id IS NULL;

ALTER TABLE `clubs` DROP FOREIGN KEY `clubs_ibfk_1`;
ALTER TABLE `clubs` CHANGE COLUMN `system_id` `scoring_id` int(11) NOT NULL;
ALTER TABLE `clubs` ADD CONSTRAINT `club_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scoring_systems` (`id`);

UPDATE events e SET e.system_id = (SELECT c.scoring_id FROM clubs c WHERE e.club_id = c.id) WHERE e.system_id IS NULL;

ALTER TABLE `events` DROP FOREIGN KEY `events_ibfk_1`;
ALTER TABLE `events` CHANGE COLUMN `system_id` `scoring_id` int(11) NOT NULL;
ALTER TABLE `events` ADD CONSTRAINT `event_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scoring_systems` (`id`);

RENAME TABLE scoring_systems TO scorings;
