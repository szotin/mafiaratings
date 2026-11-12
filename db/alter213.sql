-- The default scoring system and the default scoring normalizer of a club or of a league are its
-- own, the way its default rules are: clubs.scoring_id and leagues.scoring_id (normalizer_id
-- likewise) point to a system that belongs to the club or the league, and it is edited in place.
-- Whoever used a system of someone else - the site's, a league's, another club's - gets a copy of
-- the version they were using, under the same name. New versions of the original do not reach
-- the copies.
--
-- The site's own defaults are its systems with flag 1 (the site has no row to point from), see
-- get_site_scoring_id() and get_site_normalizer_id() in include/scoring.php. The default scoring
-- of the site has been hardcoded to 19 so far; the site has had no default normalizer, and a club
-- or a league without one did not normalize - now there is one, see below.

ALTER TABLE `scorings` ADD COLUMN `flags` INT(11) NOT NULL DEFAULT 0;
ALTER TABLE `normalizers` ADD COLUMN `flags` INT(11) NOT NULL DEFAULT 0;

UPDATE `scorings` SET `flags` = 1 WHERE `id` = 19 AND `club_id` IS NULL AND `league_id` IS NULL;

-- A system of the site called by the name of a league is that league's system: it goes to the
-- league with all its versions, so the tournaments that used it keep using it. If it was the
-- default system of the site, the site keeps a copy of its current version as its default,
-- called by the name of the site.
SET @first = (SELECT MAX(`id`) FROM `scorings`);

INSERT INTO `scorings` (`club_id`, `league_id`, `name`, `version`, `flags`)
  SELECT NULL, NULL, 'Mafia Ratings', NULL, 1 FROM `scorings` s JOIN `leagues` l ON l.`name` = s.`name`
  WHERE s.`club_id` IS NULL AND s.`league_id` IS NULL AND (s.`flags` & 1) <> 0;

INSERT INTO `scoring_versions` (`scoring_id`, `version`, `scoring`, `functions`)
  SELECT n.`id`, 1, v.`scoring`, v.`functions` FROM `scorings` n
  JOIN `scorings` s ON s.`club_id` IS NULL AND s.`league_id` IS NULL AND (s.`flags` & 1) <> 0 AND s.`id` < n.`id`
  JOIN `scoring_versions` v ON v.`scoring_id` = s.`id` AND v.`version` = s.`version`
  WHERE n.`id` > @first;

UPDATE `scorings` n JOIN `scoring_versions` v ON v.`scoring_id` = n.`id` SET n.`version` = 1 WHERE n.`id` > @first;

UPDATE `scorings` SET `flags` = 0 WHERE `id` <= @first AND `club_id` IS NULL AND `league_id` IS NULL AND (`flags` & 1) <> 0 AND EXISTS (SELECT 1 FROM (SELECT `id` FROM `scorings` WHERE `id` > @first) x);

UPDATE `scorings` s JOIN `leagues` l ON l.`name` = s.`name` SET s.`league_id` = l.`id`
  WHERE s.`club_id` IS NULL AND s.`league_id` IS NULL AND (s.`flags` & 1) = 0;

UPDATE `normalizers` s JOIN `leagues` l ON l.`name` = s.`name` SET s.`league_id` = l.`id`
  WHERE s.`club_id` IS NULL AND s.`league_id` IS NULL;

-- A system of the site that only one club uses - in its tournaments, its events, as its default -
-- and no league uses as its default is that club's: it goes to the club with all its versions,
-- the same way as above. The ones several clubs use stay with the site. This has to come before
-- the copies below, so that such a club gets the system itself rather than a copy of it.
CREATE TEMPORARY TABLE `_system_users` (`system_id` INT NOT NULL, `club_id` INT NOT NULL);

INSERT INTO `_system_users` SELECT `scoring_id`, `club_id` FROM `tournaments` WHERE `scoring_id` IS NOT NULL;
INSERT INTO `_system_users` SELECT `scoring_id`, `club_id` FROM `events` WHERE `scoring_id` IS NOT NULL;
INSERT INTO `_system_users` SELECT `scoring_id`, `id` FROM `clubs` WHERE `scoring_id` IS NOT NULL;

UPDATE `scorings` s
  JOIN (SELECT `system_id`, MIN(`club_id`) AS `club_id` FROM `_system_users` GROUP BY `system_id` HAVING COUNT(DISTINCT `club_id`) = 1) u ON u.`system_id` = s.`id`
  SET s.`club_id` = u.`club_id`
  WHERE s.`club_id` IS NULL AND s.`league_id` IS NULL AND (s.`flags` & 1) = 0
  AND NOT EXISTS (SELECT 1 FROM `leagues` l WHERE l.`scoring_id` = s.`id`);

DELETE FROM `_system_users`;

INSERT INTO `_system_users` SELECT `normalizer_id`, `club_id` FROM `tournaments` WHERE `normalizer_id` IS NOT NULL;
INSERT INTO `_system_users` SELECT `normalizer_id`, `id` FROM `clubs` WHERE `normalizer_id` IS NOT NULL;

UPDATE `normalizers` s
  JOIN (SELECT `system_id`, MIN(`club_id`) AS `club_id` FROM `_system_users` GROUP BY `system_id` HAVING COUNT(DISTINCT `club_id`) = 1) u ON u.`system_id` = s.`id`
  SET s.`club_id` = u.`club_id`
  WHERE s.`club_id` IS NULL AND s.`league_id` IS NULL AND (s.`flags` & 1) = 0
  AND NOT EXISTS (SELECT 1 FROM `leagues` l WHERE l.`normalizer_id` = s.`id`);

DROP TEMPORARY TABLE `_system_users`;

-- Scoring systems of the clubs.
SET @first = (SELECT MAX(`id`) FROM `scorings`);

INSERT INTO `scorings` (`club_id`, `league_id`, `name`, `version`, `flags`)
  SELECT c.`id`, NULL, s.`name`, NULL, 0 FROM `clubs` c JOIN `scorings` s ON s.`id` = c.`scoring_id`
  WHERE NOT (s.`club_id` <=> c.`id`)
  ORDER BY c.`id`;

INSERT INTO `scoring_versions` (`scoring_id`, `version`, `scoring`, `functions`)
  SELECT n.`id`, 1, v.`scoring`, v.`functions` FROM `scorings` n
  JOIN `clubs` c ON c.`id` = n.`club_id`
  JOIN `scorings` s ON s.`id` = c.`scoring_id`
  JOIN `scoring_versions` v ON v.`scoring_id` = s.`id` AND v.`version` = s.`version`
  WHERE n.`id` > @first;

UPDATE `scorings` n JOIN `scoring_versions` v ON v.`scoring_id` = n.`id` SET n.`version` = 1 WHERE n.`id` > @first;

UPDATE `clubs` c JOIN `scorings` n ON n.`club_id` = c.`id` AND n.`id` > @first SET c.`scoring_id` = n.`id`;

-- Scoring systems of the leagues.
SET @first = (SELECT MAX(`id`) FROM `scorings`);

INSERT INTO `scorings` (`club_id`, `league_id`, `name`, `version`, `flags`)
  SELECT NULL, l.`id`, s.`name`, NULL, 0 FROM `leagues` l JOIN `scorings` s ON s.`id` = l.`scoring_id`
  WHERE NOT (s.`league_id` <=> l.`id`)
  ORDER BY l.`id`;

INSERT INTO `scoring_versions` (`scoring_id`, `version`, `scoring`, `functions`)
  SELECT n.`id`, 1, v.`scoring`, v.`functions` FROM `scorings` n
  JOIN `leagues` l ON l.`id` = n.`league_id`
  JOIN `scorings` s ON s.`id` = l.`scoring_id`
  JOIN `scoring_versions` v ON v.`scoring_id` = s.`id` AND v.`version` = s.`version`
  WHERE n.`id` > @first;

UPDATE `scorings` n JOIN `scoring_versions` v ON v.`scoring_id` = n.`id` SET n.`version` = 1 WHERE n.`id` > @first;

UPDATE `leagues` l JOIN `scorings` n ON n.`league_id` = l.`id` AND n.`id` > @first SET l.`scoring_id` = n.`id`;

-- The default normalizer of the site. Every club and league has a default normalizer, the way it
-- has a default scoring system: the ones that had none get a copy of this one below. Going without
-- normalization is chosen for a tournament, not for its club or league.
INSERT INTO `normalizers` (`club_id`, `league_id`, `name`, `version`, `flags`) VALUES (NULL, NULL, 'Mafia Ratings', NULL, 1);
SET @site_normalizer = LAST_INSERT_ID();
INSERT INTO `normalizer_versions` (`normalizer_id`, `version`, `normalizer`) VALUES (@site_normalizer, 1, '{"policies":[{"gameAv":{"minPercOfMax":50,"min":20}}]}');
UPDATE `normalizers` SET `version` = 1 WHERE `id` = @site_normalizer;

UPDATE `clubs` SET `normalizer_id` = @site_normalizer WHERE `normalizer_id` IS NULL;
UPDATE `leagues` SET `normalizer_id` = @site_normalizer WHERE `normalizer_id` IS NULL;

-- Normalizers of the clubs.
SET @first = (SELECT IFNULL(MAX(`id`), 0) FROM `normalizers`);

INSERT INTO `normalizers` (`club_id`, `league_id`, `name`, `version`, `flags`)
  SELECT c.`id`, NULL, s.`name`, NULL, 0 FROM `clubs` c JOIN `normalizers` s ON s.`id` = c.`normalizer_id`
  WHERE NOT (s.`club_id` <=> c.`id`)
  ORDER BY c.`id`;

INSERT INTO `normalizer_versions` (`normalizer_id`, `version`, `normalizer`)
  SELECT n.`id`, 1, v.`normalizer` FROM `normalizers` n
  JOIN `clubs` c ON c.`id` = n.`club_id`
  JOIN `normalizers` s ON s.`id` = c.`normalizer_id`
  JOIN `normalizer_versions` v ON v.`normalizer_id` = s.`id` AND v.`version` = s.`version`
  WHERE n.`id` > @first;

UPDATE `normalizers` n JOIN `normalizer_versions` v ON v.`normalizer_id` = n.`id` SET n.`version` = 1 WHERE n.`id` > @first;

UPDATE `clubs` c JOIN `normalizers` n ON n.`club_id` = c.`id` AND n.`id` > @first SET c.`normalizer_id` = n.`id`;

-- Normalizers of the leagues.
SET @first = (SELECT IFNULL(MAX(`id`), 0) FROM `normalizers`);

INSERT INTO `normalizers` (`club_id`, `league_id`, `name`, `version`, `flags`)
  SELECT NULL, l.`id`, s.`name`, NULL, 0 FROM `leagues` l JOIN `normalizers` s ON s.`id` = l.`normalizer_id`
  WHERE NOT (s.`league_id` <=> l.`id`)
  ORDER BY l.`id`;

INSERT INTO `normalizer_versions` (`normalizer_id`, `version`, `normalizer`)
  SELECT n.`id`, 1, v.`normalizer` FROM `normalizers` n
  JOIN `leagues` l ON l.`id` = n.`league_id`
  JOIN `normalizers` s ON s.`id` = l.`normalizer_id`
  JOIN `normalizer_versions` v ON v.`normalizer_id` = s.`id` AND v.`version` = s.`version`
  WHERE n.`id` > @first;

UPDATE `normalizers` n JOIN `normalizer_versions` v ON v.`normalizer_id` = n.`id` SET n.`version` = 1 WHERE n.`id` > @first;

UPDATE `leagues` l JOIN `normalizers` n ON n.`league_id` = l.`id` AND n.`id` > @first SET l.`normalizer_id` = n.`id`;
