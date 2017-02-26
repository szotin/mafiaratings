use mafia;

ALTER TABLE game_settings
  ADD COLUMN `l_autosave` INT(11) NOT NULL;

ALTER TABLE game_settings
  ADD COLUMN `g_autosave` INT(11) NOT NULL;

UPDATE game_settings SET l_autosave = autosave, g_autosave = 60;
UPDATE game_settings SET l_autosave = 10 WHERE l_autosave = 60;

ALTER TABLE game_settings
  DROP COLUMN `autosave`;
