-- Named rules of a club, of a league and of the site in one table, the way the scoring systems
-- are kept: club_id for a club, league_id for a league, neither for the site.
--
-- The rules of a club and of the site are rules codes. The rules of a league are filters: they
-- are not played as they are, but put over the default rules of the club that picks them - see
-- get_available_rules() in include/rules.php.
--
-- The default rules of a club are still clubs.rules and those of a league still leagues.rules,
-- its filter. The site has nowhere else to keep its own, so they are the row of the site with
-- an empty name. That row is what a new club starts from.
--
-- A league no longer has a rules code of its own (leagues.default_rules), and a club no longer
-- has rules of its own in each league it belongs to (league_clubs.rules): picking a league's
-- rules is the club's rules put through the league's filter.

CREATE TABLE `rules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NULL,
  `league_id` INT(11) NULL,
  `name` VARCHAR(128) NOT NULL,
  `rules` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`club_id`),
  CONSTRAINT `rules_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  KEY (`league_id`),
  CONSTRAINT `rules_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `rules` (`id`, `club_id`, `league_id`, `name`, `rules`)
  SELECT `id`, `club_id`, NULL, `name`, `rules` FROM `club_rules`;

INSERT INTO `rules` (`club_id`, `league_id`, `name`, `rules`) VALUES (NULL, NULL, '', '0002000010000');

DROP TABLE `club_rules`;

ALTER TABLE `leagues` DROP COLUMN `default_rules`;

ALTER TABLE `league_clubs` DROP COLUMN `rules`;
