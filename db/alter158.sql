ALTER TABLE game_settings
   ADD COLUMN `feature_flags` INT(11) NOT NULL;
   
UPDATE game_settings SET feature_flags = 49151;
