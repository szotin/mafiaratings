use mafia;

ALTER TABLE users
  ADD COLUMN phone VARCHAR(64) NOT NULL;

ALTER TABLE users
  ADD COLUMN club_id INT(11) NULL;

ALTER TABLE users
  ADD KEY(club_id);

ALTER TABLE users
  ADD CONSTRAINT `user_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

