-- League names become localizable, the same way as club names (alter217.sql): the league refers to
-- a set of names in "names", and every query joins it for the language it shows the league in.
--
-- Every league gets its own set holding its current name for all languages, with ids right after
-- the last one in "names". A league request keeps its name as plain text: it is not a league yet,
-- and the set of names is made from it when the request is accepted.

ALTER TABLE `leagues` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `id`;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
INSERT INTO names (id, langs, name) SELECT @base + id, 16777215, name FROM leagues;
UPDATE leagues SET name_id = @base + id;

ALTER TABLE `leagues` DROP INDEX `name`;
ALTER TABLE `leagues` DROP COLUMN `name`;

ALTER TABLE `leagues` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `leagues` ADD KEY `name_id` (`name_id`);
ALTER TABLE `leagues` ADD CONSTRAINT `league_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
