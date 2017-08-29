use mafia;

ALTER TABLE cities ADD area_id INT(11) NULL;

UPDATE cities SET area_id = near_id;
UPDATE cities SET area_id = id WHERE near_id IS NULL;

ALTER TABLE cities
   ADD KEY `area` (`area_id`);

ALTER TABLE cities
   ADD CONSTRAINT `area_city` FOREIGN KEY (`area_id`) REFERENCES `cities` (`id`);


ALTER TABLE cities DROP FOREIGN KEY near_city;
ALTER TABLE cities DROP COLUMN near_id;
