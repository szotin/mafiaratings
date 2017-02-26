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

  PRIMARY KEY (`id`),
  UNIQUE INDEX (`name_en`),
  UNIQUE INDEX (`name_ru`),
  KEY (`country_id`),
  CONSTRAINT `city_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE clubs
  ADD COLUMN city_id INT(11) NOT NULL;

ALTER TABLE addresses
  ADD COLUMN city_id INT(11) NOT NULL;

ALTER TABLE users
  ADD COLUMN city_id INT(11) NOT NULL;

INSERT INTO countries (name_en, name_ru, flags) VALUES ('Canada', '??????', 0);
SET @country_id = LAST_INSERT_ID();
INSERT INTO cities (country_id, name_en, name_ru, timezone, flags) VALUES (@country_id, 'Vancouver', '????????', 'America/Vancouver', 0);
SET @city_id = LAST_INSERT_ID();
UPDATE clubs SET city_id = @city_id;
UPDATE addresses SET city_id = @city_id;
UPDATE users SET city_id = @city_id;

INSERT INTO countries (name_en, name_ru, flags) VALUES ('Russia', '??????', 0);
SET @country_id = LAST_INSERT_ID();
INSERT INTO cities (country_id, name_en, name_ru, timezone, flags) VALUES (@country_id, 'Moscow', '??????', 'Europe/Moscow', 0);
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

