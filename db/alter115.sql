use mafia;

CREATE TABLE `sounds` (
`id` INT(11) NOT NULL AUTO_INCREMENT,
`club_id` INT(11) NULL,
`user_id` INT(11) NULL,
`name` VARCHAR(128) NOT NULL,

PRIMARY KEY (`id`),
KEY (`club_id`, `name`),
CONSTRAINT `sound_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
KEY (`user_id`, `name`),
CONSTRAINT `sound_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO sounds (name) VALUES ('No sound');
INSERT INTO sounds (name) VALUES ('Cymbals');
INSERT INTO sounds (name) VALUES ('Zummer');

ALTER TABLE game_settings ADD COLUMN `prompt_sound_id` INT(11) NULL;
ALTER TABLE game_settings ADD KEY (prompt_sound_id);
ALTER TABLE game_settings ADD CONSTRAINT user_prompt_sound FOREIGN KEY(prompt_sound_id) REFERENCES sounds(id);

ALTER TABLE game_settings ADD COLUMN `end_sound_id` INT(11) NULL;
ALTER TABLE game_settings ADD KEY (end_sound_id);
ALTER TABLE game_settings ADD CONSTRAINT user_end_sound FOREIGN KEY(end_sound_id) REFERENCES sounds(id);

UPDATE game_settings SET flags = (flags & ~4) WHERE (flags & 4) <> 0;

ALTER TABLE clubs ADD COLUMN `prompt_sound_id` INT(11) NULL;
ALTER TABLE clubs ADD KEY (prompt_sound_id);
ALTER TABLE clubs ADD CONSTRAINT club_prompt_sound FOREIGN KEY(prompt_sound_id) REFERENCES sounds(id);

ALTER TABLE clubs ADD COLUMN `end_sound_id` INT(11) NULL;
ALTER TABLE clubs ADD KEY (end_sound_id);
ALTER TABLE clubs ADD CONSTRAINT club_end_sound FOREIGN KEY(end_sound_id) REFERENCES sounds(id);


