use mafia;

ALTER TABLE users
  DROP COLUMN last_game_id;

ALTER TABLE users
  ADD COLUMN rank VARCHAR(24) NOT NULL;

