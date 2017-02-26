use mafia;

CREATE TABLE `ratings` (
  `user_id` INT(11) NOT NULL,
  `role` TINYINT(1) NOT NULL, -- 0 all; 1 red; 2 dark; 3 civil; 4 sheriff; 5 mafia; 6 don
  `rating` INT(11) NOT NULL,
  `games` INT(11) NOT NULL,
  `games_won` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `role`),
  KEY (role, rating, games DESC, games_won),
  CONSTRAINT `rating_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO ratings (user_id, role, rating, games, games_won)
  SELECT id, 0, rating, mafia_games + civil_games + sheriff_games + don_games, mafia_games_won + civil_games_won + sheriff_games_won + don_games_won
  FROM users WHERE (mafia_games + civil_games + sheriff_games + don_games) <> 0;

INSERT INTO ratings (user_id, role, rating, games, games_won)
  SELECT id, 1, civil_rating + sheriff_rating, civil_games + sheriff_games, civil_games_won + sheriff_games_won
  FROM users WHERE (civil_games + sheriff_games) <> 0;

INSERT INTO ratings (user_id, role, rating, games, games_won)
  SELECT id, 2, mafia_rating + don_rating, mafia_games + don_games, mafia_games_won + don_games_won
  FROM users WHERE (mafia_games + don_games) <> 0;

INSERT INTO ratings (user_id, role, rating, games, games_won)
  SELECT id, 3, civil_rating, civil_games, civil_games_won
  FROM users WHERE civil_games <> 0;

INSERT INTO ratings (user_id, role, rating, games, games_won)
  SELECT id, 4, sheriff_rating, sheriff_games, sheriff_games_won
  FROM users WHERE sheriff_games <> 0;

INSERT INTO ratings (user_id, role, rating, games, games_won)
  SELECT id, 5, mafia_rating, mafia_games, mafia_games_won
  FROM users WHERE mafia_games <> 0;

INSERT INTO ratings (user_id, role, rating, games, games_won)
  SELECT id, 6, don_rating, don_games, don_games_won
  FROM users WHERE don_games <> 0;

ALTER TABLE users
  DROP COLUMN rating;

ALTER TABLE users
  DROP COLUMN mafia_rating;

ALTER TABLE users
  DROP COLUMN civil_rating;

ALTER TABLE users
  DROP COLUMN don_rating;

ALTER TABLE users
  DROP COLUMN sheriff_rating;

ALTER TABLE users
  DROP COLUMN mafia_games;
  
ALTER TABLE users
  DROP COLUMN civil_games;

ALTER TABLE users
  DROP COLUMN don_games;
  
ALTER TABLE users
  DROP COLUMN sheriff_games;

ALTER TABLE users
  DROP COLUMN mafia_games_won;
  
ALTER TABLE users
  DROP COLUMN civil_games_won;
  
ALTER TABLE users
  DROP COLUMN don_games_won;
  
ALTER TABLE users
  DROP COLUMN sheriff_games_won;


