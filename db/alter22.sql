use mafia;

-- Drop the FK added in alter1 before dropping its column, or modern MySQL refuses.
ALTER TABLE users
  DROP FOREIGN KEY user_last_game;

ALTER TABLE users
  DROP COLUMN last_game_id;

ALTER TABLE users
  ADD COLUMN rank VARCHAR(24) NOT NULL;

