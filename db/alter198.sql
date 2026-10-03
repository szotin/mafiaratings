-- Clubs invited to run tournaments in a series.
--
-- Until now the clubs that could enter a tournament into a series were fixed by ownership: the
-- club owning the series, or every club of the league owning it. A series can now invite other
-- clubs as well.
--
-- Being in this table grants one thing only - the right to enter a tournament of that club into
-- the series. It is not a permission over the series: the managers of an invited club cannot edit
-- it, cancel it, finish it or change its scoring.
--
-- No approval workflow, unlike league_clubs: whoever manages the series decides, and the invited
-- club has nothing to accept.
CREATE TABLE series_clubs (
  series_id INT(11) NOT NULL,
  club_id INT(11) NOT NULL,

  PRIMARY KEY (series_id, club_id),
  KEY club_id (club_id, series_id),

  CONSTRAINT series_clubs_series FOREIGN KEY (series_id) REFERENCES series (id),
  CONSTRAINT series_clubs_club FOREIGN KEY (club_id) REFERENCES clubs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
