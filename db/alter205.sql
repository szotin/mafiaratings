-- How the between-games screens are set up at a table. They used to be told in the address of
-- the browser source - the language, how long a screen stays up, which of them to show, what
-- ground they stand on - which meant a club manager had to retype the address in OBS to change
-- anything, on every machine the source was open on. The settings live here instead: the source
-- is pointed at a table and nothing else, and reads the rest from these tables as it goes, so a
-- change made on the site reaches the screens without anybody touching OBS.
--
-- Two tables rather than one because a table belongs to one of two things. A tournament keeps
-- its tables from round to round, so its settings are the tournament's and every round of it
-- reads the same ones. An event played on its own - a club night rather than a round - has
-- tables of its own and nothing above it to inherit from. They hold the same columns; which of
-- them is read is settled by what the source was pointed at.
--
-- A table with no row of its own is not an error: it runs on the defaults, and a row appears
-- the first time somebody sets anything for it.
--
-- screens is the list of screens to show, in the order they are shown, by their short names
-- and separated by commas - results, standings, upcoming, stats. Empty means all of them.
-- flags are STREAM_FLAG_* in include/constants.php: the dark ground, and the see-through one.

CREATE TABLE `tournament_streams` (
	`tournament_id` int(11) NOT NULL,
	`table_num` int(11) NOT NULL,
	`lang` int(11) NOT NULL DEFAULT '0',
	`delay` int(11) NOT NULL DEFAULT '10',
	`screens` varchar(128) NOT NULL DEFAULT '',
	`flags` int(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`tournament_id`, `table_num`),
	CONSTRAINT `tournament_stream_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `event_streams` (
	`event_id` int(11) NOT NULL,
	`table_num` int(11) NOT NULL,
	`lang` int(11) NOT NULL DEFAULT '0',
	`delay` int(11) NOT NULL DEFAULT '10',
	`screens` varchar(128) NOT NULL DEFAULT '',
	`flags` int(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`event_id`, `table_num`),
	CONSTRAINT `event_stream_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
