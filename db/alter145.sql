ALTER TABLE `events` ADD COLUMN `round` INT(11) NOT NULL;
UPDATE events SET flags = flags & ~512 WHERE id = 9841;
UPDATE events SET round = 1 WHERE (flags & 512) <> 0;
UPDATE events SET round = 2 WHERE id IN (8827, 8774, 8708, 8595, 8519, 8516, 8514, 8512);
UPDATE events SET flags = flags & ~512;
