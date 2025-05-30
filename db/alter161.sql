ALTER TABLE users
   ADD COLUMN `red_rating` DOUBLE NOT NULL;

ALTER TABLE users
   ADD COLUMN `black_rating` DOUBLE NOT NULL;
   
ALTER TABLE users 
	ADD KEY (red_rating);

ALTER TABLE users 
	ADD KEY (black_rating);

ALTER TABLE players
   ADD COLUMN `role_rating_before` DOUBLE NOT NULL;

ALTER TABLE players
   ADD COLUMN `rating_lock_until` INT(11) NOT NULL;

ALTER TABLE players
   ADD COLUMN `is_rating` BOOL NOT NULL;
   
UPDATE players p JOIN games g ON g.id = p.game_id SET p.is_rating = g.is_rating;