use mafia;

ALTER TABLE `league_clubs` ADD COLUMN `flags` INT(11) NOT NULL;

CREATE TABLE `league_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `langs` INT(11) NOT NULL,
  `web_site` VARCHAR(256) NOT NULL,
  `email` VARCHAR(256) NOT NULL,
  `phone` VARCHAR(256) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`user_id`),
  CONSTRAINT `league_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



