-- Let a series say how many stars it hands out.
--
-- The star rating a tournament or a subseries gets when it is entered into a series was fixed
-- at five everywhere the rating control was drawn. A series now carries its own maximum, from
-- one to ten, and the control follows it.
--
-- Five is what every existing series was using, so that is the default. A recurring tournament
-- is the exception: all of its tournaments are the same event happening again, so there is
-- nothing to grade between them and it always hands out exactly one star.
ALTER TABLE series
  ADD COLUMN max_stars INT(11) NOT NULL DEFAULT 5;

-- 2 is SERIES_TYPE_RECURRING.
UPDATE series SET max_stars = 1 WHERE type = 2;
