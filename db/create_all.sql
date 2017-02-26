CREATE DATABASE mafia
CHARACTER SET utf8
COLLATE utf8_general_ci;

USE mafia;

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE IF NOT EXISTS `addresses` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(64) NOT NULL,
  `club_id` int(11) NOT NULL,
  `address` varchar(256) NOT NULL,
  `map_url` varchar(1024) NOT NULL,
  `timezone` varchar(64) NOT NULL,
  `has_picture` tinyint(1) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `address_club` (`club_id`,`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `clubs` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `country` varchar(64) NOT NULL,
  `city` varchar(64) NOT NULL,
  `address` varchar(128) NOT NULL,
  `is_banned` tinyint(1) default NULL,
  `timezone` varchar(64) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `club_name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `dons` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sheriff_found` tinyint(2) default NULL,
  `sheriff_arranged` tinyint(2) default NULL,
  PRIMARY KEY  (`game_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `emails` (
  `user_id` int(11) NOT NULL,
  `code` varchar(32) NOT NULL,
  `send_time` int(11) NOT NULL,
  `obj` tinyint(2) NOT NULL,
  `obj_id` int(11) NOT NULL,
  KEY `user_id` (`user_id`),
  KEY `obj` (`obj`,`obj_id`),
  KEY `send_time` (`send_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `address_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `send_emails` tinyint(2) NOT NULL,
  `email` text NOT NULL,
  `email_subject` varchar(128) NOT NULL,
  `notes` text NOT NULL,
  `duration` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `languages` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `event_start` (`start_time`),
  KEY `event_address` (`address_id`),
  KEY `event_club` (`club_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `event_users` (
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coming_odds` tinyint(2) NOT NULL,
  `people_with_me` tinyint(2) NOT NULL,
  PRIMARY KEY  (`event_id`,`user_id`),
  KEY `event_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL auto_increment,
  `club_id` int(11) NOT NULL,
  `moderator_id` int(11) NOT NULL,
  `log` text,
  `result` tinyint(1) NOT NULL,
  `start_time` int(11) default NULL,
  `end_time` int(11) default NULL,
  `event_id` int(11) default NULL,
  `language` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `game_club_result` (`club_id`,`result`),
  KEY `game_moderator_result` (`moderator_id`,`result`),
  KEY `game_event_id` (`event_id`,`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mafiosos` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shots1_ok` tinyint(2) default NULL,
  `shots1_miss` tinyint(2) default NULL,
  `shots2_ok` tinyint(2) default NULL,
  `shots2_miss` tinyint(2) default NULL,
  `shots2_blank` tinyint(2) default NULL,
  `shots2_rearrange` tinyint(2) default NULL,
  `shots3_ok` tinyint(2) default NULL,
  `shots3_miss` tinyint(2) default NULL,
  `shots3_blank` tinyint(2) default NULL,
  `shots3_fail` tinyint(2) default NULL,
  `shots3_rearrange` tinyint(2) default NULL,
  `is_don` tinyint(1) NOT NULL,
  PRIMARY KEY  (`game_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL auto_increment,
  `obj` tinyint(2) NOT NULL,
  `obj_id` int(11) NOT NULL,
  `vis` tinyint(2) NOT NULL,
  `vis_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `language` int(11) NOT NULL,
  `send_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `obj` (`obj`,`obj_id`,`update_time`),
  KEY `send_time` (`send_time`),
  KEY `user_id` (`user_id`,`send_time`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `messages_tree` (
  `message_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `send_time` int(11) NOT NULL,
  PRIMARY KEY  (`message_id`,`parent_id`),
  KEY `parent_id` (`parent_id`,`send_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `photos` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `vis` tinyint(2) NOT NULL,
  `vis_id` int(11) default NULL,
  `album_id` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `album_id` (`album_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `photo_albums` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `event_id` int(11) default NULL,
  `vis` tinyint(2) NOT NULL,
  `vis_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `private` tinyint(1) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `event_id` (`event_id`),
  KEY `club_id` (`club_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `players` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nick_name` varchar(128) NOT NULL,
  `number` tinyint(2) NOT NULL,
  `role` tinyint(1) NOT NULL,
  `rating` tinyint(2) default NULL,
  `voted_civil` tinyint(2) default NULL,
  `voted_mafia` tinyint(2) default NULL,
  `voted_sheriff` tinyint(2) default NULL,
  `voted_by_civil` tinyint(2) default NULL,
  `voted_by_mafia` tinyint(2) default NULL,
  `voted_by_sheriff` tinyint(2) default NULL,
  `nominated_civil` tinyint(2) default NULL,
  `nominated_mafia` tinyint(2) default NULL,
  `nominated_sheriff` tinyint(2) default NULL,
  `nominated_by_civil` tinyint(2) default NULL,
  `nominated_by_mafia` tinyint(2) default NULL,
  `nominated_by_sheriff` tinyint(2) default NULL,
  `kill_round` tinyint(2) default NULL,
  `kill_type` tinyint(2) default NULL,
  `warns` tinyint(2) default NULL,
  `was_arranged` tinyint(2) default NULL,
  `checked_by_don` tinyint(2) default NULL,
  `checked_by_sheriff` tinyint(2) default NULL,
  PRIMARY KEY  (`game_id`,`user_id`),
  KEY `player_role` (`role`),
  KEY `player_user_role` (`user_id`,`role`),
  KEY `player_game` (`game_id`),
  KEY `player_user` (`user_id`,`nick_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ratings` (
  `user_id` int(11) NOT NULL,
  `role` tinyint(1) NOT NULL,
  `rating` int(11) NOT NULL,
  `games` int(11) NOT NULL,
  `games_won` int(11) NOT NULL,
  PRIMARY KEY  (`user_id`,`role`),
  KEY `role` (`role`,`rating`,`games`,`games_won`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int(11) NOT NULL auto_increment,
  `club_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nick_name` varchar(128) NOT NULL,
  `duration` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `event_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `registration_club_nick` (`club_id`,`nick_name`),
  KEY `registration_user_nick` (`user_id`,`nick_name`),
  KEY `registration_club_time` (`club_id`,`start_time`),
  KEY `registration_user_time` (`user_id`,`start_time`),
  KEY `registration_event_user` (`event_id`,`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sheriffs` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `civil_found` tinyint(2) default NULL,
  `mafia_found` tinyint(2) default NULL,
  PRIMARY KEY  (`game_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `signup` (
  `name` varchar(128) NOT NULL,
  `password` varchar(32) NOT NULL,
  `club_id` int(11) NOT NULL,
  `email` varchar(256) NOT NULL,
  `email_code` varchar(32) NOT NULL,
  `is_male` tinyint(1) NOT NULL,
  `request_time` int(11) NOT NULL,
  `languages` int(11) NOT NULL,
  PRIMARY KEY  (`name`),
  KEY `signup_club` (`club_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `password` varchar(32) NOT NULL,
  `auth_key` varchar(32) NOT NULL,
  `club_id` int(11) NOT NULL,
  `email` varchar(256) NOT NULL,
  `games_moderated` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `languages` int(11) NOT NULL,
  `forum_last_view` int(11) NOT NULL,
  `rank` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `user_name` (`name`),
  KEY `user_club` (`club_id`),
  KEY `user_moderated` (`games_moderated`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `user_photos` (
  `user_id` int(11) NOT NULL,
  `photo_id` int(11) NOT NULL,
  `email_sent` tinyint(1) NOT NULL,
  `tag` tinyint(1) NOT NULL,
  PRIMARY KEY  (`user_id`,`photo_id`),
  KEY `photo_id` (`photo_id`),
  KEY `email_sent` (`email_sent`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `addresses`
  ADD CONSTRAINT `address_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

ALTER TABLE `dons`
  ADD CONSTRAINT `don_player` FOREIGN KEY (`game_id`, `user_id`) REFERENCES `mafiosos` (`game_id`, `user_id`);

ALTER TABLE `emails`
  ADD CONSTRAINT `email_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `events`
  ADD CONSTRAINT `event_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
  ADD CONSTRAINT `event_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

ALTER TABLE `event_users`
  ADD CONSTRAINT `event_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

ALTER TABLE `games`
  ADD CONSTRAINT `game_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `game_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `game_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`);

ALTER TABLE `mafiosos`
  ADD CONSTRAINT `mafioso_player` FOREIGN KEY (`game_id`, `user_id`) REFERENCES `players` (`game_id`, `user_id`);

ALTER TABLE `messages`
  ADD CONSTRAINT `message_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `messages_tree`
  ADD CONSTRAINT `tree_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`),
  ADD CONSTRAINT `tree_parent` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`id`);

ALTER TABLE `photos`
  ADD CONSTRAINT `photo_album` FOREIGN KEY (`album_id`) REFERENCES `photo_albums` (`id`),
  ADD CONSTRAINT `photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `photo_albums`
  ADD CONSTRAINT `album_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `album_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `album_owner` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `players`
  ADD CONSTRAINT `player_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `player_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `ratings`
  ADD CONSTRAINT `rating_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `registrations`
  ADD CONSTRAINT `registration_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `registration_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `registration_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `sheriffs`
  ADD CONSTRAINT `sheriff_player` FOREIGN KEY (`game_id`, `user_id`) REFERENCES `players` (`game_id`, `user_id`);

ALTER TABLE `signup`
  ADD CONSTRAINT `signup_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

ALTER TABLE `users`
  ADD CONSTRAINT `user_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

ALTER TABLE `user_photos`
  ADD CONSTRAINT `user_photo_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`),
  ADD CONSTRAINT `user_photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

-- club and admin

INSERT INTO clubs (`name`, `country`, `city`, `address`, `is_banned`, `timezone`)
  VALUES ('Vancouver Mafia Club', 'Canada', 'Vancouver', '', false, 'America/Vancouver');
SET @club_id = LAST_INSERT_ID();

INSERT INTO users (`name`, `password`, `auth_key`, `club_id`, `email`, `games_moderated`, `flags`, `languages`, `forum_last_view`, `rank`)
  VALUES ('Admin',  'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, 'admin@mafiaworld.ca', 0, 1640, 3, 0, 0);

INSERT INTO users (`name`, `password`, `auth_key`, `club_id`, `email`, `games_moderated`, `flags`, `languages`, `forum_last_view`, `rank`)
  VALUES ('Super',  'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, 'super@mafiaworld.ca', 0, 1636, 3, 0, 0);

INSERT INTO users (`name`, `password`, `auth_key`, `club_id`, `email`, `games_moderated`, `flags`, `languages`, `forum_last_view`, `rank`)
  VALUES ('Moder',  'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, 'moder@mafiaworld.ca', 0, 1634, 3, 0, 0);
