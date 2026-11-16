-- Address names become localizable, the same way as the names of clubs, leagues, events, tournaments,
-- series, rules, systems and tournament schemas (alter217.sql - alter225.sql): an address refers to a
-- set of names in "names", and every query joins it for the language it shows the address in. The
-- address itself - the street and the house - stays as it is.
--
-- The addresses with the same name share one set rather than each getting a copy of it; names are
-- told apart by case (BINARY) and taken without the spaces around them. A name stays unique among the
-- addresses of its club, now in every language it is for, which a key can not say any more: the code
-- checks it. The unique (club_id, name) key goes, and a plain key on club_id takes its place under the
-- foreign key to the club.

ALTER TABLE `addresses` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `id`;

CREATE TEMPORARY TABLE `address_names` (
  `name_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO address_names (name) SELECT DISTINCT BINARY TRIM(name) FROM addresses;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
SET @n = 0;
UPDATE address_names SET name_id = @base + (@n := @n + 1) ORDER BY name;

INSERT INTO names (id, langs, name) SELECT name_id, 16777215, name FROM address_names;
UPDATE addresses a JOIN address_names an ON an.name = TRIM(a.name) COLLATE utf8_bin SET a.name_id = an.name_id;

DROP TEMPORARY TABLE `address_names`;

ALTER TABLE `addresses` ADD KEY `club_id` (`club_id`);
ALTER TABLE `addresses` DROP INDEX `address_club`;
ALTER TABLE `addresses` DROP COLUMN `name`;

ALTER TABLE `addresses` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `addresses` ADD KEY `name_id` (`name_id`);
ALTER TABLE `addresses` ADD CONSTRAINT `address_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
