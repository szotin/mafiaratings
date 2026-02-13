CREATE TABLE `tournament_scores_cache` (
  `tournament_id` INT NOT NULL,
  `flags` INT NOT NULL,
  `scoring_id` INT NOT NULL,
  `scoring_version` INT NOT NULL,
  `normalizer_id` INT NOT NULL,
  `normalizer_version` INT NOT NULL,
  `scores` MEDIUMTEXT NOT NULL,

  PRIMARY KEY (`tournament_id`, `flags`, `scoring_id`, `scoring_version`, `normalizer_id`, `normalizer_version`),
  CONSTRAINT `tournament_scores_cache_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `event_scores_cache` (
  `event_id` INT NOT NULL,
  `flags` INT NOT NULL,
  `scoring_id` INT NOT NULL,
  `scoring_version` INT NOT NULL,
  `scores` MEDIUMTEXT NOT NULL,

  PRIMARY KEY (`event_id`, `flags`, `scoring_id`, `scoring_version`),
  CONSTRAINT `event_scores_cache_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

