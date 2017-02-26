use mafia;

CREATE TABLE `photo_albums` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `event_id` INT(11) NULL,
  `vis` TINYINT(2) NOT NULL,
  `vis_id` INT(11) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `private` BOOL NOT NULL,
  `user_id` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX `name` (name),
  KEY (`event_id`),
  CONSTRAINT `album_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`club_id`),
  CONSTRAINT `album_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  KEY (`user_id`),
  CONSTRAINT `album_owner` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO photo_albums (name, event_id, vis, private, club_id, user_id)
  SELECT DISTINCT CONCAT(CONCAT(e.name, ':'), FROM_UNIXTIME(e.start_time, ' %a, %b %e, %y')), e.id, 0, false, e.club_id, 1 FROM photos p, events e WHERE p.event_id = e.id;

ALTER TABLE photos
  ADD COLUMN vis TINYINT(2) NOT NULL;

ALTER TABLE photos
  ADD COLUMN vis_id INT(11) NULL;

ALTER TABLE photos
  ADD COLUMN album_id INT(11) NOT NULL;

UPDATE photos p SET p.album_id = (SELECT a.id FROM photo_albums a WHERE a.event_id = p.event_id);

ALTER TABLE photos
  ADD KEY (album_id);

ALTER TABLE photos
  ADD CONSTRAINT photo_album FOREIGN KEY(album_id) REFERENCES photo_albums(id);

ALTER TABLE photos
  DROP FOREIGN KEY photo_event;

ALTER TABLE photos
  DROP COLUMN event_id;

ALTER TABLE user_photos
  ADD COLUMN tag BOOL NOT NULL;

UPDATE user_photos SET tag = true;

