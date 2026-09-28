-- Give a series a type.
--
-- There are three kinds of series. A season and a series of tournaments behave identically
-- for now - they differ only in what the organizers call them. A recurring tournament is the
-- new kind: it has no start and no end, it just keeps running, so it is kept out of the main
-- page and out of the series lists unless it is asked for.
--
-- Zero is SERIES_TYPE_SERIES, a series of tournaments, which is what every existing series is.
ALTER TABLE series
  ADD COLUMN type INT(11) NOT NULL DEFAULT 0;
