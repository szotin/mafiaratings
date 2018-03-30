use mafia;

ALTER TABLE players
  DROP COLUMN club_points;

ALTER TABLE players
  DROP COLUMN event_points;
  
UPDATE scoring_points SET points = points * 100 WHERE scoring_id IN (5, 6);

CREATE TABLE `scoring_rules` (
  `scoring_id` INT(11) NOT NULL,
  `category` INT(5) NOT NULL,
  `matter` INT(11) NOT NULL,
  `roles` INT(5) NOT NULL,
  `policy` INT(5) NOT NULL,
  `min_dependency` FLOAT NOT NULL,
  `min_points` FLOAT NOT NULL,
  `max_dependency` FLOAT NOT NULL,
  `max_points` FLOAT NOT NULL,

  PRIMARY KEY (`scoring_id`, `category`, `matter`, `roles`),
  CONSTRAINT `scoring_rules_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT into scoring_rules (scoring_id, category, matter, roles, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 1, 1, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 1;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 1, 2, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 2;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 1, 4, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 4;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 1, 8, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 8;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 2, 1, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 16;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 2, 2, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 32;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 2, 4, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 64;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 0, 2, 8, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 128;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 5, 15, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 256;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 6, 15, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 512;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 10, 3, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 1024;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 15, 1, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 2048;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 17, 4, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 4096;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 17, 8, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 8192;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 18, 8, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 16384;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 20, 2, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 32768;

INSERT into scoring_rules (scoring_id, matter, roles, category, policy, min_dependency, min_points, max_dependency, max_points)
SELECT scoring_id, 1, 19, 8, 0, 0, points / 100, 0, points / 100 FROM scoring_points WHERE flag = 65536;

DROP table scoring_points;

ALTER TABLE scorings
  ADD COLUMN sorting VARCHAR(16) NOT NULL;
  
UPDATE scorings SET sorting = 'acgk';
