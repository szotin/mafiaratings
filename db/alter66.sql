use mafia;

ALTER TABLE events
  ADD COLUMN `standings_settings` VARCHAR(256) NULL;
