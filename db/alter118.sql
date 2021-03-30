ALTER TABLE games ADD COLUMN json TEXT NOT NULL;

CREATE TABLE `game_issues` (
  `game_id` INT(11) NOT NULL,
  `json` TEXT,
  `issues` TEXT,

  PRIMARY KEY (`game_id`),
  CONSTRAINT `issue_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE games ADD COLUMN feature_flags INT(11) NOT NULL;
ALTER TABLE games ADD COLUMN as_is BOOLEAN NOT NULL;
