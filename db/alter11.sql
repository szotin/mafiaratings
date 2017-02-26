use mafia;

ALTER TABLE users
  ADD COLUMN `flags` INT(11) NOT NULL;

UPDATE users SET flags = (flags | 1) WHERE is_player = true;
UPDATE users SET flags = (flags | 2) WHERE is_moderator = true;
UPDATE users SET flags = (flags | 4) WHERE is_supervisor = true;
UPDATE users SET flags = (flags | 8) WHERE is_admin = true;
UPDATE users SET flags = (flags | 32) WHERE is_male = true;
UPDATE users SET flags = (flags | 64) WHERE is_subscribed = true;
UPDATE users SET flags = (flags | 128) WHERE is_banned = true;

ALTER TABLE users
  DROP COLUMN is_player;
ALTER TABLE users
  DROP COLUMN is_moderator;
ALTER TABLE users
  DROP COLUMN is_supervisor;
ALTER TABLE users
  DROP COLUMN is_admin;
ALTER TABLE users
  DROP COLUMN is_male;
ALTER TABLE users
  DROP COLUMN is_subscribed;
ALTER TABLE users
  DROP COLUMN is_banned;
