-- The names of the scoring systems, the scoring normalizers and the gaining systems become
-- localizable, the same way as the names of clubs, leagues, events, tournaments, series and rules
-- (alter217.sql - alter223.sql): each system refers to a set of names in "names", and every query
-- joins it for the language it shows the system in.
--
-- Most systems are copies of the default ones of the site, made for every club and league, and
-- carry the same name - "MWT", "Mafia Ratings". The systems with the same name share one set rather
-- than each getting a copy of it, in all three tables at once; names are told apart by case
-- (BINARY) and taken without the spaces around them. Dropping the name column takes it out of the
-- (club_id, name) and (league_id, name) keys, which stay as keys on the owner.

ALTER TABLE `scorings` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `club_id`;
ALTER TABLE `normalizers` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `league_id`;
ALTER TABLE `gainings` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `league_id`;

CREATE TEMPORARY TABLE `system_names` (
  `name_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO system_names (name) SELECT DISTINCT BINARY TRIM(name) FROM scorings;
INSERT IGNORE INTO system_names (name) SELECT DISTINCT BINARY TRIM(name) FROM normalizers;
INSERT IGNORE INTO system_names (name) SELECT DISTINCT BINARY TRIM(name) FROM gainings;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
SET @n = 0;
UPDATE system_names SET name_id = @base + (@n := @n + 1) ORDER BY name;

INSERT INTO names (id, langs, name) SELECT name_id, 16777215, name FROM system_names;
UPDATE scorings s JOIN system_names sn ON sn.name = TRIM(s.name) COLLATE utf8_bin SET s.name_id = sn.name_id;
UPDATE normalizers s JOIN system_names sn ON sn.name = TRIM(s.name) COLLATE utf8_bin SET s.name_id = sn.name_id;
UPDATE gainings s JOIN system_names sn ON sn.name = TRIM(s.name) COLLATE utf8_bin SET s.name_id = sn.name_id;

DROP TEMPORARY TABLE `system_names`;

ALTER TABLE `scorings` DROP COLUMN `name`;
ALTER TABLE `normalizers` DROP COLUMN `name`;
ALTER TABLE `gainings` DROP COLUMN `name`;

ALTER TABLE `scorings` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `normalizers` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `gainings` ALTER COLUMN `name_id` DROP DEFAULT;

ALTER TABLE `scorings` ADD KEY `name_id` (`name_id`);
ALTER TABLE `normalizers` ADD KEY `name_id` (`name_id`);
ALTER TABLE `gainings` ADD KEY `name_id` (`name_id`);

ALTER TABLE `scorings` ADD CONSTRAINT `scoring_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
ALTER TABLE `normalizers` ADD CONSTRAINT `normalizer_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
ALTER TABLE `gainings` ADD CONSTRAINT `gaining_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
