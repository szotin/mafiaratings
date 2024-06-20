ALTER TABLE events ADD COLUMN seating TEXT NULL;
ALTER TABLE `games` CHANGE COLUMN `table_name` `game_table` INT(11) NULL;
