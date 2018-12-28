use mafia;

ALTER TABLE `leagues` ADD COLUMN `rules` TEXT NOT NULL;
ALTER TABLE `tournaments` ADD COLUMN `rules` CHAR(32) NOT NULL;
ALTER TABLE `games` ADD COLUMN `rules` CHAR(32) NOT NULL;
ALTER TABLE `clubs` ADD COLUMN `rules` CHAR(32) NOT NULL;
ALTER TABLE `events` ADD COLUMN `rules` CHAR(32) NOT NULL;
ALTER TABLE `club_rules` ADD COLUMN `rules` CHAR(32) NOT NULL;
ALTER TABLE `league_clubs` ADD COLUMN `rules` CHAR(32) NOT NULL;

UPDATE `leagues` SET `rules` = '{}';

...

ALTER TABLE `leagues` DROP FOREIGN KEY `league_rules`;
ALTER TABLE `leagues` DROP COLUMN `rules_id`;
ALTER TABLE `tournaments` DROP FOREIGN KEY `tournament_rules`;
ALTER TABLE `tournaments` DROP COLUMN `rules_id`;
ALTER TABLE `games` DROP FOREIGN KEY `game_rules`;
ALTER TABLE `games` DROP COLUMN `rules_id`;
ALTER TABLE `clubs` DROP FOREIGN KEY `club_rules`;
ALTER TABLE `clubs` DROP COLUMN `rules_id`;
ALTER TABLE `events` DROP FOREIGN KEY `event_rules`;
ALTER TABLE `events` DROP COLUMN `rules_id`;
ALTER TABLE `club_rules` DROP FOREIGN KEY `club_rules_rules`;
ALTER TABLE `club_rules` DROP COLUMN `rules_id`;
DROP TABLE `rules`;

ALTER TABLE `club_rules` DROP PRIMARY KEY;
ALTER TABLE `club_rules` ADD `id` INT(11) NOT NULL AUTO_INCREMENT KEY;

