<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';
require_once '../../include/names.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $club_id, $id = -1)
	{
		global $_profile;

		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('scoring system name')));
		}

		check_name($name, get_label('scoring system name'));
		
		if (!is_null($club_id))
		{
			$query = new DbQuery('SELECT name FROM scorings WHERE name = ? AND (club_id = ? OR club_id IS NULL)', $name, $club_id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM scorings WHERE name = ? AND club_id IS NULL', $name);
		}
		
		if ($id > 0)
		{
			$query->add(' AND id <> ?', $id);
		}
		
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Scoring system name'), $name));
		}
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$league_id = (int)get_optional_param('league_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if ($league_id > 0)
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
			else
			{
				$league_id = NULL;
			}
		}
		else if ($league_id > 0)
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			$club_id = NULL;
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			$club_id = NULL;
			$league_id = NULL;
		}
		
		$copy_id = (int)get_optional_param('copy_id', -1);
		$name = trim(get_required_param('name'));
		$scoring = get_optional_param('scoring', '{}');
		if (!is_string($scoring))
		{
			$scoring = json_encode($scoring);
		}
		
		Db::begin();
		$this->check_name($name, $club_id, $league_id);
		
		Db::exec(get_label('scoring system'), 'INSERT INTO scorings (club_id, league_id, name, version) VALUES (?, ?, ?, NULL)', $club_id, $league_id, $name);
		list ($scoring_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		
		if ($copy_id > 0)
		{
			$query = new DbQuery('SELECT scoring FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $copy_id);
			if ($row = $query->next())
			{
				list ($scoring) = $row;
			}
		}
		
		Db::exec(get_label('scoring system'), 'INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (?, 1, ?)', $scoring_id, $scoring);
		Db::exec(get_label('scoring system'), 'UPDATE scorings SET version = 1', $club_id, $league_id, $name);
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->version = 1;
		$log_details->scoring = $scoring;
		db_log(LOG_OBJECT_SCORING_SYSTEM, 'created', $log_details, $scoring_id, $club_id, $league_id);
		Db::commit();
		$this->response['scoring_id'] = (int)$scoring_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create scoring system in the club. Or create a global scoring system. "Global" means that it can be used in any club. Creating global scoring system requires <em>admin</em> permissions.');
		$help->request_param('club_id', 'Club id.', 'global scoring system is created.');
		$help->request_param('league_id', 'League id.', 'global scoring system is created.');
		$help->request_param('name', 'Scoring system name.');
		$help->request_param('copy_id', 'Id of the existing scoring system to be used as an initial template. If set, the latest version of scoring rules from this system are copied to the new system.', 'parameter <q>scoring</q> is used to create the new system.');
		api_scoring_help($help->request_param('scoring', 'Scoring rules:', 'empty scoring system is created.'));
		$help->response_param('scoring_id', 'Id of the newly created scoring system.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$scoring_id = (int)get_required_param('scoring_id');
		
		Db::begin();
		
		list ($club_id, $league_id, $old_name) = Db::record(get_label('scoring system'), 'SELECT club_id, league_id, name FROM scorings WHERE id = ?', $scoring_id);
		if (!is_null($club_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if (!is_null($league_id))
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		else if (!is_null($league_id))
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		$name = trim(get_optional_param('name', $old_name));
		$this->check_name($name, $club_id, $scoring_id);
		
		$scoring = get_optional_param('scoring', NULL);
		if (!is_string($scoring))
		{
			$scoring = json_encode($scoring);
		}
		
		$overwrite = false;
		list ($old_scoring, $version) = Db::record(get_label('scoring system'), 'SELECT v.scoring, s.version FROM scorings s JOIN scoring_versions v ON v.scoring_id = s.id AND v.version = s.version WHERE s.id = ?', $scoring_id);
		if ($old_scoring != $scoring)
		{
			list ($usageCount) = Db::record(get_label('event'), 'SELECT count(*) FROM events WHERE scoring_id = ? AND scoring_version = ? AND (flags & ' . EVENT_FLAG_FINISHED . ') <> 0', $scoring_id, $version);
			if ($usageCount <= 0)
			{
				list ($usageCount) = Db::record(get_label('tournament'), 'SELECT count(*) FROM tournaments WHERE scoring_id = ? AND scoring_version = ? AND (flags & ' . TOURNAMENT_FLAG_FINISHED . ') <> 0', $scoring_id, $version);
				$overwrite = ($usageCount <= 0);
			}
		}
		else
		{
			$scoring = NULL;
		}
		
		Db::exec(get_label('scoring system'), 'UPDATE scorings SET name = ? WHERE id = ?', $name, $scoring_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = $name;
			db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id);
		}
		
		if (!is_null($scoring))
		{
			if ($overwrite)
			{
				Db::exec(get_label('scoring system'), 'UPDATE scoring_versions SET scoring = ? WHERE scoring_id = ? AND version = ?', $scoring, $scoring_id, $version);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->scoring = $scoring;
					db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id);
				}
			}
			else
			{
				++$version;
				Db::exec(get_label('scoring system'), 'INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (?, ?, ?)', $scoring_id, $version, $scoring);
				Db::exec(get_label('scoring system'), 'UPDATE scorings SET version = ? WHERE id = ?', $version, $scoring_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->scoring = $scoring;
					$log_details->version = $version;
					db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id);
				}
			}
		}
		Db::commit();
		
		$this->response['scoring_version'] = (int)$version;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change scoring system. If some of the past events or tournaments are already using this scoring system, the scoring rules are not overwritten. We create a new version of scoring rules. Old events contunie using old version. All newly created events use the new version. Events that already exist but not finished yet will use the new version.');
		$help->request_param('scoring_id', 'Scoring system id. If the scoring system is global (shared between clubs) updating requires <em>admin</em> permissions.');
		$help->request_param('name', 'Scoring system name.', 'remains the same.');
		api_scoring_help($help->request_param('scoring', 'Scoring rules:', 'remain the same.'));
		$help->response_param('version', 'Current scoring system version.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$scoring_id = (int)get_required_param('scoring_id');
		
		list ($club_id) = Db::record(get_label('scoring system'), 'SELECT club_id FROM scorings WHERE id = ?', $scoring_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);

		Db::begin();
		Db::exec(get_label('scoring system'), 'DELETE FROM scoring_rules WHERE scoring_id = ?', $scoring_id);
		Db::exec(get_label('scoring system'), 'DELETE FROM scorings WHERE id = ?', $scoring_id);
		db_log(LOG_OBJECT_SCORING_SYSTEM, 'deleted', NULL, $scoring_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete scoring system.');
		$help->request_param('scoring_id', 'Scoring system id. If the scoring system is global (shared between clubs) deleting requires <em>admin</em> permissions.');
		return $help;
	}

	// //-------------------------------------------------------------------------------------------------------
	// // create_rule
	// //-------------------------------------------------------------------------------------------------------
	// function create_rule_op()
	// {
		// $scoring_id = (int)get_required_param('scoring_id');
		
		// list ($club_id) = Db::record(get_label('scoring system'), 'SELECT club_id FROM scorings WHERE id = ?', $scoring_id);
		// check_permissions(PERMISSION_CLUB_MANAGER, $club_id);

		// $matter = (int)get_required_param('matter');
		// if ($matter < 0 || $matter >= SCORING_MATTER_COUNT)
		// {
			// throw new Exc(get_label('Invalid rule matter [0]', $matter));
		// }
		
		// $category = (int)get_required_param('category');
		// if ($category < 0 || $category >= SCORING_CATEGORY_COUNT)
		// {
			// throw new Exc(get_label('Invalid rule category [0]', $matter));
		// }
		
		// $roles = (int)get_required_param('roles') & SCORING_ROLE_FLAGS_ALL;
		// if ($roles == 0)
		// {
			// throw new Exc(get_label('Please select at least one role'));
		// }
		
		// $policy = (int)get_required_param('policy');
		// if ($policy < 0 || $policy >= SCORING_POLICY_COUNT)
		// {
			// throw new Exc(get_label('Invalid policy [0]', $policy));
		// }
		
		// $min_points = (float)get_required_param('min_points');
		// if ($policy == SCORING_POLICY_STATIC)
		// {
			// $max_dependency = $min_dependency = 0.0;
			// $max_points = $min_points;
		// }
		// else
		// {
			// $max_points = (float)get_required_param('max_points');
			// if ($max_points == $min_points)
			// {
				// $policy == SCORING_POLICY_STATIC;
				// $max_dependency = $min_dependency = 0.0;
			// }
			// else
			// {
				// $min_dependency = (float)get_required_param('min_dep');
				// if ($min_dependency < 0)
				// {
					// $min_dependency = 0.0;
				// }
				// else if ($min_dependency > 1)
				// {
					// $min_dependency = 1.0;
				// }
				// $max_dependency = (float)get_required_param('max_dep');
				// if ($max_dependency < 0)
				// {
					// $max_dependency = 0.0;
				// }
				// else if ($max_dependency > 1)
				// {
					// $max_dependency = 1.0;
				// }
				
				// if ($min_dependency > $max_dependency)
				// {
					// $max_dependency = $min_dependency;
				// }
			// }
		// }
		
		// if ($min_points == 0 && $max_points == 0)
		// {
			// throw new Exc(get_label('Please enter points'));
		// }
		
		// Db::begin();
		// Db::exec(get_label('scoring rule'), 'INSERT INTO scoring_rules (scoring_id, category, matter, roles, policy, min_dependency, min_points, max_dependency, max_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 
			// $scoring_id, $category, $matter, $roles, $policy, $min_dependency, $min_points, $max_dependency, $max_points);
		
		// $log_details = new stdClass();
		// $log_details->matter = $matter;
		// $log_details->roles = $roles;
		// $log_details->policy = $policy;
		// $log_details->min_dependency = $min_dependency;
		// $log_details->min_points = $min_points;
		// $log_details->max_dependency = $max_dependency;
		// $log_details->max_points = $max_points;
		// db_log(LOG_OBJECT_SCORING_SYSTEM, 'rule created', $log_details, $scoring_id, $club_id);
		
		// Db::commit();
	// }
	
	// function create_rule_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER,
			// 'Create a scoring rule. Scoring system consists of the rules. Once the game result matches the scoring rule, a player gets some points defined by the rule. Examples:
			// <dl>
				// <dt class="plain">{ matter:1, category:0, roles:12, policy:0, min_dep:0, max_dep:0, min_points:3, max_points:3}</dt>
					// <dd>Black players (roles:12) get 3 points (min_points:3) when they win a game (matter:1).</dd>
				// <dt class="plain">{ matter:2, category:0, roles:15, policy:1, min_dep:0.8, max_dep:1, min_points:0, max_points:0.2}</dt>
					// <dd>All players (roles:15) get from 0 points (min_points:0) to 0.2 points (max_points:0.2) even if they lose a game (matter:2) but the difficulty of the game was 80% (min_dep:0.8) or more (max_dep:1). The actual value depends on the difficulty. For example if the game difficulty is 90%, all players who lose still get 0.1 points. Because 90% is equally between 0.8 and 1.</dd>
				// <dt class="plain">{ matter:8, category:1, roles:3, policy:2, min_dep:0.15, max_dep:0.4, min_points:0, max_points:0.3}</dt>
					// <dd>Red players (roles:3) killed the first night (matter:8) get from 0 points (min_points:0) to 0.3 points (max_points:0.3) depending on how often they are killed first night during the competition. For example suppose a player played 200 games and he was killed the first night in 60 of them. His killing frequency is 0.3 = 60 / 200. For every game where he was killed the first night he gets 0.18 points (linear interpolation between 0 and 0.3). If he is killed 100 times, his rate is 0.5 = 100 / 200. In this case his rate is greater than 0.4 (max_dep:0.4) and he gets 0.3 points (max_points:0.3) for every game.</dd>
			// </dl>
			// Note that max_points can be less than min_points. Although max_dep is always greater than min_dep. If max_dep is less than min_dep, it is set to min_dep. The rule is that user gets min_points when the rate specified in policy is less or equal min_dep; and max_points when it is greater or equal max_dep.');
		// $help->request_param('scoring_id', 'Scoring system id. If the scoring system is global (shared between clubs) creating a rule requires <em>admin</em> permissions.');
		// $help->request_param('matter', 
			// 'What happens with a player in the game in order to trigger this rule. If the rule is triggered the player gains the points specified by the rule. Possible values are:
				// <ul>
					// <li>0 - a player participated in the game (guaranteed points for just playing)</li>
					// <li>1 - a player won</li>
					// <li>2 - a player lost</li>
					// <li>3 - all players killed in a daytime were from another team.</li>
					// <li>4 - all players killed in a daytime were from player\'s team.</li>
					// <li>5 - a player is recognized as a best player</li>
					// <li>6 - a player made a best move. Note that this is not guessing 3 mafia. We have a separate matter for guessing.</li>
					// <li>7 - a player survived in the game</li>
					// <li>8 - a player was killed in the first night</li>
					// <li>9 - a player was killed in the night</li>
					// <li>10 - a player was guessed 3 mafia after being killed the first night</li>
					// <li>11 - a player was guessed 2 mafia after being killed the first night</li>
					// <li>12 - a player was killed by 4 warnings</li>
					// <li>13 - a player was kicked out by a moderator</li>
					// <li>14 - a player surrendered (left the table because there is no hope to win)</li>
					// <li>15 - all votes of a player were against blacks (only if he participated in 3 or more votings)</li>
					// <li>16 - all votes of a player were against reds (only if he participated in 3 or more votings)</li>
					// <li>17 - the sheriff was killed the next day after finding</li>
					// <li>18 - the don found the sheriff the first night</li>
					// <li>19 - the sheriff was killed the first night</li>
					// <li>20 - all first three sheriff\'s checks were black</li>
					// <li>21 - all first three sheriff\'s checks were red</li>
				// </ul>');
		// $help->request_param('category', 
			// 'What kind of points this rule adds to a player. In some scoring systems categories are used to recognize who is the winner when points are equal. For example in ФИИМ, when points are equal a player with more additional points wins. Possible values are:
				// <ul>
					// <li>0 - main points</li>
					// <li>1 - additional points</li>
				// </ul>');
		// $help->request_param('roles', 
			// 'To whom this rule is applied. This is a bit combination of flags: 1 - civilian, 2 - sheriff; 4 - mafia; 8 don. Possible values are:
				// <ul>
					// <li>1 - civilian</li>
					// <li>2 - sheriff</li>
					// <li>3 - red (civilian or sheriff)</li>
					// <li>4 - mafia (but not don)</li>
					// <li>5 - civilian or mafia (not don, not sheriff)</li>
					// <li>6 - sheriff or mafia (not civilian, not don)</li>
					// <li>7 - all except don</li>
					// <li>8 - don</li>
					// <li>9 - civilian or don (not mafia, not sheriff)</li>
					// <li>10 - sheriff or don (not mafia, not civilian)</li>
					// <li>11 - all except mafia (but including don)</li>
					// <li>12 - black (mafia or don)</li>
					// <li>13 - all except sheriff</li>
					// <li>14 - all except civilians (but including sheriff)</li>
					// <li>15 - all players</li>
				// </ul>');
		// $help->request_param('policy', 
			// 'How the points are added. Possible values are:
				// <ul>
					// <li>0 - just add static points specified in <q>min_points</q> parameter. <q>min_dep</q>, <q>max_dep</q>, and <q>max_points</q> are ignored. Set <q>min_dep</q> and <q>max_dep</q> to 0. Set <q>max_points</q> to the same value as <q>min_points</q>.</li>
					// <li>1 - points depend on the game difficulty. Game difficulty is the number of wins of the opposite team divided by total number of games in the competition. The result is from 0 to 1. If this value is lower than <q>min_dep</q>, players get <q>min_points</q>. If it is higher than <q>max_dep</q>, they get <q>max_points</q>. It it is in between, players get a linear interpolation between <q>max_points</q> and <q>min_points</q> depending on the value.</li>
					// <li>2 - points depend on the frequency of first night kills of the player. It is number of games in the competition when a target player was killed the first night divided by total number of games in the competition. The result is from 0 to 1. If this value is lower than <q>min_dep</q>, players get <q>min_points</q>. If it is higher than <q>max_dep</q>, they get <q>max_points</q>. It it is in between, players get a linear interpolation between <q>max_points</q> and <q>min_points</q> depending on the value.</li>
				// </ul>');
		// $help->request_param('min_dep', 'This is a float value from 0 to 1. Please refer to the <q>policy</q> parameter to understand how to use it.');
		// $help->request_param('min_points', 'Minimum number of poins that players can get out of this policy (float). Please refer to the <q>policy</q> parameter to understand how to use it.');
		// $help->request_param('max_dep', 'This is a float value from 0 to 1. Please refer to the <q>policy</q> parameter to understand how to use it.');
		// $help->request_param('max_points', 'Maximum number of poins that players can get out of this policy (float). Please refer to the <q>policy</q> parameter to understand how to use it.');
		// return $help;
	// }

	// //-------------------------------------------------------------------------------------------------------
	// // delete_rule
	// //-------------------------------------------------------------------------------------------------------
	// function delete_rule_op()
	// {
		// $scoring_id = (int)get_required_param('scoring_id');
		
		// list ($club_id) = Db::record(get_label('scoring system'), 'SELECT club_id FROM scorings WHERE id = ?', $scoring_id);
		// check_permissions(PERMISSION_CLUB_MANAGER, $club_id);

		// $matter = (int)get_required_param('matter');
		// $category = (int)get_required_param('category');
		// Db::begin();
		// Db::exec(get_label('scoring rule'), 'DELETE FROM scoring_rules WHERE scoring_id = ? AND matter = ? AND category = ?', $scoring_id, $matter, $category);
		
		// $log_details = new stdClass();
		// $log_details->matter = $matter;
		// $log_details->category = $category;
		// db_log(LOG_OBJECT_SCORING_SYSTEM, 'rule deleted', $log_details, $scoring_id, $club_id);
		
		// Db::commit();
	// }
	
	// function delete_rule_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete a number of scoring rules. Note that there are only two parameters identifying scoring rule - <q>matter</q> and <q>category</q>. All rules with this matter in this category are deleted.');
		// $help->request_param('scoring_id', 'Scoring system id. If the scoring system is global (shared between clubs) deleting a rule requires <em>admin</em> permissions.');
		// $help->request_param('matter', 
			// 'What happens with a player in the game in order to trigger this rule. If the rule is triggered the player gains the points specified by the rule. Possible values are:
				// <ul>
					// <li>0 - a player participated in the game (guaranteed points for just playing)</li>
					// <li>1 - a player won</li>
					// <li>2 - a player lost</li>
					// <li>3 - all players killed in a daytime were from another team.</li>
					// <li>4 - all players killed in a daytime were from player\'s team.</li>
					// <li>5 - a player is recognized as a best player</li>
					// <li>6 - a player made a best move. Note that this is not guessing 3 mafia. We have a separate matter for guessing.</li>
					// <li>7 - a player survived in the game</li>
					// <li>8 - a player was killed in the first night</li>
					// <li>9 - a player was killed in the night</li>
					// <li>10 - a player was guessed 3 mafia after being killed the first night</li>
					// <li>11 - a player was guessed 2 mafia after being killed the first night</li>
					// <li>12 - a player was killed by 4 warnings</li>
					// <li>13 - a player was kicked out by a moderator</li>
					// <li>14 - a player surrendered (left the table because there is no hope to win)</li>
					// <li>15 - all votes of a player were against blacks (only if he participated in 3 or more votings)</li>
					// <li>16 - all votes of a player were against reds (only if he participated in 3 or more votings)</li>
					// <li>17 - the sheriff was killed the next day after finding</li>
					// <li>18 - the don found the sheriff the first night</li>
					// <li>19 - the sheriff was killed the first night</li>
					// <li>20 - all first three sheriff\'s checks were black</li>
					// <li>21 - all first three sheriff\'s checks were red</li>
				// </ul>');
		// $help->request_param('category', 
			// 'What kind of points this rule adds to a player. In some scoring systems categories are used to recognize who is the winner when points are equal. For example in ФИИМ, when points are equal a player with more additional points wins. Possible values are:
				// <ul>
					// <li>0 - main points</li>
					// <li>1 - additional points</li>
				// </ul>');
		// return $help;
	// }

	// //-------------------------------------------------------------------------------------------------------
	// // change_sorting
	// //-------------------------------------------------------------------------------------------------------
	// function change_sorting_op()
	// {
		// $scoring_id = (int)get_required_param('scoring_id');
		
		// list ($club_id) = Db::record(get_label('scoring system'), 'SELECT club_id FROM scorings WHERE id = ?', $scoring_id);
		// check_permissions(PERMISSION_CLUB_MANAGER, $club_id);

		// $sorting = get_required_param('sorting');
		// Db::begin();
		// Db::exec(get_label('scoring system'), 'UPDATE scorings SET sorting = ? WHERE id = ?', $sorting, $scoring_id);
		
		// $log_details = new stdClass();
		// $log_details->sorting = $sorting;
		// db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed sorting', $log_details, $scoring_id, $club_id);
		
		// Db::commit();
	// }
	
	// function change_sorting_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change sorting rules for the scoring system. Sorting rule is what happens when two or more players have the same number of points. How are they sorted in the scoring table. When a system is created, sorting rules by default are "acgk".');
		// $help->request_param('scoring_id', 'Scoring system id. If the scoring system is global (shared between clubs) changing sorting requires <em>admin</em> permissions.');
		// $help->request_param('sorting', 
			// 'A string that describes sorting. Every letter in the string means some sorting parameter. The order of letters mean sorting priority. Dash '-' before the character reverses it. For example:
				// <ul>
					// <li><q>acgk</q>: means that between the players with equal points the one with more additional points wins (a); if additional points are equal the one who won more games wins (c); if they are still equal then the one who won more games in a special role wins (g); and last if they are still equal, the one who was killed the first night wins.</li>
					// <li><q>c-o-p</q>: means that between the players with equal points the one with more wins (c); if additional points are equal the one who was kicked out from the game less often wins (-o); if they are still equal then the one who got 4 warnings less often wins (-p).</li>
				// </ul>
				// Possible letters and their meanings are:
				// <ul>
					// <li>a - a player who has more additional points (look at the <q>category</q> parameter in create operation) wins.</li>
					// <li>b - a player who has more main points (look at the <q>category</q> parameter in create operation) wins.</li>
					// <li>c - a player who won more games wins.</li>
					// <li>d - a player who lost more games wins.</li>
					// <li>e - a player who won more games clearly (3-3) wins.</li>
					// <li>f - a player who lost more games clearly (3-3) wins.</li>
					// <li>g - a player who won more games in a special role (don/sheriff) wins.</li>
					// <li>h - a player who has more <q>best player</q> titles wins.</li>
					// <li>i - a player who has more <q>best move/q> titles wins.</li>
					// <li>j - a player who survived more games wins.</li>
					// <li>k - a player who was killed the first night more often wins.</li>
					// <li>l - a player who was killed any night more often wins.</li>
					// <li>m - a player who guessed 3 mafia more often wins.</li>
					// <li>n - a player who guessed 2 mafia more often wins.</li>
					// <li>o - a player who got 4 warnings more often wins.</li>
					// <li>p - a player who was kicked out from the game more often wins.</li>
					// <li>q - a player who surrendered more often wins.</li>
				// </ul>');
		// return $help;
	// }
}

$page = new ApiPage();
$page->run('Scoring Operations', CURRENT_VERSION);

?>