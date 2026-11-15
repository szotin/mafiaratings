-- Club names become localizable, the same way as the names of users, cities and countries: the
-- club refers to a set of names in "names" - one per language, the one with the most languages
-- being the default - and every query joins it for the language it shows the club in.
--
-- Every club gets its own set holding its current name for all languages. The ids are taken right
-- after the last one in "names", so that each club knows its set without matching by name.
-- Name sets are shared by whoever has the same names (see Names::get_id in include/names.php),
-- which is why name_id is not unique; uniqueness of club names is checked by Names when one is set.

ALTER TABLE `clubs` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `id`;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
INSERT INTO names (id, langs, name) SELECT @base + id, 16777215, name FROM clubs;
UPDATE clubs SET name_id = @base + id;

-- Dropping the column also takes it out of the index (parent_id, name), which stays as the index
-- of the parent_club foreign key.
ALTER TABLE `clubs` DROP INDEX `club_name`;
ALTER TABLE `clubs` DROP COLUMN `name`;

ALTER TABLE `clubs` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `clubs` ADD KEY `name_id` (`name_id`);
ALTER TABLE `clubs` ADD CONSTRAINT `club_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
