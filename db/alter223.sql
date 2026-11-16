-- Rules names become localizable, the same way as the names of clubs, leagues, events,
-- tournaments and series (alter217.sql - alter219.sql, alter221.sql, alter222.sql): the rules refer
-- to a set of names in "names", and every query joins it for the language it shows the rules in.
--
-- The default rules of the site are the row with no name. They get no set: their name_id is NULL,
-- and that is what tells them apart now, as the empty name did. The rules with the same name share
-- one set rather than each getting a copy of it; names are told apart by case (BINARY) and taken
-- without the spaces around them.

ALTER TABLE `rules` ADD COLUMN `name_id` INT(11) NULL DEFAULT NULL AFTER `league_id`;

CREATE TEMPORARY TABLE `rules_names` (
  `name_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO rules_names (name) SELECT DISTINCT BINARY TRIM(name) FROM rules WHERE TRIM(name) <> '';

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
SET @n = 0;
UPDATE rules_names SET name_id = @base + (@n := @n + 1) ORDER BY name;

INSERT INTO names (id, langs, name) SELECT name_id, 16777215, name FROM rules_names;
UPDATE rules r JOIN rules_names rn ON rn.name = TRIM(r.name) COLLATE utf8_bin SET r.name_id = rn.name_id;

DROP TEMPORARY TABLE `rules_names`;

ALTER TABLE `rules` DROP COLUMN `name`;

ALTER TABLE `rules` ADD KEY `name_id` (`name_id`);
ALTER TABLE `rules` ADD CONSTRAINT `rules_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
