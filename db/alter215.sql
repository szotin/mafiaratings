-- The system of a club: the league whose defaults - rules, scoring system, normalizer and gaining
-- system - the club takes, see op create and op change in api/ops/club.php. NULL is none: the
-- defaults of the site, or of the parent club for a sub-club. Kept so that the club form shows
-- what was picked; the defaults are taken again only when it changes.

ALTER TABLE `clubs` ADD COLUMN `system_league_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `clubs` ADD KEY `system_league_id` (`system_league_id`);
ALTER TABLE `clubs` ADD CONSTRAINT `club_system_league` FOREIGN KEY (`system_league_id`) REFERENCES `leagues` (`id`);

-- An existing club gets as its system the league it is a member of - a membership still waiting to
-- be accepted counts too. A member of several gets the first of: iMafia (6), Mafclub (4), CLM (7),
-- ФИИМ (1), American Mafia League (2). A club in none of these has no system. Only the system is
-- recorded: the defaults the club has stay as they are.
UPDATE `clubs` c SET c.`system_league_id` = (
  SELECT lc.`league_id` FROM `league_clubs` lc
  WHERE lc.`club_id` = c.`id` AND lc.`league_id` IN (6, 4, 7, 1, 2)
  ORDER BY FIELD(lc.`league_id`, 6, 4, 7, 1, 2) LIMIT 1);
