use mafia;

CREATE TABLE `rounds` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `event_id` INT(11) NOT NULL,
  `sort_order` INT(11) NOT NULL,
  `scoring_id` INT(11) NOT NULL,
  `scoring_weight` FLOAT NOT NULL,

  PRIMARY KEY (`id`),
  KEY(`event_id`, `sort_order`),
  CONSTRAINT `round_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY(`scoring_id`),
  CONSTRAINT `round_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `games` ADD COLUMN `round_id` INT(11) NULL;
ALTER TABLE `games` ADD KEY(`round_id`, `end_time`);
ALTER TABLE `games` ADD CONSTRAINT `game_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`);

ALTER TABLE `games` ADD KEY(`club_id`, `end_time`);
ALTER TABLE `games` ADD KEY(`moderator_id`, `end_time`);
ALTER TABLE `games` ADD KEY(`event_id`, `end_time`);
ALTER TABLE `games` ADD KEY(`user_id`, `end_time`);
ALTER TABLE `games` ADD KEY(`best_player_id`, `end_time`);

ALTER TABLE `games` DROP INDEX `game_club_result`;
ALTER TABLE `games` DROP INDEX `game_moderator_result`;
ALTER TABLE `games` DROP INDEX `game_event_id`;
ALTER TABLE `games` DROP INDEX `user_id`;
ALTER TABLE `games` DROP INDEX `game_best_player`;

ALTER TABLE `events` ADD COLUMN `round_id` INT(11) NULL;
ALTER TABLE `events` ADD KEY(`round_id`);
ALTER TABLE `events` ADD CONSTRAINT `event_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`);

ALTER TABLE `events` ADD COLUMN `scoring_weight` FLOAT NOT NULL DEFAULT 1;

