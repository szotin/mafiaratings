-- What is played in a series: tournaments, subseries, or both.
--
-- A series holds two kinds of competition - tournaments through series_tournaments and subseries
-- through series_series - and until now every series was open to both whether it ever meant to be.
-- A series now says which of them are played in it, and the lists of potential parents offered
-- when a tournament or a series is created follow it, as do the operations that write the
-- membership.
--
-- Two bits of series.flags, and never both clear: a series that takes no competition at all has
-- nothing to rank. 512 is SERIES_FLAG_PLAYS_TOURNAMENTS, 1024 is SERIES_FLAG_PLAYS_SERIES - see
-- include/constants.php. No column: the flags had room.
--
-- Every series that exists gets the tournaments bit. That is right for a recurring tournament,
-- whose members are the same event happening again, and for a series of tournaments, which says
-- which stage a tournament belongs to.
UPDATE series SET flags = flags | 512;

-- A season is the one kind that usually holds both: its own tournaments, and the subseries of the
-- leagues and clubs running inside it. The condition is the one series_type_condition() uses for a
-- season - dates, and stars to grade its competitions with.
UPDATE series SET flags = flags | 1024 WHERE start_time <> 0 AND max_stars >= 1;
