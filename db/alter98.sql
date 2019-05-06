use mafia;

ALTER TABLE events DROP FOREIGN KEY event_address;
ALTER TABLE events DROP FOREIGN KEY event_club;
ALTER TABLE events DROP FOREIGN KEY event_scoring;
ALTER TABLE events DROP FOREIGN KEY event_tournament;

ALTER TABLE events DROP INDEX event_start;
ALTER TABLE events DROP INDEX event_address;
ALTER TABLE events DROP INDEX event_club;
ALTER TABLE events DROP INDEX system_id;
ALTER TABLE events DROP INDEX tournament_id;

CREATE INDEX i_events_start ON events (start_time, id);
CREATE INDEX i_events_address ON events (address_id, start_time);
CREATE INDEX i_events_club ON events (club_id, start_time);
CREATE INDEX i_events_scoring ON events (scoring_id, start_time);
CREATE INDEX i_events_tournament ON events (tournament_id, start_time);

ALTER TABLE events ADD CONSTRAINT fk_events_address FOREIGN KEY (address_id) REFERENCES addresses(id);
ALTER TABLE events ADD CONSTRAINT fk_events_club FOREIGN KEY (club_id) REFERENCES clubs(id);
ALTER TABLE events ADD CONSTRAINT fk_events_scoring FOREIGN KEY (scoring_id) REFERENCES scorings(id);
ALTER TABLE events ADD CONSTRAINT fk_events_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id);

CREATE TABLE `tournament_comments` (

  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,
  `tournament_id` INT(11) NOT NULL,
  `lang` INT(11) NOT NULL,

  PRIMARY KEY (`id`),
  KEY `i_tournament_comments_tournament` (`tournament_id`, `time`),
  KEY `i_tournament_comments_user` (`user_id`, `time`),
  CONSTRAINT `fk_tournament_comments_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  CONSTRAINT `fk_tournament_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `tournament_invitations` (

  `tournament_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `status` INT(11) NOT NULL,

  PRIMARY KEY (`tournament_id`, `user_id`),
  KEY `i_tournament_invitations_user` (`user_id`),
  CONSTRAINT `fk_tournament_invitations_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  CONSTRAINT `fk_tournament_invitations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

