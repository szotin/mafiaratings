ALTER TABLE `games` CHANGE COLUMN `canceled` `is_canceled` TINYINT(1) NOT NULL;
ALTER TABLE `games` CHANGE COLUMN `non_rating` `is_rating` TINYINT(1) NOT NULL;
UPDATE `games` SET is_rating = IF(is_rating, 0, 1);
