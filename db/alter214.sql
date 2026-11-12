-- The default gaining system of a club or of a league is its own, the way its default scoring
-- system and normalizer are since db/alter213.sql: clubs.gaining_id (new - a club has series of
-- its own too) and leagues.gaining_id point to a gaining system that belongs to the club or the
-- league, and it is edited in place. Whoever used a system of someone else gets a copy of the
-- version they were using, under the same name. New versions of the original do not reach the
-- copies.
--
-- The site's own default is its gaining system with flag 1, see get_site_gaining_id() in
-- include/scoring.php. It has been hardcoded to 1 (MWT) so far.

ALTER TABLE `gainings` ADD COLUMN `flags` INT(11) NOT NULL DEFAULT 0;
ALTER TABLE `clubs` ADD COLUMN `gaining_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `clubs` ADD KEY `gaining_id` (`gaining_id`);

UPDATE `gainings` SET `flags` = 1 WHERE `id` = 1 AND `club_id` IS NULL AND `league_id` IS NULL;

-- A gaining system of the site called by the name of a league is that league's: it goes to the
-- league with all its versions, so the series that used it keep using it.
UPDATE `gainings` s JOIN `leagues` l ON l.`name` = s.`name` SET s.`league_id` = l.`id`
  WHERE s.`club_id` IS NULL AND s.`league_id` IS NULL AND (s.`flags` & 1) = 0;

-- A gaining system of the site that only one club or only one league uses - in its series, as its
-- default - is that club's or that league's: it goes to it with all its versions. The ones used by
-- more than one stay with the site. This has to come before the copies below, so that such an
-- owner gets the system itself rather than a copy of it.
CREATE TEMPORARY TABLE `_system_users` (`system_id` INT NOT NULL, `club_id` INT NULL, `league_id` INT NULL);

INSERT INTO `_system_users` SELECT `gaining_id`, `club_id`, `league_id` FROM `series`;
INSERT INTO `_system_users` SELECT `gaining_id`, NULL, `id` FROM `leagues`;

UPDATE `gainings` s
  JOIN (SELECT `system_id`, MIN(`club_id`) AS `club_id`, MIN(`league_id`) AS `league_id` FROM `_system_users`
    GROUP BY `system_id` HAVING COUNT(DISTINCT IFNULL(CONCAT('c', `club_id`), CONCAT('l', `league_id`))) = 1) u ON u.`system_id` = s.`id`
  SET s.`club_id` = u.`club_id`, s.`league_id` = u.`league_id`
  WHERE s.`club_id` IS NULL AND s.`league_id` IS NULL AND (s.`flags` & 1) = 0;

DROP TEMPORARY TABLE `_system_users`;

-- Every club gets a default gaining system: a copy of the site's.
UPDATE `clubs` SET `gaining_id` = (SELECT `id` FROM `gainings` WHERE `club_id` IS NULL AND `league_id` IS NULL ORDER BY (`flags` & 1) DESC, `id` LIMIT 1);

-- Gaining systems of the clubs.
SET @first = (SELECT MAX(`id`) FROM `gainings`);

INSERT INTO `gainings` (`club_id`, `league_id`, `name`, `version`, `flags`)
  SELECT c.`id`, NULL, s.`name`, NULL, 0 FROM `clubs` c JOIN `gainings` s ON s.`id` = c.`gaining_id`
  WHERE NOT (s.`club_id` <=> c.`id`)
  ORDER BY c.`id`;

INSERT INTO `gaining_versions` (`gaining_id`, `version`, `gaining`, `functions`)
  SELECT n.`id`, 1, v.`gaining`, v.`functions` FROM `gainings` n
  JOIN `clubs` c ON c.`id` = n.`club_id`
  JOIN `gainings` s ON s.`id` = c.`gaining_id`
  JOIN `gaining_versions` v ON v.`gaining_id` = s.`id` AND v.`version` = s.`version`
  WHERE n.`id` > @first;

UPDATE `gainings` n JOIN `gaining_versions` v ON v.`gaining_id` = n.`id` SET n.`version` = 1 WHERE n.`id` > @first;

UPDATE `clubs` c JOIN `gainings` n ON n.`club_id` = c.`id` AND n.`id` > @first SET c.`gaining_id` = n.`id`;

-- The foreign key comes only now: MySQL 8 does not let a column in a foreign key be modified.
ALTER TABLE `clubs` MODIFY `gaining_id` INT(11) NOT NULL;
ALTER TABLE `clubs` ADD CONSTRAINT `club_gaining` FOREIGN KEY (`gaining_id`) REFERENCES `gainings` (`id`);

-- Gaining systems of the leagues.
SET @first = (SELECT MAX(`id`) FROM `gainings`);

INSERT INTO `gainings` (`club_id`, `league_id`, `name`, `version`, `flags`)
  SELECT NULL, l.`id`, s.`name`, NULL, 0 FROM `leagues` l JOIN `gainings` s ON s.`id` = l.`gaining_id`
  WHERE NOT (s.`league_id` <=> l.`id`)
  ORDER BY l.`id`;

INSERT INTO `gaining_versions` (`gaining_id`, `version`, `gaining`, `functions`)
  SELECT n.`id`, 1, v.`gaining`, v.`functions` FROM `gainings` n
  JOIN `leagues` l ON l.`id` = n.`league_id`
  JOIN `gainings` s ON s.`id` = l.`gaining_id`
  JOIN `gaining_versions` v ON v.`gaining_id` = s.`id` AND v.`version` = s.`version`
  WHERE n.`id` > @first;

UPDATE `gainings` n JOIN `gaining_versions` v ON v.`gaining_id` = n.`id` SET n.`version` = 1 WHERE n.`id` > @first;

UPDATE `leagues` l JOIN `gainings` n ON n.`league_id` = l.`id` AND n.`id` > @first SET l.`gaining_id` = n.`id`;
