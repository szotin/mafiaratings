use mafia;

ALTER TABLE games ADD COLUMN `table_name` VARCHAR(32) NULL;
ALTER TABLE games ADD COLUMN `game_number` INT(11) NULL;
ALTER TABLE games ADD COLUMN `objection_user_id` INT(11) NULL;
ALTER TABLE games ADD COLUMN `objection` TEXT NULL;

ALTER TABLE games ADD KEY (objection_user_id);
ALTER TABLE games ADD CONSTRAINT objection_user FOREIGN KEY(objection_user_id) REFERENCES users(id);

ALTER TABLE players CHANGE extra_points_reason extra_points_reason TEXT NULL;