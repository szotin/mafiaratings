// ALTER TABLE users DROP INDEX user_name;
// CREATE UNIQUE INDEX user_name ON users (name, city_id);

CREATE TABLE `names` (
`id` INT(11) NOT NULL AUTO_INCREMENT,
`langs` INT(11) NOT NULL DEFAULT X'FFFFFF',
`name` VARCHAR(256) NOT NULL,
`name_ru` VARCHAR(256) NOT NULL,

PRIMARY KEY (`id`, `langs`),
KEY(`name`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO names (name, name_ru) SELECT name_en, name_ru FROM countries;
INSERT INTO names (name, name_ru) SELECT name_en, name_ru FROM cities;
INSERT INTO names (id, langs, name, name_ru) SELECT id, 2, name_ru, name_ru FROM names WHERE name <> name_ru;
UPDATE names SET langs = langs & ~2 WHERE name <> name_ru;

ALTER TABLE `countries` ADD COLUMN `name_id` INT(11) NOT NULL;
ALTER TABLE `cities` ADD COLUMN `name_id` INT(11) NOT NULL;

UPDATE countries c SET c.name_id = (SELECT n.id FROM names n WHERE n.name = c.name_en AND n.name_ru = c.name_ru);
UPDATE cities c SET c.name_id = (SELECT n.id FROM names n WHERE n.name = c.name_en AND n.name_ru = c.name_ru);

ALTER TABLE `countries` ADD UNIQUE KEY (`name_id`);
ALTER TABLE `countries` ADD CONSTRAINT `country_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
ALTER TABLE `cities` ADD UNIQUE KEY (`name_id`);
ALTER TABLE `cities` ADD CONSTRAINT `city_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);

ALTER TABLE `names` DROP COLUMN `name_ru`;
ALTER TABLE `countries` DROP COLUMN `name_en`;
ALTER TABLE `countries` DROP COLUMN `name_ru`;
ALTER TABLE `cities` DROP COLUMN `name_en`;
ALTER TABLE `cities` DROP COLUMN `name_ru`;

ALTER TABLE country_names DROP INDEX name;
ALTER TABLE country_names ADD UNIQUE KEY (name);

ALTER TABLE city_names DROP INDEX name;
ALTER TABLE city_names ADD UNIQUE KEY (name);
