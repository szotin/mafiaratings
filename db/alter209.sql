-- The strength of the field of a series.
--
-- A tournament keeps on its own row what its players were rated, and the gaining formulas measure
-- a competition with it: ratingSum20() over the twenty strongest of them is what turns a star into
-- points. A series kept nothing of the kind, so a subseries entered into another series came to
-- the formula as a field of nothing but thousand-rated players - a five-star subseries paid its
-- winner the least a five-star competition can pay, ten points, however strong the field really
-- was.
--
-- The four columns are the ones tournaments carries, with the same meaning: the ratings of
-- everybody the series counts, the ratings of its twenty strongest, and the distance travelled to
-- its competitions with the guest coefficient of it, both summed over them.
ALTER TABLE series
  ADD COLUMN rating_sum DOUBLE NOT NULL DEFAULT 0,
  ADD COLUMN rating_sum_20 DOUBLE NOT NULL DEFAULT 0,
  ADD COLUMN traveling_distance DOUBLE NOT NULL DEFAULT 0,
  ADD COLUMN guest_coeff DOUBLE NOT NULL DEFAULT 0;

-- What one player was rated in the series: the rating they carried at the latest of its
-- competitions they counted in. A series is played over a span rather than at a moment, so unlike
-- a tournament it cannot freeze one rating for everybody. It is the column the series above reads
-- to measure its own field when a subseries is one of its competitions.
ALTER TABLE series_places
  ADD COLUMN rating DOUBLE NOT NULL DEFAULT 0;

-- Nothing fills the new columns but the recount, so every series is asked for one. 16 is
-- SERIES_FLAG_DIRTY - see include/constants.php.
UPDATE series SET flags = flags | 16;
