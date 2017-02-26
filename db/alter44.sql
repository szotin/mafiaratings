use mafia;

ALTER TABLE photo_albums
   ADD COLUMN `viewers` TINYINT(2) NOT NULL;

ALTER TABLE photo_albums
   ADD COLUMN `adders` TINYINT(2) NOT NULL;

UPDATE photo_albums SET adders = 2;
UPDATE photo_albums SET viewers = 0 WHERE vis = 0 OR vis = 1;
UPDATE photo_albums SET viewers = 1 WHERE vis = 2;
UPDATE photo_albums SET viewers = 3 WHERE vis > 2;

ALTER TABLE photo_albums
   DROP COLUMN `vis`;

ALTER TABLE photo_albums
   DROP COLUMN `vis_id`;

ALTER TABLE photos
   ADD COLUMN `viewers` TINYINT(2) NOT NULL;

UPDATE photos SET viewers = 0 WHERE vis = 0 OR vis = 1;
UPDATE photos SET viewers = 1 WHERE vis = 2;
UPDATE photos SET viewers = 3 WHERE vis > 2;

ALTER TABLE photos
   DROP COLUMN `vis`;

ALTER TABLE photos
   DROP COLUMN `vis_id`;

ALTER TABLE messages
  ADD COLUMN `viewers` TINYINT(2) NOT NULL;

ALTER TABLE messages
  ADD COLUMN `club_id` INT(11) NULL;

ALTER TABLE messages
  ADD KEY(`club_id`);

ALTER TABLE messages
  ADD CONSTRAINT `message_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

UPDATE messages m SET m.club_id = 1;

UPDATE messages SET viewers = 0 WHERE vis = 0 OR vis = 1;
UPDATE messages SET viewers = 1 WHERE vis = 2;
UPDATE messages SET viewers = 3 WHERE vis > 2;
UPDATE messages SET obj = 5, obj_id = vis_id WHERE vis = 4 AND obj <> 0;

ALTER TABLE messages
   DROP COLUMN `vis`;

ALTER TABLE messages
   DROP COLUMN `vis_id`;
