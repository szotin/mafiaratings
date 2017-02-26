use mafia;

ALTER TABLE club_info
   ADD COLUMN `pos` INT(11) NOT NULL;

UPDATE club_info SET pos = id;

ALTER TABLE club_info
  DROP FOREIGN KEY info_club;

ALTER TABLE club_info
  DROP KEY club_id;

ALTER TABLE club_info
  ADD UNIQUE INDEX(club_id, pos);

ALTER TABLE club_info
  ADD CONSTRAINT `info_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

