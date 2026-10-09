-- Series stages, and the three properties a series is actually made of.
--
-- A series used to be described by its type, and the type was a thing an organizer picked from a
-- list. It turns out the type is not a property at all: it follows from three that are.
--
--   * how many stars the series gives its tournaments, zero meaning it does not grade them;
--   * how many stages it has, one meaning there is no ladder;
--   * whether it has dates at all.
--
-- A series with no dates is a recurring tournament. Of the dated ones, the ones that grade their
-- tournaments are seasons, and the rest are series of tournaments - a series of tournaments says
-- which stage a tournament belongs to instead of rating it. So series.type is dropped at the end
-- of this migration: it held nothing that the other columns do not already say.

-- The stage of the series a tournament belongs to, numbered the way tournaments number the rounds
-- of their own playoff in events.round: 0 is the base stage - a qualifier, or an ordinary
-- tournament of a season - 1 is the final, 2 the semifinal, 3 the quarterfinal. Every existing
-- membership is the base stage.
ALTER TABLE series_tournaments
  ADD COLUMN stage INT(11) NOT NULL DEFAULT 0;

-- How many stages the series has. One means the base stage and nothing above it, which is every
-- series that exists today. The stages of a series with N of them are 0 and 1..N-1, so the count
-- is all that has to be stored about the ladder.
ALTER TABLE series
  ADD COLUMN stages INT(11) NOT NULL DEFAULT 1;

-- A recurring tournament has no dates in either direction, and the stored start says so: the
-- epoch, with the eternal duration alter195 already gives it. It used to be created with the
-- moment of its creation as the start, which read as a real date it never had - and on a 32 bit
-- PHP build that start plus the eternal duration overflowed, so api/get/series.php reported the
-- end of such a series as false. The epoch plus that duration is exactly the largest 32 bit
-- integer, so the sum is safe, every query asking whether the series has started answers yes and
-- every query asking whether it is over answers no.
--
-- A start of zero is now what makes a series recurring, rather than the type saying so. Nothing
-- else has a start of zero: the earliest real series starts in 2017.
--
-- 2 is SERIES_TYPE_RECURRING.
UPDATE series SET start_time = 0 WHERE type = 2;

-- Every tournament of a recurring tournament is the same event happening again, so there is
-- nothing to grade between them. alter196 gave them one star, which still showed up as a single
-- star under the icon and meant nothing there; zero now says outright that the series does not
-- grade its tournaments.
UPDATE series SET max_stars = 0 WHERE start_time = 0;

-- And the type goes away. It was never a property of a series - it follows from the two columns
-- above it, in SQL as plainly as in PHP:
--
--   a recurring tournament   start_time = 0
--   a series of tournaments  start_time <> 0 AND max_stars < 1
--   a season                 start_time <> 0 AND max_stars >= 1
--
-- So there is nothing to keep in step, and no row that can disagree with itself. See series_type()
-- and series_type_condition() in include/series.php.
--
-- A side effect of the derivation: every series that existed before alter195 was stored as a
-- series of tournaments, because that was the default the column was added with, yet all of them
-- grade their tournaments with stars - so they all read as seasons now, which is what their names
-- say they are. The ones that really are series of tournaments are marked by clearing their stars
-- in the editor, which is the change that means it.
ALTER TABLE series
  DROP COLUMN type;


-- The stage a player reached, and one ranking per stage of the series.
--
-- A series is ranked lexicographically: the deciding stage first, and points only inside it. So a
-- row of series_places now says which stage decided the place, and score is the points of that
-- stage rather than a sum over all of them. See calculate_series_task() in
-- complete_competitions.php.
--
-- Numbered the way series_tournaments.stage is - 0 the base stage, 1 the final, 2 the semifinal -
-- so the two columns can be read against each other and get_series_stage_name() labels both.
ALTER TABLE series_places
  ADD COLUMN stage INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN cut_stages INT(11) NOT NULL DEFAULT 0;

-- cut_stages is how many of the latest stages this ranking leaves out: zero is the whole series,
-- one is the table as it stood before the last stage was played, and so on. That is what the
-- "up to the final" / "up to the semifinal" switch reads, and the reason it counts backwards is
-- that zero then means the same thing for every series - the full table - whatever its ladder.
--
-- Which is also why no data migration is needed: every series has one stage today, so every
-- existing row is already a full ranking of a base stage, which is exactly what the two defaults
-- say. A series with n stages gets n rankings, n-1 of them partial.
ALTER TABLE series_places
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (user_id, series_id, cut_stages);

-- How a standings page reads the table: one series, one ranking of it, in place order.
ALTER TABLE series_places
  ADD KEY series_cut_place (series_id, cut_stages, place);

-- Superseded by the key above, which starts with the same column and so keeps the foreign key on
-- series_id indexed.
ALTER TABLE series_places
  DROP KEY series_id;


-- The finals of a series becomes a stage of it.
--
-- Until now a series named one of its tournaments as its finals, and the scoring answered by
-- leaving that tournament out of the count altogether. Stages say the same thing properly: the
-- series gets two of them, and the tournament sits in the second one, so its players stand above
-- everybody else by construction instead of being excluded.

-- Two series name a finals tournament they never had as a member, so nothing of it was counted in
-- them. Being the finals of a series is a stronger statement than membership, so the membership is
-- the thing that was missing - it is added here rather than the name being dropped.
--
-- Its star rating is the highest the series already gives any of its tournaments: a finals is
-- worth at least as much as the biggest tournament of the series, and every series stays on its
-- own scale. The value barely matters now anyway - a stage holds one finals tournament, so every
-- finalist is rated by the same number of stars and the order inside the stage comes from their
-- places. 1 is SERIES_STAGE_FINAL, see include/series.php.
INSERT INTO series_tournaments (series_id, tournament_id, stars, flags, stage)
  SELECT s.id, s.finals_id, COALESCE(m.max_stars, 1), 0, 1
    FROM series s
    LEFT JOIN (SELECT series_id, MAX(stars) AS max_stars FROM series_tournaments GROUP BY series_id) m
           ON m.series_id = s.id
    LEFT JOIN series_tournaments st ON st.series_id = s.id AND st.tournament_id = s.finals_id
    WHERE s.finals_id IS NOT NULL AND st.tournament_id IS NULL;

-- The base stage, 0, is where every other tournament of the series already is.
UPDATE series_tournaments st
  JOIN series s ON s.id = st.series_id AND s.finals_id = st.tournament_id
  SET st.stage = 1;

-- Two stages, and a recount.
--
-- 16 is SERIES_FLAG_DIRTY. The series are not recomputed here: the flag is what the recount job
-- looks for, and it rebuilds each of them from scratch on its next run. GREATEST leaves a series
-- that already declares a deeper ladder alone.
UPDATE series s
  JOIN series_tournaments st ON st.series_id = s.id AND st.tournament_id = s.finals_id
  SET s.stages = GREATEST(s.stages, 2), s.flags = s.flags | 16;


-- series.finals_id goes away.
--
-- It named one tournament of a series as its finals, and the only thing that ever came of the
-- name was that the scoring left that tournament out of the count. Stages say it properly: the
-- tournament sits in the final stage of the series, its players stand above everybody else by
-- construction, and the series tournament list prints the stage next to its name. alter200 moved
-- every series that had the column set over to that, so nothing reads it any more.
ALTER TABLE series
  DROP FOREIGN KEY series_final;

ALTER TABLE series
  DROP COLUMN finals_id;


-- Which stage of the series extra points belong to.
--
-- Extra points are not a competition of the series, so nothing said which stage they counted in
-- and the recount put them all in the base one. That is right for most of them - a point given for
-- the season as a whole belongs to the season - but not for all: points given for something that
-- happened in the final belong to the final, and in a series ranked stage by stage the difference
-- decides places.
--
-- Zero is the base stage, which is where every existing row already counted, so nothing has to be
-- moved. Numbered as series_tournaments.stage is - see include/series.php.
ALTER TABLE series_extra_points
  ADD COLUMN stage INT(11) NOT NULL DEFAULT 0;
