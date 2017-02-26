use mafia;

CREATE TABLE `clubs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `country` VARCHAR(64) NOT NULL,
  `city` VARCHAR(64) NOT NULL,
  `address` VARCHAR(128) NOT NULL,
  `is_banned` BOOL  NOT NULL,
  `timezone` VARCHAR(64) NOT NULL,

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
  `flags` INT(11) NOT NULL, -- bit flags: (flags & 1) - player; 2 - moderator; 4 - supervisor; 8 - admin; 16 - photographer; 32 - male; 64 - subscribed; 128 - banned
  `languages` INT(11) NOT NULL, -- bit flags 1 - English; 2 - Russian; 30 others can be added
  `photo_ext` VARCHAR(8) NOT NULL,

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

  `last_game_id` INT(11) NULL,
  `games_moderated` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX `user_name` (name),
  INDEX user_rating (`rating`),
  INDEX user_mafia_rating (`mafia_rating`),
  INDEX user_civil_rating (`civil_rating`),
  INDEX user_don_rating (`don_rating`),
  INDEX user_sheriff_rating (`sheriff_rating`),
  INDEX user_moderated (`games_moderated`),
  KEY `user_club` (`club_id`),
  CONSTRAINT `user_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  KEY `user_last_game` (`last_game_id`),
  CONSTRAINT `user_last_game` FOREIGN KEY (`last_game_id`) REFERENCES `games` (`id`),

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `signup` (
  `name` VARCHAR(128) NOT NULL,
  `password` VARCHAR(32) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `email` VARCHAR(256) NOT NULL,
  `email_code` VARCHAR(32) NOT NULL,
  `is_male` BOOL NOT NULL,
  `request_time` INT NOT NULL,
  `languages` INT(11) NOT NULL, -- bit flags 1 - English; 2 - Russian; 30 others can be added

  PRIMARY KEY (`name`),
  KEY `signup_club` (`club_id`),
  CONSTRAINT `signup_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `addresses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `address` VARCHAR(256) NOT NULL,
  `map_url` VARCHAR(1024) NOT NULL,
  `timezone` VARCHAR(64) NOT NULL,
  `picture` VARCHAR(8) NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE INDEX `address_club` (`club_id`, `name`),
  CONSTRAINT `address_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `address_id` INT(11) NOT NULL,
  `club_id` INT(11) NOT NULL,
  `start_time` BIGINT(20) NOT NULL, 
  `send_emails` TINYINT(2) NOT NULL, -- number of days before the event when the site is sending emails; if 0 - no emails are sent
  `email` TEXT NOT NULL, -- email body
  `email_subject` VARCHAR(128) NOT NULL, -- email subject
  `notes` TEXT NOT NULL, -- event notes
  `duration` TINYINT(2) NOT NULL, -- hours
  `flags` INT NOT NULL, -- 1: users can register when attending; 2: user password is required when moderator is registering user
  `languages` INT(11) NOT NULL, -- bit flags 1 - English; 2 - Russian; 30 others can be added

  PRIMARY KEY (`id`),
  KEY `event_address` (`address_id`),
  CONSTRAINT `event_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
  KEY `event_club` (`club_id`),
  CONSTRAINT `event_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  UNIQUE INDEX `event_start` (`start_time`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `event_users` (
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `coming_odds` TINYINT(2) NOT NULL,
  `people_with_me` TINYINT(2) NOT NULL,

  PRIMARY KEY (`event_id`, `user_id`),
  CONSTRAINT `user_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY `event_user` (`user_id`),
  CONSTRAINT `event_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `event_emails` (
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `code` VARCHAR(32) NOT NULL,
  `send_time` BIGINT(20) NOT NULL, 

  PRIMARY KEY (`event_id`, `user_id`),
  CONSTRAINT `email_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`user_id`),
  CONSTRAINT `email_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `nick_name` VARCHAR(128) NOT NULL,
  `duration` TINYINT(2) NOT NULL,
  `start_time` DATETIME NOT NULL,
  `event_id` INT(11) NULL,

  PRIMARY KEY (`id`),
  INDEX `registration_club_nick` (`club_id`, `nick_name`),
  INDEX `registration_club_time` (`club_id`, `start_time`),
  INDEX `registration_user_nick` (`user_id`, `nick_name`),
  INDEX `registration_user_time` (`user_id`, `start_time`),
  INDEX `registration_event_user` (`event_id`, `user_id`),
  CONSTRAINT `registration_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  CONSTRAINT `registration_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `registration_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `games` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `club_id` INT(11) NOT NULL,
  `moderator_id` INT(11) NOT NULL,
  `log` TEXT,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `result` TINYINT(1) NOT NULL, -- 0 - still playing; 1 - civils won; 2 - mafia won; 3 - terminated
  `event_id` INT(11) NULL,
  `language` INT(11) NOT NULL, -- bit flags 1 - English; 2 - Russian; 30 others can be added

  PRIMARY KEY (`id`),
  INDEX `game_club_result` (`club_id`, `result`),
  KEY `game_moderator_result` (`moderator_id`, `result`),
  INDEX `game_event_id` (`event_id`, `id`),
  CONSTRAINT `game_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  CONSTRAINT `game_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `game_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)

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
  `is_don` BOOL,

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

CREATE TABLE `photos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `ext` VARCHAR(8) NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`event_id`),
  CONSTRAINT `photo_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`user_id`),
  CONSTRAINT `photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_photos` (
  `user_id` INT(11) NOT NULL,
  `photo_id` INT(11) NOT NULL,

  PRIMARY KEY (`user_id`, `photo_id`),
  KEY (`photo_id`),
  CONSTRAINT `user_photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `user_photo_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `photo_comments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `photo_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY (`photo_id`),
  KEY (`user_id`),
  CONSTRAINT `comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `comment_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
