use mafia;

CREATE TABLE `country_names` (
  `country_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,

  PRIMARY KEY (`country_id`, `name`),
  KEY(`name`),
  CONSTRAINT `country_names_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `city_names` (
  `city_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,

  PRIMARY KEY (`city_id`, `name`),
  KEY(`name`),
  CONSTRAINT `city_names_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO country_names (country_id, name) SELECT id, name_en FROM countries;
INSERT INTO country_names (country_id, name) SELECT id, name_ru FROM countries WHERE name_ru <> name_en;
INSERT INTO city_names (city_id, name) SELECT id, name_en FROM cities;
INSERT INTO city_names (city_id, name) SELECT id, name_ru FROM cities WHERE name_ru <> name_en;