CREATE TABLE `tournament_teams` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` INT(11) NOT NULL,
  `name` VARCHAR(256) NOT NULL,

  PRIMARY KEY (`id`),
  KEY(`tournament_id`, `name`),
  CONSTRAINT `c_team_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `tournament_users` ADD COLUMN `team_id` INT(11) NULL;
ALTER TABLE `tournament_users` ADD KEY(`team_id`);
ALTER TABLE `tournament_users` ADD CONSTRAINT `c_user_team` FOREIGN KEY (`team_id`) REFERENCES `tournament_teams` (`id`);
