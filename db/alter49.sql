use mafia;

ALTER TABLE club_requests
   DROP COLUMN `country`;

ALTER TABLE club_requests
   DROP COLUMN `city`;

ALTER TABLE club_requests
   ADD COLUMN `city_id` INT(11);

ALTER TABLE club_requests
   ADD KEY(`city_id`);

ALTER TABLE club_requests
  ADD CONSTRAINT `request_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

