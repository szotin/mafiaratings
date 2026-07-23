CREATE DATABASE mafia
CHARACTER SET utf8
COLLATE utf8_general_ci;

use mafia;

-- Initial (pre-alter1) schema. `db/alter1.sql` .. `db/alterN.sql` incrementally
-- bring this up to the current schema. Only the tables that exist before alter1
-- live here; every other table is created by an alter script. Columns that later
-- alters drop are kept here (some with placeholder types) so those DROPs succeed.

CREATE TABLE `clubs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `country` VARCHAR(64) NOT NULL,
  `city` VARCHAR(64) NOT NULL,
  `address` VARCHAR(128) NOT NULL,
  `is_banned` BOOL NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX `club_name` (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `password` VARCHAR(32) NOT NULL,
  `auth_key` VARCHAR(32) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `email` VARCHAR(256) NOT NULL,

  `is_player` BOOL NOT NULL,
  `is_moderator` BOOL NOT NULL,
  `is_supervisor` BOOL NOT NULL,
  `is_admin` BOOL NOT NULL,
  `is_male` BOOL NOT NULL,
  `is_banned` BOOL NOT NULL,

  `rating` INT(11) NOT NULL, -- rating = mafia_rating + civil_rating + don_rating + sheriff_rating (for indexing)
  `mafia_rating` INT(11) NOT NULL,
  `civil_rating` INT(11) NOT NULL,
  `don_rating` INT(11) NOT NULL,
  `sheriff_rating` INT(11) NOT NULL,

  `mafia_games` INT(11) NOT NULL,
  `civil_games` INT(11) NOT NULL,
  `don_games` INT(11) NOT NULL,
  `sheriff_games` INT(11) NOT NULL,

  `mafia_games_won` INT(11) NOT NULL,
  `civil_games_won` INT(11) NOT NULL,
  `don_games_won` INT(11) NOT NULL,
  `sheriff_games_won` INT(11) NOT NULL,

  `forum_last_view` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX `user_name` (name),
  INDEX user_rating (`rating`),
  INDEX user_mafia_rating (`mafia_rating`),
  INDEX user_civil_rating (`civil_rating`),
  INDEX user_don_rating (`don_rating`),
  INDEX user_sheriff_rating (`sheriff_rating`),
  KEY `user_club` (`club_id`),
  CONSTRAINT `user_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `nick_name` VARCHAR(128) NOT NULL,
  `duration` TINYINT(2) NOT NULL,
  `start_time` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  INDEX `registration_club_nick` (`club_id`, `nick_name`),
  INDEX `registration_club_time` (`club_id`, `start_time`),
  INDEX `registration_user_nick` (`user_id`, `nick_name`),
  INDEX `registration_user_time` (`user_id`, `start_time`),
  CONSTRAINT `registration_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  CONSTRAINT `registration_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `games` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `moderator_id` INT(11) NOT NULL,
  `log` TEXT,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `result` TINYINT(1) NOT NULL, -- 0 - still playing; 1 - civils won; 2 - mafia won; 3 - terminated

  PRIMARY KEY (`id`),
  INDEX `game_club_result` (`club_id`, `result`),
  KEY `game_moderator_result` (`moderator_id`, `result`),
  CONSTRAINT `game_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  CONSTRAINT `game_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `players` (
  `game_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,

  `nick_name` VARCHAR(128) NOT NULL,
  `number` TINYINT(2) NOT NULL, -- 1-10 player number in the game
  `role` TINYINT(1) NOT NULL, -- 0 - civil; 1 - sherif; 2 - mafia; 3 - don
  `rating` TINYINT(2),

  -- voting
  `voted_civil` TINYINT(2), -- number of times this player voted against civilians (excluding sheriff)
  `voted_mafia` TINYINT(2), -- number of times this player voted against mafia
  `voted_sheriff` TINYINT(2), -- number of times this player voted against sheriff
  `voted_by_civil` TINYINT(2), -- number of times this player was nominated by civils
  `voted_by_mafia` TINYINT(2), -- number of times this player was nominated by mafia
  `voted_by_sheriff` TINYINT(2), -- number of times this player was nominated by sheriff

  -- nominating
  `nominated_civil` TINYINT(2), -- number of times this player nominated civilians
  `nominated_mafia` TINYINT(2), -- number of times this player nominated mafia
  `nominated_sheriff` TINYINT(2), -- number of times this player nominated sheriff
  `nominated_by_civil` TINYINT(2), -- number of times this player was nominated by civils
  `nominated_by_mafia` TINYINT(2), -- number of times this player was nominated by mafia
  `nominated_by_sheriff` TINYINT(2), -- number of times this player was nominated by sheriff

  -- surviving
  `kill_round` TINYINT(2), -- -1 if kill_type == 0
  `kill_type` TINYINT(2), -- 0 - alive; 1 - day; 2 - night; 3 warnings; 4 suicide; 5 - kick out
  `warns` TINYINT(2), -- number of warnings
  `was_arranged` TINYINT(2), -- if this player was arranged in this game
  `checked_by_don` TINYINT(2), -- if this player was checked by don
  `checked_by_sheriff` TINYINT(2), -- if this player was checked by sheriff

  PRIMARY KEY (`game_id`, `user_id`),
  INDEX `player_role` (`role`),
  INDEX `player_user_role` (`user_id`, `role`),
  KEY `player_game` (`game_id`),
  CONSTRAINT `player_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  KEY `player_user` (`user_id`, `nick_name`),
  CONSTRAINT `player_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `mafiosos` (
  `game_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,

  -- shooting
  -- total shots = shots1_ok + shots2_ok + shots3_ok + shots1_miss + shots2_miss + shots3_miss
  `shots1_ok` TINYINT(2), -- 1 mafia player is alive: successful shots
  `shots1_miss` TINYINT(2), -- 1 mafia player is alive: missed shot (this player didn't shoot)

  `shots2_ok` TINYINT(2), -- 2 mafia players are alive: successful shots
  `shots2_miss` TINYINT(2), -- 2 mafia players are alive: missed shot
  `shots2_blank` TINYINT(2), -- 2 mafia players are alive: this player didn't shoot
  `shots2_rearrange` TINYINT(2), -- 2 mafia players are alive: killed a player who was not arranged

  `shots3_ok` TINYINT(2), -- 3 mafia players are alive: successful shots
  `shots3_miss` TINYINT(2), -- 3 mafia players are alive: missed shot
  `shots3_blank` TINYINT(2), -- 3 mafia players are alive: this player didn't shoot
  `shots3_fail` TINYINT(2), -- 3 mafia players are alive: missed because of this player (others shoot the same person)
  `shots3_rearrange` TINYINT(2), -- 3 mafia players are alive: killed a player who was not arranged

  PRIMARY KEY (`game_id`, `user_id`),
  CONSTRAINT `mafioso_player` FOREIGN KEY (`game_id`, `user_id`) REFERENCES `players` (`game_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sheriffs` (
  `game_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `civil_found` TINYINT(2),
  `mafia_found` TINYINT(2),

  PRIMARY KEY (`game_id`, `user_id`),
  CONSTRAINT `sheriff_player` FOREIGN KEY (`game_id`, `user_id`) REFERENCES `players` (`game_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `dons` (
  `game_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `sheriff_found` TINYINT(2), -- the round when sheriff was found; -1 if sheriff was not found
  `sheriff_arranged` TINYINT(2), -- the round when sheriff was arranged for murder; -1 if sheriff was not arranged

  PRIMARY KEY (`game_id`, `user_id`),
  CONSTRAINT `don_player` FOREIGN KEY (`game_id`, `user_id`) REFERENCES `mafiosos` (`game_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
