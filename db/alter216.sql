-- The tournament schemas of a league: ready-made ways to set a tournament up - its rounds and the
-- weight of their points, the rules, the scoring system with its options and the normalizer, the
-- special awards, the hiding of the table, and whether it is a long-term one. A tournament takes
-- one on the Scheme tab of its preparation page; what it takes is the content, see
-- include/tournament_schema.php, and nothing remembers which schema it was. What a schema refers
-- to - its rules, scoring system and normalizer - are columns with foreign keys, so that they can
-- not be deleted while a schema uses them; the rest of it is the json in "scheme".
CREATE TABLE `league_tournament_schemas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `league_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `scheme` TEXT NOT NULL,
  `rules_id` INT(11) NULL,
  `rules_league_id` INT(11) NULL,
  `scoring_id` INT(11) NOT NULL,
  `normalizer_id` INT(11) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `league_name` (`league_id`, `name`),
  KEY `tournament_schema_rules` (`rules_id`),
  KEY `tournament_schema_rules_league` (`rules_league_id`),
  KEY `tournament_schema_scoring` (`scoring_id`),
  KEY `tournament_schema_normalizer` (`normalizer_id`),
  CONSTRAINT `tournament_schema_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  CONSTRAINT `tournament_schema_rules` FOREIGN KEY (`rules_id`) REFERENCES `rules` (`id`),
  CONSTRAINT `tournament_schema_rules_league` FOREIGN KEY (`rules_league_id`) REFERENCES `leagues` (`id`),
  CONSTRAINT `tournament_schema_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`),
  CONSTRAINT `tournament_schema_normalizer` FOREIGN KEY (`normalizer_id`) REFERENCES `normalizers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- The types the rounds of a tournament used to be made by. The schemas take their place.
ALTER TABLE `tournaments` DROP COLUMN `type`;
