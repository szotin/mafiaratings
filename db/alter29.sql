use mafia;

ALTER TABLE games
  ADD COLUMN user_id INT(11) NOT NULL;

UPDATE games SET user_id = moderator_id;

ALTER TABLE games
  ADD KEY(user_id);

ALTER TABLE games
   ADD CONSTRAINT `game_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
