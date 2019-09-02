use mafia;

ALTER TABLE players ADD COLUMN `extra_points_reason` VARCHAR(1024) NULL;
ALTER TABLE players ADD KEY(extra_points_reason);
