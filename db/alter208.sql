-- How long a table's stream is delayed, kept with the table.
--
-- Where a game sits in a recording is the moment it began less the moment the recording began,
-- and the second of those is not the moment YouTube stamps on the broadcast: that stamp is
-- when the picture reached the viewers, which is seconds after the picture was made. So every
-- game placed by it lands early by exactly the delay of the stream, and time_shift on
-- event_broadcasts is what puts that back.
--
-- The delay was being worked out as the gap between OBS going on the air and YouTube saying
-- the broadcast had begun. That gap is not the delay. YouTube does not begin a broadcast when
-- the pictures start arriving - it begins when somebody presses Go live, and what is in
-- between is however long that took them. On one of our own runs it came to 77 seconds
-- against a real delay of about ten, and the only reason it did no harm is that it was thrown
-- out for being longer than a stream is ever delayed by. A smaller one would have been
-- believed and would have moved every game of the evening.
--
-- It is read off the recording instead. A finished recording is not the same length as the
-- window the broadcast was live for: it is shorter by whatever was cut out of it while the
-- stream was off the air, which is what stream_outages measures, and longer by the delay,
-- because what the viewers had not been shown yet when the broadcast began is in the
-- recording all the same. One taken from the other leaves the delay, to the second. On four
-- of our own broadcasts it came to 9, 13, 13 and 16 seconds, against ten-odd measured by
-- hand on the same streams.
--
-- That can only be read once YouTube has finished the recording, which is hours after the
-- broadcast is over, while the games are written down as they are played. So the figure is
-- kept here as well: the same machine on the same connection is delayed by much the same from
-- one evening to the next, and a recording that cannot be measured yet borrows it. When the
-- recording can be measured, the exact figure replaces the borrowed one and the games already
-- placed on it move with it.

ALTER TABLE `tournament_streams`
	ADD COLUMN `time_shift` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `event_streams`
	ADD COLUMN `time_shift` int(11) NOT NULL DEFAULT '0';
