<?php

require_once '../../include/api.php';
require_once '../../include/tournament_schema.php';

define('CURRENT_VERSION', 0);

// The tournament schemas of a league - the table league_tournament_schemas, see db/alter216.sql
// and include/tournament_schema.php. A tournament takes one with op apply_schema of
// api/ops/tournament.php.
class ApiPage extends OpsApiPageBase
{
	// A name is needed, and the league has no other schema by that name.
	private static function check_name($league_id, $name, $schema_id = 0)
	{
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('tournament schema name')));
		}
		$query = new DbQuery('SELECT id FROM league_tournament_schemas WHERE league_id = ? AND name = ? AND id <> ?', $league_id, $name, $schema_id);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Tournament schema name'), $name));
		}
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		$name = trim(get_required_param('name'));

		Db::begin();
		self::check_name($league_id, $name);
		$schema = league_tournament_schema($league_id, get_optional_param('schema', '{}'));
		list($scheme, $rules_id, $rules_league_id, $scoring_id, $normalizer_id) = tournament_schema_to_row($schema);
		Db::exec(get_label('tournament schema'),
			'INSERT INTO league_tournament_schemas (league_id, name, ' . TOURNAMENT_SCHEMA_COLUMNS . ') VALUES (?, ?, ?, ?, ?, ?, ?)',
			$league_id, $name, $scheme, $rules_id, $rules_league_id, $scoring_id, $normalizer_id);
		list($schema_id) = Db::record(get_label('tournament schema'), 'SELECT LAST_INSERT_ID()');

		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->schema = $schema;
		db_log(LOG_OBJECT_TOURNAMENT_SCHEMA, 'created', $log_details, $schema_id, NULL, $league_id);
		Db::commit();

		$this->response['schema_id'] = (int)$schema_id;
	}

	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Create a tournament schema of a league.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('name', 'Schema name. Must be unique in the league.');
		$param = $help->request_param('schema', 'The schema, a json object.', 'an empty one: a tournament that is not long-term, with no rounds, the default scoring system of the league and nothing hidden.');
		ApiPage::schema_help($param);
		$help->response_param('schema_id', 'Newly created schema id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$schema_id = (int)get_required_param('schema_id');

		Db::begin();
		$row = Db::record(get_label('tournament schema'), 'SELECT league_id, name, ' . TOURNAMENT_SCHEMA_COLUMNS . ' FROM league_tournament_schemas WHERE id = ?', $schema_id);
		$league_id = (int)$row[0];
		$old_name = $row[1];
		$old_schema = tournament_schema_from_row($row[2], $row[3], $row[4], $row[5], $row[6]);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);

		$name = trim(get_optional_param('name', $old_name));
		self::check_name($league_id, $name, $schema_id);
		$schema = league_tournament_schema($league_id, get_optional_param('schema', $old_schema));
		list($scheme, $rules_id, $rules_league_id, $scoring_id, $normalizer_id) = tournament_schema_to_row($schema);
		Db::exec(get_label('tournament schema'),
			'UPDATE league_tournament_schemas SET name = ?, scheme = ?, rules_id = ?, rules_league_id = ?, scoring_id = ?, normalizer_id = ? WHERE id = ?',
			$name, $scheme, $rules_id, $rules_league_id, $scoring_id, $normalizer_id, $schema_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($name != $old_name)
			{
				$log_details->name = $name;
			}
			if (json_encode($schema) != json_encode($old_schema))
			{
				$log_details->schema = $schema;
			}
			db_log(LOG_OBJECT_TOURNAMENT_SCHEMA, 'changed', $log_details, $schema_id, NULL, $league_id);
		}
		Db::commit();
	}

	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Change a tournament schema of a league. The tournaments that took it stay as they are.');
		$help->request_param('schema_id', 'Schema id.');
		$help->request_param('name', 'Schema name. Must be unique in the league.', 'remains the same.');
		$param = $help->request_param('schema', 'The schema, a json object. It is replaced as a whole.', 'remains the same.');
		ApiPage::schema_help($param);
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$schema_id = (int)get_required_param('schema_id');

		Db::begin();
		list($league_id, $name) = Db::record(get_label('tournament schema'), 'SELECT league_id, name FROM league_tournament_schemas WHERE id = ?', $schema_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		Db::exec(get_label('tournament schema'), 'DELETE FROM league_tournament_schemas WHERE id = ?', $schema_id);

		$log_details = new stdClass();
		$log_details->name = $name;
		db_log(LOG_OBJECT_TOURNAMENT_SCHEMA, 'deleted', $log_details, $schema_id, NULL, $league_id);
		Db::commit();
	}

	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Delete a tournament schema of a league. The tournaments that took it stay as they are.');
		$help->request_param('schema_id', 'Schema id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// copy
	//-------------------------------------------------------------------------------------------------------
	// All the schemas of another league, into this one. Where this league already has a schema by
	// the same name, its own one stays and the other is not copied.
	function copy_op()
	{
		$league_id = (int)get_required_param('league_id');
		$source_league_id = (int)get_required_param('source_league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		if ($source_league_id == $league_id)
		{
			throw new Exc(get_label('Please select another league.'));
		}
		Db::record(get_label('league'), 'SELECT id FROM leagues WHERE id = ?', $source_league_id);

		Db::begin();
		$existing = array();
		$query = new DbQuery('SELECT name FROM league_tournament_schemas WHERE league_id = ?', $league_id);
		while ($row = $query->next())
		{
			$existing[mb_strtolower($row[0], 'UTF-8')] = true;
		}

		$copied = array();
		$skipped = array();
		// The copies refer to the same rules, scoring system and normalizer as the schemas they are
		// copied from - those of the other league among them.
		$query = new DbQuery('SELECT name, ' . TOURNAMENT_SCHEMA_COLUMNS . ' FROM league_tournament_schemas WHERE league_id = ? ORDER BY name', $source_league_id);
		while ($row = $query->next())
		{
			list($name, $scheme, $rules_id, $rules_league_id, $scoring_id, $normalizer_id) = $row;
			if (isset($existing[mb_strtolower($name, 'UTF-8')]))
			{
				$skipped[] = $name;
				continue;
			}
			Db::exec(get_label('tournament schema'),
				'INSERT INTO league_tournament_schemas (league_id, name, ' . TOURNAMENT_SCHEMA_COLUMNS . ') VALUES (?, ?, ?, ?, ?, ?, ?)',
				$league_id, $name, $scheme, $rules_id, $rules_league_id, $scoring_id, $normalizer_id);
			$existing[mb_strtolower($name, 'UTF-8')] = true;
			$copied[] = $name;
		}

		if (count($copied) > 0)
		{
			$log_details = new stdClass();
			$log_details->source_league_id = $source_league_id;
			$log_details->copied = $copied;
			db_log(LOG_OBJECT_TOURNAMENT_SCHEMA, 'copied', $log_details, NULL, NULL, $league_id);
		}
		Db::commit();

		$this->response['copied'] = $copied;
		$this->response['skipped'] = $skipped;
	}

	function copy_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Copy all the tournament schemas of another league - any league - into this one. Where this league already has a schema by the same name, its own one stays and the other one is not copied. A copied schema keeps the scoring system and the normalizer it had, those of the other league included.');
		$help->request_param('league_id', 'The league the schemas are copied to.');
		$help->request_param('source_league_id', 'The league the schemas are copied from.');
		$help->response_param('copied', 'The names of the schemas copied.');
		$help->response_param('skipped', 'The names of the schemas not copied, because the league already has a schema by that name.');
		return $help;
	}

	static function schema_help($param)
	{
		$param->sub_param('long_term', 'true for a long-term tournament, like a seasonal club championship. It has no rounds: its games are played on the ordinary days of its club.', 'false.');
		$param->sub_param('single_games', 'For a long-term tournament: true when single games of the ordinary days can be assigned to it.', 'false.');
		$rounds = $param->sub_param('rounds', 'Array of the rounds of a tournament that is not long-term. The playoff goes all the way down to the final: when there is a semi-final, there is a final.', 'no rounds.');
			$rounds->sub_param('round', 'The round: 0 - the main round, 1 - the final, 2 - the semi-final, 3 - the quarter-final, and so on.');
			$rounds->sub_param('players', 'Number of players.', 'not said.');
			$rounds->sub_param('tables', 'Number of tables. No more than one for every ten players.', 'not said.');
			$rounds->sub_param('games', 'Number of games each player plays.', 'not said.');
			$rounds->sub_param('weight', 'What the points of the round are multiplied by.', '1.');
			$rounds->sub_param('group', 'The tournament group of the round: "pre", "main", "final", or "" for none. The stats the scoring needs, like the first night kill rate for the compensation (Ci), are counted over each group apart.', '"final" for the final, "main" for the other rounds.');
		$param->sub_param('rules_id', 'Id of the named rules of a league - see <a href="rules.php?help">rules.php</a>. They are a rules filter: the tournament plays the default rules of its club put through it. The schema refers to them, so the tournament gets them as they are when it takes the schema.', '0: rules_league_id is used.');
		$param->sub_param('rules_league_id', 'When rules_id is 0: the league whose rules - see <a href="league.php?help">league.php</a> - are the filter, taken the same way.', '0: the rules of the club as they are.');
		$param->sub_param('scoring_id', 'Scoring system id. The tournament takes its current version.', 'the default scoring system of the league.');
		$param->sub_param('scoring_options', 'Json object {flags}: ' . SCORING_OPTION_NO_NIGHT_KILLS . ' - the first night kill rate factor is not used; ' . SCORING_OPTION_NO_GAME_DIFFICULTY . ' - the game difficulty factor is not used.', 'everything is used.');
		$param->sub_param('normalizer_id', 'Scoring normalizer id, 0 for none. The tournament takes its current version.', '0.');
		$param->sub_param('awards', 'Special awards, a combination of: ' . TOURNAMENT_FLAG_AWARD_MVP . ' - MVP; ' . TOURNAMENT_FLAG_AWARD_RED . ' - best red player; ' . TOURNAMENT_FLAG_AWARD_BLACK . ' - best black player; ' . TOURNAMENT_FLAG_AWARD_SHERIFF . ' - best sheriff; ' . TOURNAMENT_FLAG_AWARD_DON . ' - best don; ' . TOURNAMENT_FLAG_AWARD_NO_SHERIFF_IN_RED . ' - the sheriff games are left out of the best red player; ' . TOURNAMENT_FLAG_AWARD_NO_DON_IN_BLACK . ' - the don games are left out of the best black player.', '0.');
		$param->sub_param('hide_table', 'Where the scoring table is hidden from, as <q>table</q> of op set_hiding in <a href="tournament.php?help">tournament.php</a>.', 'shown.');
		$param->sub_param('hide_table_tour', 'The tour it is hidden from, as <q>table_tour</q> of op set_hiding.', '0.');
		$param->sub_param('hide_bonus', 'Where the bonus points are hidden from, the same way.', 'shown.');
		$param->sub_param('hide_bonus_tour', 'The tour they are hidden from.', '0.');
	}
}

$page = new ApiPage();
$page->run('Tournament Schema Operations', CURRENT_VERSION);

?>
