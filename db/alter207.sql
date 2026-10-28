-- Tying a game to the moment it begins inside a stream.
--
-- A stream and a game are both stamped in wall clock time, so where a game begins in the
-- recording looks like a subtraction: when the game started, less when the stream started. It
-- is not, because a live stream that drops its connection is spliced: YouTube keeps the stream
-- as one recording and leaves the dead air out of it, so everything after an interruption sits
-- earlier in the recording than the clock would say, and every game after it is out by the sum
-- of every interruption before it. On one real broadcast of ours that came to an hour and a
-- half over a thirteen hour day.
--
-- So the interruptions have to be measured, and we are already in a position to measure them.
-- The overlays run inside the same OBS that is doing the streaming, and they carry what OBS
-- itself says about its stream, holding on to it while they cannot get through and sending it
-- on when they can. stream_outages is where the stretches OBS reported as off the air are
-- written down.
--
-- Only those. A silence is not taken for a break, however long it runs. The overlays may
-- simply not be there - nobody put them in the scene, or they were taken out - and the stream
-- will have been running all the while; a break invented out of such a silence would move
-- every game after it in that recording and spoil times that were right. So the error only
-- ever runs one way: less time off the air than there was, never more.
--
-- seen_time on the two stream settings tables is the last time a table's overlay was heard
-- from. It is what says which part of an account has already been written down, so that the
-- same break is not recorded twice while the overlay goes on re-sending it.
--
-- start_time on current_games is the moment the site first saw the game, by the site's own
-- clock. The game carries a start time of its own already, but it is stamped by the browser of
-- whoever is refereeing, and a laptop with the wrong clock would move the game in the
-- recording by exactly as much as the clock is out.
--
-- stream_start, stream_end and video_length are what the broadcast itself says about its
-- timing, read from its page and kept so it is not asked again. The three of them also settle
-- how good the measuring was: the recording is shorter than the wall clock by precisely the
-- time that was cut out of it, so the breaks we recorded should add up to that difference.
-- They often will not, and that is not a fault - it is the measure of how far the links into
-- that recording may be out, and it is shown to whoever runs the tournament.
--
-- flags on videos tells a time the site worked out from a time a person typed in. Only the
-- first kind may be moved, and that is what lets one correction carry the rest of a recording
-- with it: a person who moves one game to where it really begins has said how much the time
-- off the air was out by, and every later game of the same recording is out by the same.
-- Everything already in that table was put there by hand, which is what the default says.

ALTER TABLE `current_games`
	ADD COLUMN `start_time` int(11) DEFAULT NULL;

ALTER TABLE `tournament_streams`
	ADD COLUMN `seen_time` int(11) NOT NULL DEFAULT '0',
	ADD COLUMN `obs_start_time` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `event_streams`
	ADD COLUMN `seen_time` int(11) NOT NULL DEFAULT '0',
	ADD COLUMN `obs_start_time` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `event_broadcasts`
	ADD COLUMN `stream_start` int(11) DEFAULT NULL,
	ADD COLUMN `stream_end` int(11) DEFAULT NULL,
	ADD COLUMN `video_length` int(11) DEFAULT NULL,
	ADD COLUMN `checked_time` int(11) DEFAULT NULL,
	ADD COLUMN `time_shift` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `videos`
	ADD COLUMN `flags` int(11) NOT NULL DEFAULT '0';

DROP TABLE IF EXISTS `stream_outages`;
CREATE TABLE `stream_outages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `table_num` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `end_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `by_tournament` (`tournament_id`,`table_num`,`start_time`),
  KEY `by_event` (`event_id`,`table_num`,`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
