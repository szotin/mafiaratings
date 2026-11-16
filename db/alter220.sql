-- The rounds of tournaments named by the site get their names in every language. A round is named
-- after its number - main round, final, semi-final... - see get_round_name() in include/rounds.php,
-- and until now it got the name in the language of whoever made it, so most of them read
-- "основной раунд" to everybody. Now get_round_name_id() in include/tournament.php gives a round the
-- set holding the name of its number in every language, taken from the labels, and the rounds of one
-- number share it.
--
-- The rounds that have such a name now get that set: a round whose name is the name of its number in
-- any language, in any case ("Финал" too), or the old misspelt "quoter-final" of the forms. A round
-- somebody named otherwise - "Финальный раунд", "Saturday Mafia" - keeps its name.
--
-- The rows are made the way Names::from_languages() makes them: the English name for all languages but
-- the ones that have their own (16777215 & ~2 & ~4 = 16777209), Russian (2) and Ukrainian (4).

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);


-- round 0: main round / основной раунд / основний раунд
INSERT INTO names (id, langs, name) VALUES (@base + 1, 16777209, 'main round'), (@base + 1, 2, 'основной раунд'), (@base + 1, 4, 'основний раунд');
UPDATE events e JOIN names n ON n.id = e.name_id SET e.name_id = @base + 1
  WHERE e.tournament_id IS NOT NULL AND e.round = 0 AND TRIM(n.name) IN ('main round', 'основной раунд', 'основний раунд');

-- round 1: final / финал / фінал
INSERT INTO names (id, langs, name) VALUES (@base + 2, 16777209, 'final'), (@base + 2, 2, 'финал'), (@base + 2, 4, 'фінал');
UPDATE events e JOIN names n ON n.id = e.name_id SET e.name_id = @base + 2
  WHERE e.tournament_id IS NOT NULL AND e.round = 1 AND TRIM(n.name) IN ('final', 'финал', 'фінал');

-- round 2: semi-final / полу-финал / півфінал
INSERT INTO names (id, langs, name) VALUES (@base + 3, 16777209, 'semi-final'), (@base + 3, 2, 'полу-финал'), (@base + 3, 4, 'півфінал');
UPDATE events e JOIN names n ON n.id = e.name_id SET e.name_id = @base + 3
  WHERE e.tournament_id IS NOT NULL AND e.round = 2 AND TRIM(n.name) IN ('semi-final', 'полу-финал', 'півфінал');

-- round 3: quarter-final / четверть-финал / чвертьфінал
INSERT INTO names (id, langs, name) VALUES (@base + 4, 16777209, 'quarter-final'), (@base + 4, 2, 'четверть-финал'), (@base + 4, 4, 'чвертьфінал');
UPDATE events e JOIN names n ON n.id = e.name_id SET e.name_id = @base + 4
  WHERE e.tournament_id IS NOT NULL AND e.round = 3 AND TRIM(n.name) IN ('quarter-final', 'четверть-финал', 'чвертьфінал', 'quoter-final');

-- round 4: 1/8 final / 1/8 финала / 1/8 фіналу
INSERT INTO names (id, langs, name) VALUES (@base + 5, 16777209, '1/8 final'), (@base + 5, 2, '1/8 финала'), (@base + 5, 4, '1/8 фіналу');
UPDATE events e JOIN names n ON n.id = e.name_id SET e.name_id = @base + 5
  WHERE e.tournament_id IS NOT NULL AND e.round = 4 AND TRIM(n.name) IN ('1/8 final', '1/8 финала', '1/8 фіналу');

-- round 5: 1/16 final / 1/16 финала / 1/16 фіналу
INSERT INTO names (id, langs, name) VALUES (@base + 6, 16777209, '1/16 final'), (@base + 6, 2, '1/16 финала'), (@base + 6, 4, '1/16 фіналу');
UPDATE events e JOIN names n ON n.id = e.name_id SET e.name_id = @base + 6
  WHERE e.tournament_id IS NOT NULL AND e.round = 5 AND TRIM(n.name) IN ('1/16 final', '1/16 финала', '1/16 фіналу');

-- round 6: 1/32 final / 1/32 финала / 1/32 фіналу
INSERT INTO names (id, langs, name) VALUES (@base + 7, 16777209, '1/32 final'), (@base + 7, 2, '1/32 финала'), (@base + 7, 4, '1/32 фіналу');
UPDATE events e JOIN names n ON n.id = e.name_id SET e.name_id = @base + 7
  WHERE e.tournament_id IS NOT NULL AND e.round = 6 AND TRIM(n.name) IN ('1/32 final', '1/32 финала', '1/32 фіналу');
