use mafia;

ALTER TABLE users
   ADD COLUMN `last_game_id` INT(11) NULL;

ALTER TABLE users
   ADD KEY `user_last_game` (`last_game_id`);

ALTER TABLE users
   ADD CONSTRAINT `user_last_game` FOREIGN KEY (`last_game_id`) REFERENCES `games` (`id`);

UPDATE users u SET last_game_id = (SELECT max(g.id) FROM games g, players p WHERE p.game_id = g.id AND p.user_id = u.id);