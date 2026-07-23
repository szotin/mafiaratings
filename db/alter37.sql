use mafia;

CREATE TABLE `countries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name_en` VARCHAR(128) NOT NULL,
  `name_ru` VARCHAR(128) NOT NULL,
  `flags` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX (`name_en`),
  UNIQUE INDEX (`name_ru`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `cities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `country_id` INT(11) NOT NULL,
  `name_en` VARCHAR(128) NOT NULL,
  `name_ru` VARCHAR(128) NOT NULL,
  `timezone` VARCHAR(64) NOT NULL,
  `flags` INT(11) NOT NULL,
  `near_id` INT(11) NULL, -- nearest larger city; migrated to area_id and dropped in alter73

  PRIMARY KEY (`id`),
  UNIQUE INDEX (`name_en`),
  UNIQUE INDEX (`name_ru`),
  KEY (`country_id`),
  KEY `near_city` (`near_id`),
  CONSTRAINT `city_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `near_city` FOREIGN KEY (`near_id`) REFERENCES `cities` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE clubs
  ADD COLUMN city_id INT(11) NOT NULL;

ALTER TABLE addresses
  ADD COLUMN city_id INT(11) NOT NULL;

ALTER TABLE users
  ADD COLUMN city_id INT(11) NOT NULL;

INSERT INTO countries (name_en, name_ru, flags) VALUES ('Canada', 'Канада', 0);
SET @country_id = LAST_INSERT_ID();
INSERT INTO cities (country_id, name_en, name_ru, timezone, flags) VALUES (@country_id, 'Vancouver', 'Ванкувер', 'America/Vancouver', 0);
SET @city_id = LAST_INSERT_ID();
UPDATE clubs SET city_id = @city_id;
UPDATE addresses SET city_id = @city_id;
UPDATE users SET city_id = @city_id;

INSERT INTO countries (name_en, name_ru, flags) VALUES ('Russia', 'Россия', 0);
SET @country_id = LAST_INSERT_ID();
INSERT INTO cities (country_id, name_en, name_ru, timezone, flags) VALUES (@country_id, 'Moscow', 'Москва', 'Europe/Moscow', 0);
SET @city_id = LAST_INSERT_ID();
UPDATE clubs SET city_id = @city_id WHERE timezone = 'Europe/Moscow';
UPDATE addresses SET city_id = @city_id WHERE timezone = 'Europe/Moscow';
UPDATE users SET city_id = @city_id WHERE timezone = 'Europe/Moscow';

ALTER TABLE clubs
  DROP column timezone;
ALTER TABLE clubs
   ADD KEY (`city_id`);
ALTER TABLE clubs
   ADD CONSTRAINT `club_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

ALTER TABLE addresses
  DROP column timezone;
ALTER TABLE addresses
   ADD KEY (`city_id`);
ALTER TABLE addresses
   ADD CONSTRAINT `address_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

ALTER TABLE users
  DROP column timezone;
ALTER TABLE users
   ADD KEY (`city_id`);
ALTER TABLE users
   ADD CONSTRAINT `user_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

