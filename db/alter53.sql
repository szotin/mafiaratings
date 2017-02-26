use mafia;

ALTER TABLE `incomers`
  ADD COLUMN `flags` INT(11) NULL;

UPDATE events SET flags = flags | 16 WHERE start_time + duration + 28800 < UNIX_TIMESTAMP();
