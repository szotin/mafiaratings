use mafia;

CREATE TABLE `changelists` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `submit_time` INT(11) NOT NULL,
  `data` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (user_id),
  CONSTRAINT `changelist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  KEY (club_id),
  CONSTRAINT `changelist_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP table signup;