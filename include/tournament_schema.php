<?php

require_once __DIR__ . '/tournament.php';   // create_tournament_round(), delete_tournament_round(), get_round_name()
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/scoring.php';
require_once __DIR__ . '/seating.php';
require_once __DIR__ . '/table_hiding.php';

// A tournament schema: a ready-made way to set a tournament up, kept by a league in the table
// league_tournament_schemas (db/alter216.sql) and edited on its Tournament schemas page. A
// tournament takes one on the Scheme tab of its preparation page. What it takes is the content:
// nothing remembers which schema it was, and changing a schema afterwards changes no tournament.
// The rules, the scoring system and the normalizer are not kept in a schema but referred to: a
// tournament gets them as they are when it takes the schema, so a change made to them reaches the
// tournaments set up after it. The references are columns of the table, with foreign keys: what a
// schema refers to can not be deleted while it does - see check_not_in_tournament_schemas(). The
// rest is the json in the column "scheme". Outside the table - in the api and in the code - a
// schema is one object with all of it.
//
// The content is a json object:
//   long_term       - a long-term tournament, like a seasonal club championship. It has no rounds
//                     of its own: its games are played on the ordinary days of its club.
//   single_games    - for a long-term one: single games of the ordinary days can be assigned to it.
//   rounds          - the rounds of a tournament that is not long-term, in the order they are
//                     played: [{round, players, tables, games, weight}]. "round" is numbered the
//                     way events.round is - 0 the main round, 1 the final, 2 the semi-final and so
//                     on, see include/rounds.php. players, tables and games may be null - not
//                     said; weight is what the points of the round are multiplied by. group is the
//                     tournament group of the round - see tournament_group_names() - '' for none.
//   rules_id        - the named rules of a league, a record of the table rules. Or, when 0:
//   rules_league_id - the league whose rules (leagues.rules) are taken. Either way they are a rules
//                     filter: the tournament plays the default rules of its club put through it, see
//                     correct_rules(). When both are 0 it plays the rules of its club as they are.
//   scoring_id      - the scoring system; the tournament takes its current version.
//   scoring_options - {flags}: SCORING_OPTION_* - what of the scoring system is not used.
//   normalizer_id   - the scoring normalizer, 0 for none; the current version is taken.
//   awards          - the TOURNAMENT_FLAG_AWARD_* flags.
//   hide_table, hide_table_tour, hide_bonus, hide_bonus_tour - the hiding of the scoring table
//                     and of the bonus points, as api/ops/tournament.php op set_hiding takes it.

define('TOURNAMENT_SCHEMA_AWARDS_MASK',
	TOURNAMENT_FLAG_AWARD_MVP | TOURNAMENT_FLAG_AWARD_RED | TOURNAMENT_FLAG_AWARD_BLACK | TOURNAMENT_FLAG_AWARD_SHERIFF | TOURNAMENT_FLAG_AWARD_DON |
	TOURNAMENT_FLAG_AWARD_NO_SHERIFF_IN_RED | TOURNAMENT_FLAG_AWARD_NO_DON_IN_BLACK);

// The tournament flags a schema sets. The rest stay as the tournament has them.
define('TOURNAMENT_SCHEMA_FLAGS_MASK',
	TOURNAMENT_FLAG_LONG_TERM | TOURNAMENT_FLAG_SINGLE_GAME | TOURNAMENT_SCHEMA_AWARDS_MASK | TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);

// The order the rounds are played in: the main round first, then the playoff from its deepest
// rung down to the final.
function compare_rounds_by_play_order($a, $b)
{
	if ($a == $b)
	{
		return 0;
	}
	if ($a == 0)
	{
		return -1;
	}
	if ($b == 0)
	{
		return 1;
	}
	return $b - $a;
}

function _tournament_schema_count($value)
{
	if (is_null($value) || $value === '')
	{
		return NULL;
	}
	$value = (int)$value;
	return $value > 0 ? $value : NULL;
}

// The hiding the way op set_hiding stores it: a tour means something only with a mode hiding from
// a round, from the first tour is from the start of it, and from the start of the main round is
// everything.
function _tournament_schema_hiding($mode, $tour)
{
	$mode = (int)$mode;
	if ($mode < TOURNAMENT_HIDE_NONE || $mode > TOURNAMENT_HIDE_FROM_QUARTER_FINALS)
	{
		$mode = TOURNAMENT_HIDE_NONE;
	}
	$tour = (is_null(hiding_mode_round($mode)) || (int)$tour <= 1) ? 0 : (int)$tour;
	if ($mode == TOURNAMENT_HIDE_FROM_MAIN_ROUND && $tour == 0)
	{
		$mode = TOURNAMENT_HIDE_ALL;
	}
	return array($mode, $tour);
}

// A schema as it is kept: every field there, of its type, and the rounds in the order they are
// played. Takes the json or the object, and throws when the rounds do not make a tournament.
function normalize_tournament_schema($schema)
{
	if (is_string($schema))
	{
		$schema = json_decode($schema);
	}
	if (!is_object($schema))
	{
		throw new Exc(get_label('Invalid [0]', get_label('tournament schema')));
	}

	$s = new stdClass();
	$s->long_term = !empty($schema->long_term);
	$s->single_games = $s->long_term && !empty($schema->single_games);

	$s->rounds = array();
	if (!$s->long_term && isset($schema->rounds) && is_array($schema->rounds))
	{
		$numbers = array();
		foreach ($schema->rounds as $round)
		{
			$r = new stdClass();
			$r->round = isset($round->round) ? (int)$round->round : 0;
			if ($r->round < 0 || isset($numbers[$r->round]))
			{
				throw new Exc(get_label('Invalid [0]', get_label('rounds')));
			}
			$numbers[$r->round] = true;
			$r->players = _tournament_schema_count(isset($round->players) ? $round->players : NULL);
			$r->tables = _tournament_schema_count(isset($round->tables) ? $round->tables : NULL);
			$r->games = _tournament_schema_count(isset($round->games) ? $round->games : NULL);
			if (!is_null($r->players) && !is_null($r->tables) && $r->tables * 10 > $r->players)
			{
				throw new Exc(get_label('[0] players can not play at [1] tables.', $r->players, $r->tables));
			}
			$r->weight = isset($round->weight) ? (float)$round->weight : 1;
			if ($r->weight <= 0)
			{
				$r->weight = 1;
			}
			$r->group = normalize_tournament_group(isset($round->group) ? $round->group : NULL, $r->round);
			$s->rounds[] = $r;
		}
		// The playoff goes all the way down to the final: no rung of it is left out.
		$playoff = count($numbers) - (isset($numbers[0]) ? 1 : 0);
		for ($i = 1; $i <= $playoff; ++$i)
		{
			if (!isset($numbers[$i]))
			{
				throw new Exc(get_label('Invalid [0]', get_label('rounds')));
			}
		}
		usort($s->rounds, function($a, $b) { return compare_rounds_by_play_order($a->round, $b->round); });
	}

	$s->rules_id = isset($schema->rules_id) ? max(0, (int)$schema->rules_id) : 0;
	$s->rules_league_id = ($s->rules_id == 0 && isset($schema->rules_league_id)) ? max(0, (int)$schema->rules_league_id) : 0;

	$s->scoring_id = isset($schema->scoring_id) ? (int)$schema->scoring_id : 0;
	$s->scoring_options = new stdClass();
	if (isset($schema->scoring_options->flags))
	{
		$s->scoring_options->flags = (int)$schema->scoring_options->flags & (SCORING_OPTION_NO_NIGHT_KILLS | SCORING_OPTION_NO_GAME_DIFFICULTY);
	}
	$s->normalizer_id = isset($schema->normalizer_id) ? max(0, (int)$schema->normalizer_id) : 0;
	$s->awards = isset($schema->awards) ? (int)$schema->awards & TOURNAMENT_SCHEMA_AWARDS_MASK : 0;

	list($s->hide_table, $s->hide_table_tour) = _tournament_schema_hiding(
		isset($schema->hide_table) ? $schema->hide_table : TOURNAMENT_HIDE_NONE,
		isset($schema->hide_table_tour) ? $schema->hide_table_tour : 0);
	list($s->hide_bonus, $s->hide_bonus_tour) = _tournament_schema_hiding(
		isset($schema->hide_bonus) ? $schema->hide_bonus : TOURNAMENT_HIDE_NONE,
		isset($schema->hide_bonus_tour) ? $schema->hide_bonus_tour : 0);
	return $s;
}

// The columns of league_tournament_schemas a schema is in, as tournament_schema_from_row() takes them.
define('TOURNAMENT_SCHEMA_COLUMNS', 'scheme, rules_id, rules_league_id, scoring_id, normalizer_id');

// A schema as it is read from the columns TOURNAMENT_SCHEMA_COLUMNS.
function tournament_schema_from_row($scheme, $rules_id, $rules_league_id, $scoring_id, $normalizer_id)
{
	$schema = json_decode($scheme);
	if (!is_object($schema))
	{
		$schema = new stdClass();
	}
	$schema->rules_id = (int)$rules_id;
	$schema->rules_league_id = (int)$rules_league_id;
	$schema->scoring_id = (int)$scoring_id;
	$schema->normalizer_id = (int)$normalizer_id;
	return normalize_tournament_schema($schema);
}

// A normalized schema as it is written to the columns TOURNAMENT_SCHEMA_COLUMNS: the json of what is
// not a reference, then the references, NULL where there is none.
function tournament_schema_to_row($s)
{
	$json = clone $s;
	unset($json->rules_id, $json->rules_league_id, $json->scoring_id, $json->normalizer_id);
	return array(
		json_encode($json),
		$s->rules_id > 0 ? $s->rules_id : NULL,
		$s->rules_league_id > 0 ? $s->rules_league_id : NULL,
		$s->scoring_id,
		$s->normalizer_id > 0 ? $s->normalizer_id : NULL);
}

// Stops deleting rules, a scoring system or a normalizer that tournament schemas refer to, naming
// them. $column is the column of league_tournament_schemas referring to it: rules_id, scoring_id
// or normalizer_id.
function check_not_in_tournament_schemas($column, $id, $object_name)
{
	$names = '';
	$query = new DbQuery(
		'SELECT l.name, s.name FROM league_tournament_schemas s JOIN leagues l ON l.id = s.league_id' .
		' WHERE s.' . $column . ' = ? ORDER BY l.name, s.name', $id);
	while ($row = $query->next())
	{
		$names .= '<br>' . htmlspecialchars($row[0] . ': ' . $row[1], ENT_QUOTES);
	}
	if ($names != '')
	{
		throw new Exc(get_label('Unable to delete [0] because tournament schemas use it:', $object_name) . $names);
	}
}

// A schema of a league, ready to be stored: normalized, and referring to rules, a scoring system
// and a normalizer that are there. A schema without a scoring system takes the default of the league.
function league_tournament_schema($league_id, $schema)
{
	$s = normalize_tournament_schema($schema);
	if ($s->scoring_id <= 0)
	{
		list($s->scoring_id) = Db::record(get_label('league'), 'SELECT scoring_id FROM leagues WHERE id = ?', $league_id);
		$s->scoring_id = (int)$s->scoring_id;
	}
	Db::record(get_label('scoring system'), 'SELECT id FROM scorings WHERE id = ?', $s->scoring_id);
	if ($s->normalizer_id > 0)
	{
		Db::record(get_label('scoring normalizer'), 'SELECT id FROM normalizers WHERE id = ?', $s->normalizer_id);
	}
	tournament_schema_rules_filter($s);
	return $s;
}

// The rules filter a schema refers to, read when it is used, so that it is what the rules are now.
function tournament_schema_rules_filter($s)
{
	if ($s->rules_id > 0)
	{
		list($filter) = Db::record(get_label('rules'), 'SELECT rules FROM rules WHERE id = ? AND league_id IS NOT NULL', $s->rules_id);
	}
	else if ($s->rules_league_id > 0)
	{
		list($filter) = Db::record(get_label('league'), 'SELECT rules FROM leagues WHERE id = ?', $s->rules_league_id);
	}
	else
	{
		return new stdClass();
	}
	$filter = json_decode($filter);
	return is_object($filter) ? $filter : new stdClass();
}

// The tournament groups a round can be in. The rounds of a group are scored together: the stats the
// scoring needs - like the first night kill rate for the compensation (Ci) - are counted over the
// games of the group only. See the groups of tournament_scores() in include/scoring.php.
function tournament_group_names()
{
	return array(
		'' => '',
		'pre' => get_label('preliminary rounds'),
		'main' => get_label('main rounds'),
		'final' => get_label('final rounds'));
}

// The group of a round: one of tournament_group_names(), or, when it is not said (NULL), the usual
// one - the final apart from the rounds before it.
function normalize_tournament_group($group, $round_num)
{
	if (is_null($group))
	{
		return ($round_num == 1) ? 'final' : 'main';
	}
	$group = (string)$group;
	return array_key_exists($group, tournament_group_names()) ? $group : '';
}

// What a round of the tournament is scored with: the options of the tournament, the group of the
// round - by default the final is scored apart from the rounds before it - and the weight of its points.
function tournament_round_scoring_options($round_num, $tournament_options, $weight, $group = NULL)
{
	$ops = new stdClass();
	$group = normalize_tournament_group($group, $round_num);
	if ($group != '')
	{
		$ops->group = $group;
	}
	if (isset($tournament_options->flags))
	{
		$ops->flags = (int)$tournament_options->flags;
	}
	if (abs($weight - 1) > 0.0001)
	{
		$ops->weight = $weight;
	}
	return $ops;
}

// Sets the tournament up the way the schema says. A tournament that has been played in can not
// take a schema: its rounds and its scoring are not to be rebuilt under the games.
//
// The rounds the schema has are made, keeping a round the tournament already has by the same
// number, together with its broadcasts and its seating while the scheme of it holds. The rounds
// it does not have are deleted. A long-term tournament has no rounds of its own and its events
// are left as they are - they are the ordinary days of its club - only playing by the new rules
// and scoring like the rest of it.
function apply_tournament_schema($tournament_id, $schema)
{
	$s = normalize_tournament_schema($schema);

	list($club_id, $address_id, $start, $duration, $langs, $notes, $fee, $currency_id, $old_flags, $num_players, $club_rules) = Db::record(get_label('tournament'),
		'SELECT t.club_id, t.address_id, t.start_time, t.duration, t.langs, t.notes, t.fee, t.currency_id, t.flags, t.num_players, c.rules' .
		' FROM tournaments t JOIN clubs c ON c.id = t.club_id WHERE t.id = ?', $tournament_id);
	$old_flags = (int)$old_flags;

	list($games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE tournament_id = ?', $tournament_id);
	if ($games_count > 0)
	{
		throw new Exc(get_label('Unable to apply a tournament schema because the tournament has already started.'));
	}

	$rules_code = correct_rules(upgrade_rules_code($club_rules), tournament_schema_rules_filter($s));

	if ($s->scoring_id <= 0)
	{
		throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
	}
	list($scoring_version) = Db::record(get_label('scoring system'), 'SELECT MAX(version) FROM scoring_versions WHERE scoring_id = ?', $s->scoring_id);
	$normalizer_id = NULL;
	$normalizer_version = NULL;
	if ($s->normalizer_id > 0)
	{
		$normalizer_id = $s->normalizer_id;
		list($normalizer_version) = Db::record(get_label('scoring normalizer'), 'SELECT MAX(version) FROM normalizer_versions WHERE normalizer_id = ?', $normalizer_id);
	}

	$flags = $old_flags & ~(TOURNAMENT_SCHEMA_FLAGS_MASK | TOURNAMENT_FLAG_FINISHED);
	if ($s->long_term)
	{
		$flags |= TOURNAMENT_FLAG_LONG_TERM;
		if ($s->single_games)
		{
			$flags |= TOURNAMENT_FLAG_SINGLE_GAME;
		}
	}
	$flags |= $s->awards | ($s->hide_table << TOURNAMENT_HIDE_TABLE_MASK_OFFSET) | ($s->hide_bonus << TOURNAMENT_HIDE_BONUS_MASK_OFFSET);

	$scoring_options = json_encode($s->scoring_options);
	$end = $start + $duration;

	if ($s->long_term)
	{
		$query = new DbQuery('SELECT id, scoring_options FROM events WHERE tournament_id = ?', $tournament_id);
		while ($row = $query->next())
		{
			list($event_id, $event_options) = $row;
			$event_options = json_decode($event_options);
			if (!is_object($event_options))
			{
				$event_options = new stdClass();
			}
			unset($event_options->flags);
			if (isset($s->scoring_options->flags))
			{
				$event_options->flags = $s->scoring_options->flags;
			}
			Db::exec(get_label('event'), 'UPDATE events SET rules = ?, scoring_id = ?, scoring_version = ?, scoring_options = ? WHERE id = ?',
				$rules_code, $s->scoring_id, $scoring_version, json_encode($event_options), $event_id);
		}
	}
	else
	{
		$existing = array();
		$query = new DbQuery('SELECT id, round, players, tables, games FROM events WHERE tournament_id = ? ORDER BY id', $tournament_id);
		while ($row = $query->next())
		{
			list($event_id, $round_num) = $row;
			$round_num = (int)$round_num;
			if (isset($existing[$round_num]))
			{
				delete_tournament_round($event_id); // a second event of the same round is no round
			}
			else
			{
				$existing[$round_num] = $row;
			}
		}

		$num_players = 0;
		foreach ($s->rounds as $r)
		{
			$ops = json_encode(tournament_round_scoring_options($r->round, $s->scoring_options, $r->weight, $r->group));
			if (isset($existing[$r->round]))
			{
				list($event_id, $round_num, $old_players, $old_tables, $old_games) = $existing[$r->round];
				Db::exec(get_label('round'), 'UPDATE events SET rules = ?, scoring_id = ?, scoring_version = ?, scoring_options = ?, players = ?, tables = ?, games = ? WHERE id = ?',
					$rules_code, $s->scoring_id, $scoring_version, $ops, $r->players, $r->tables, $r->games, $event_id);
				clear_event_seating_on_scheme_change($event_id, array($old_players, $old_tables, $old_games), array($r->players, $r->tables, $r->games));
				unset($existing[$r->round]);
			}
			else
			{
				create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $s->scoring_id, $scoring_version, $ops, $tournament_id, $rules_code, $r->round, $flags, $r->players, $r->tables, $r->games);
			}
			if ($r->round == 0 && !is_null($r->players))
			{
				$num_players = $r->players;
			}
		}
		foreach ($existing as $row)
		{
			delete_tournament_round($row[0]);
		}
	}

	Db::exec(get_label('tournament'),
		'UPDATE tournaments SET rules = ?, scoring_id = ?, scoring_version = ?, normalizer_id = ?, normalizer_version = ?, scoring_options = ?, flags = ?, hide_table_tour = ?, hide_bonus_tour = ?, num_players = ? WHERE id = ?',
		$rules_code, $s->scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $flags, $s->hide_table_tour, $s->hide_bonus_tour, $num_players, $tournament_id);
	Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
	Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);

	$log_details = new stdClass();
	$log_details->schema = $s;
	db_log(LOG_OBJECT_TOURNAMENT, 'schema applied', $log_details, $tournament_id, $club_id);
}

// The rounds of a schema in a few words: "main round, semi-final, final", or that it is a long-term
// tournament.
function tournament_schema_summary($schema)
{
	if (is_string($schema))
	{
		$schema = json_decode($schema);
	}
	if (!empty($schema->long_term))
	{
		return get_label('long-term tournament');
	}
	$names = array();
	if (isset($schema->rounds) && is_array($schema->rounds))
	{
		foreach ($schema->rounds as $r)
		{
			$names[] = get_round_name((int)$r->round);
		}
	}
	return implode(', ', $names);
}

// The leagues a tournament of a club takes a schema from: the global ones and those the club is in -
// a membership still waiting to be accepted counts too - that are not closed. Each with its schemas:
// [league_id => {id, name, schemas => [{id, name}]}], by name.
function get_tournament_schema_leagues($club_id)
{
	$leagues = array();
	$query = new DbQuery(
		'SELECT DISTINCT l.id, l.name FROM leagues l' .
		' LEFT OUTER JOIN league_clubs lc ON lc.league_id = l.id AND lc.club_id = ?' .
		' WHERE (l.flags & ' . LEAGUE_FLAG_CLOSED . ') = 0 AND (lc.club_id IS NOT NULL OR (l.flags & ' . LEAGUE_FLAG_GLOBAL . ') <> 0)' .
		' ORDER BY l.name', $club_id);
	while ($row = $query->next())
	{
		$l = new stdClass();
		list($l->id, $l->name) = $row;
		$l->id = (int)$l->id;
		$l->schemas = array();
		$leagues[$l->id] = $l;
	}
	if (count($leagues) > 0)
	{
		$query = new DbQuery('SELECT id, league_id, name FROM league_tournament_schemas WHERE league_id IN (' . implode(',', array_keys($leagues)) . ') ORDER BY name');
		while ($row = $query->next())
		{
			$sc = new stdClass();
			list($sc->id, $league_id, $sc->name) = $row;
			$sc->id = (int)$sc->id;
			$leagues[(int)$league_id]->schemas[] = $sc;
		}
	}
	return $leagues;
}

?>
