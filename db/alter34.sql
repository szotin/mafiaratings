use mafia;

/*ALTER TABLE games
  ADD COLUMN best_player_id INT(11) NULL;

ALTER TABLE `games`
  ADD INDEX `game_best_player` (`best_player_id`, `club_id`);

ALTER TABLE `games`
  ADD CONSTRAINT `game_best_player` FOREIGN KEY (`best_player_id`) REFERENCES `users` (`id`);*/



-- Re-enabled: alter47 and later migrations depend on players.won existing.
-- (The best_player_id block above stays disabled because alter33 already adds it.)
ALTER TABLE players
  ADD COLUMN won TINYINT(1) NOT NULL;

UPDATE players SET won = IF(rating > 0, 1, 0);

ALTER TABLE players
  DROP COLUMN announced_sheriff;

ALTER TABLE players
  DROP COLUMN sheriff_status;
