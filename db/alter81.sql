use mafia;

ALTER TABLE videos
  ADD COLUMN user_id INT(11) NOT NULL;

ALTER TABLE videos
	ADD KEY `video_user` (`user_id`, `type`, `time`);
	
ALTER TABLE videos
	ADD CONSTRAINT `video_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
	
ALTER TABLE games
  ADD COLUMN video_id INT(11) NULL;
  
ALTER TABLE games
	ADD KEY `game_video` (`video_id`);

ALTER TABLE games
	ADD CONSTRAINT `game_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`);
	
INSERT INTO videos (`name`, `video`, `type`, `club_id`, `event_id`, `lang`, `time`, `user_id`) SELECT '', video, 1, club_id, event_id, language, start_time, user_id FROM games WHERE video IS NOT NULL;
UPDATE games g JOIN videos v ON v.video = g.video SET g.video_id = v.id;

ALTER TABLE games
  DROP COLUMN video;
  
CREATE TABLE `video_comments` (

  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,
  `video_id` INT(11) NOT NULL,
  `lang` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY `video_comment_video` (`video_id`, `time`),
  KEY `video_comment_user` (`user_id`, `time`),
  CONSTRAINT `video_comment_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`),
  CONSTRAINT `video_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE user_videos
  ADD COLUMN tagged_by_id INT(11) NOT NULL;

ALTER TABLE user_videos
	ADD KEY `video_tagged_by` (`tagged_by_id`, `user_id`, `video_id`);
	
ALTER TABLE user_videos
	ADD CONSTRAINT `video_tagged_by` FOREIGN KEY (`tagged_by_id`) REFERENCES `users` (`id`);

ALTER TABLE videos
  CHANGE COLUMN time post_time INT(11) NOT NULL;
  
ALTER TABLE videos
	DROP KEY `video_time`;

ALTER TABLE videos
  ADD COLUMN video_time INT(11) NOT NULL;

ALTER TABLE videos
	ADD KEY `video_type_time` (`type`, `video_time`);
	
UPDATE videos SET video_time = post_time;
