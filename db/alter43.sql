use mafia;

ALTER TABLE photo_albums
   ADD COLUMN `flags` INT(11) NOT NULL;


UPDATE photo_albums SET flags = private;

ALTER TABLE photo_albums
   DROP COLUMN `private`;

