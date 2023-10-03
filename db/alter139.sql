ALTER TABLE `users` ADD COLUMN `name_id` INT(11) NOT NULL;
ALTER TABLE `users` ADD KEY (`name_id`);

INSERT INTO names (langs, name) SELECT 4444, name FROM users;
UPDATE users u SET u.name_id = (SELECT n.id FROM names n WHERE n.name = u.name AND langs = 4444);
UPDATE names SET langs = 16777215 WHERE langs = 4444;

ALTER TABLE `users` ADD CONSTRAINT user_name FOREIGN KEY(name_id) REFERENCES names(id);
ALTER TABLE `users` DROP COLUMN `name`;

UPDATE users SET def_lang = 1 WHERE def_lang = 0;

ALTER TABLE `game_settings` DROP COLUMN `l_autosave`;
ALTER TABLE `game_settings` DROP COLUMN `g_autosave`;
