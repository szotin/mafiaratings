use mafia;

ALTER TABLE games
  ADD COLUMN best_player_id INT(11) NULL;

ALTER TABLE `games`
  ADD INDEX `game_best_player` (`best_player_id`, `club_id`);

ALTER TABLE `games`
  ADD CONSTRAINT `game_best_player` FOREIGN KEY (`best_player_id`) REFERENCES `users` (`id`);

