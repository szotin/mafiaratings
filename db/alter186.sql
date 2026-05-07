ALTER TABLE `tournaments`
  ADD COLUMN `team_size` INT NOT NULL DEFAULT 1;

UPDATE `tournaments` SET `team_size` = 2, flags = `flags` & ~256 WHERE (`flags` & 256) != 0;
