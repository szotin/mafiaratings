<?php

require_once '../../include/api.php';
require_once '../../include/seating.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function add_pair($user1_id, $user2_id, $policy, $tournament_id, $club_id, $league_id, $global)
	{
		if ($user1_id == $user2_id)
		{
			throw new Exc(get_label('Cannot create a pair policy for the same user.'));
		}

		if ($user1_id > $user2_id)
		{
			$tmp = $user1_id;
			$user1_id = $user2_id;
			$user2_id = $tmp;
		}

		if ($tournament_id > 0)
		{
			list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
			$club_id = (int)$club_id;

			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id);

			$default_policy = PAIR_POLICY_NOTHING;
			Db::begin();
			if ($global)
			{
				if (is_permitted(PERMISSION_ADMIN))
				{
					Db::exec(get_label('pair'), 'INSERT INTO pairs (user1_id, user2_id, policy) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $user1_id, $user2_id, $policy, $policy);
					Db::exec(get_label('pair'), 'DELETE FROM tournament_pairs WHERE tournament_id = ? AND user1_id = ? AND user2_id = ?', $tournament_id, $user1_id, $user2_id);
					Db::exec(get_label('pair'), 'DELETE FROM club_pairs WHERE club_id = ? AND user1_id = ? AND user2_id = ?', $club_id, $user1_id, $user2_id);
					Db::exec(get_label('pair'), 'DELETE FROM league_pairs WHERE league_id IN (SELECT DISTINCT s.league_id FROM series s JOIN series_tournaments st ON st.series_id = s.id WHERE st.tournament_id = ?) AND user1_id = ? AND user2_id = ?', $tournament_id, $user1_id, $user2_id);
					$default_policy = $policy;
				}
				else
				{
					$query = new DbQuery('SELECT policy FROM pairs WHERE user1_id = ? AND user2_id = ?', $user1_id, $user2_id);
					if ($row = $query->next())
					{
						$default_policy = (int)$row[0];
					}
					
					if (is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id))
					{
						if ($default_policy == $policy)
						{
							Db::exec(get_label('pair'), 'DELETE FROM club_pairs WHERE club_id = ? AND user1_id = ? AND user2_id = ?', $club_id, $user1_id, $user2_id);
						}
						else
						{
							Db::exec(get_label('pair'), 'INSERT INTO club_pairs (club_id, user1_id, user2_id, policy) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $club_id, $user1_id, $user2_id, $policy, $policy);
						}
						$default_policy = $policy;
					}

					$query = Db::query('SELECT DISTINCT s.league_id FROM series s JOIN series_tournaments st ON st.series_id = s.id WHERE st.tournament_id = ?', $tournament_id);
					while ($row = $query->next())
					{
						$league_id = (int)$row[0];
						if (is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
						{
							if ($default_policy == $policy)
							{
								Db::exec(get_label('pair'), 'DELETE FROM league_pairs WHERE league_id = ? AND user1_id = ? AND user2_id = ?', $league_id, $user1_id, $user2_id);
							}
							else
							{
								Db::exec(get_label('pair'), 'INSERT INTO league_pairs (league_id, user1_id, user2_id, policy) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $league_id, $user1_id, $user2_id, $policy, $policy);
							}
							$default_policy = $policy;
						}
					}
				}
			}
			else
			{
				$query = new DbQuery('SELECT policy FROM pairs WHERE user1_id = ? AND user2_id = ?', $user1_id, $user2_id);
				while ($row = $query->next())
				{
					$default_policy = (int)$row[0];
				}
				
				$query = new DbQuery('SELECT policy FROM club_pairs WHERE club_id = ? AND user1_id = ? AND user2_id = ?', $club_id, $user1_id, $user2_id);
				while ($row = $query->next())
				{
					$default_policy = min((int)$row[0], $default_policy);
				}
				
				$query = new DbQuery('SELECT policy FROM league_pairs WHERE league_id IN (SELECT DISTINCT s.league_id FROM series s JOIN series_tournaments st ON st.series_id = s.id WHERE st.tournament_id = ?) AND user1_id = ? AND user2_id = ?', $tournament_id, $user1_id, $user2_id);
				while ($row = $query->next())
				{
					$default_policy = min((int)$row[0], $default_policy);
				}
			}

			if ($default_policy == $policy)
			{
				Db::exec(get_label('pair'), 'DELETE FROM tournament_pairs WHERE tournament_id = ? AND user1_id = ? AND user2_id = ?', $tournament_id, $user1_id, $user2_id);
			}
			else
			{
				Db::exec(get_label('pair'), 'INSERT INTO tournament_pairs (tournament_id, user1_id, user2_id, policy) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $tournament_id, $user1_id, $user2_id, $policy, $policy);
			}
			Db::commit();
		}
		else if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
			Db::begin();
			if ($global && is_permitted(PERMISSION_ADMIN))
			{
				Db::exec(get_label('pair'), 'INSERT INTO pairs (user1_id, user2_id, policy) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $user1_id, $user2_id, $policy, $policy);
				Db::exec(get_label('pair'), 'DELETE FROM club_pairs WHERE club_id = ? AND user1_id = ? AND user2_id = ?', $club_id, $user1_id, $user2_id);
			}
			else
			{
				$default_policy = PAIR_POLICY_NOTHING;
				$query = new DbQuery('SELECT policy FROM pairs WHERE user1_id = ? AND user2_id = ?', $user1_id, $user2_id);
				if ($row = $query->next())
				{
					$default_policy = (int)$row[0];
				}
				
				if ($default_policy == $policy)
				{
					Db::exec(get_label('pair'), 'DELETE FROM club_pairs WHERE club_id = ? AND user1_id = ? AND user2_id = ?', $club_id, $user1_id, $user2_id);
				}
				else
				{
					Db::exec(get_label('pair'), 'INSERT INTO club_pairs (club_id, user1_id, user2_id, policy) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $club_id, $user1_id, $user2_id, $policy, $policy);
				}
			}
			Db::commit();
		}
		else if ($league_id > 0)
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			Db::begin();
			if ($global && is_permitted(PERMISSION_ADMIN))
			{
				Db::exec(get_label('pair'), 'INSERT INTO pairs (user1_id, user2_id, policy) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $user1_id, $user2_id, $policy, $policy);
				Db::exec(get_label('pair'), 'DELETE FROM league_pairs WHERE league_id = ? AND user1_id = ? AND user2_id = ?', $league_id, $user1_id, $user2_id);
			}
			else
			{
				$default_policy = PAIR_POLICY_NOTHING;
				$query = new DbQuery('SELECT policy FROM pairs WHERE user1_id = ? AND user2_id = ?', $user1_id, $user2_id);
				if ($row = $query->next())
				{
					$default_policy = (int)$row[0];
				}
				
				if ($default_policy == $policy)
				{
					Db::exec(get_label('pair'), 'DELETE FROM league_pairs WHERE league_id = ? AND user1_id = ? AND user2_id = ?', $league_id, $user1_id, $user2_id);
				}
				else
				{
					Db::exec(get_label('pair'), 'INSERT INTO league_pairs (league_id, user1_id, user2_id, policy) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $league_id, $user1_id, $user2_id, $policy, $policy);
				}
			}
			Db::commit();
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			Db::exec(get_label('pair'), 'INSERT INTO pairs (user1_id, user2_id, policy) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE policy = ?', $user1_id, $user2_id, $policy, $policy);
		}		
	}
	
	//-------------------------------------------------------------------------------------------------------
	// add_pair
	//-------------------------------------------------------------------------------------------------------
	function add_pair_op()
	{
		$tournament_id = (int)get_optional_param('tournament_id', 0);
		$club_id = (int)get_optional_param('club_id', 0);
		$league_id = (int)get_optional_param('league_id', 0);

		$user1_id = (int)get_required_param('user1_id');
		$user2_id = (int)get_required_param('user2_id');
		$policy = (int)get_required_param('policy');
		$global = (int)get_optional_param('global', 0);

		$this->add_pair($user1_id, $user2_id, $policy, $tournament_id, $club_id, $league_id, $global);
	}

	function add_pair_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, 'Add a pair policy between two players in a tournament/club/league.');
		$help->request_param('tournament_id', 'Policy is created for this tournament id.', 'policy is created globally.');
		$help->request_param('league_id', 'Policy is created for this league id.', 'policy is created globally.');
		$help->request_param('club_id', 'Policy is created for this club id.', 'policy is created globally.');
		$help->request_param('user1_id', 'First player user ID.');
		$help->request_param('user2_id', 'Second player user ID.');
		$help->request_param('policy', 'Pair policy: ' . PAIR_POLICY_SEPARATE . ' = separate, ' . PAIR_POLICY_AVOID . ' = avoid, ' . PAIR_POLICY_WELCOME . ' = welcome.' . PAIR_POLICY_NOTHING . ' = nothing, ');
		$help->request_param('global', 'If 0 (default), the policy is saved for this tournament/club/league only. If 1, it may be saved at a broader scope depending on caller permissions.', '0');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete_pair
	//-------------------------------------------------------------------------------------------------------
	function delete_pair_op()
	{
		$tournament_id = (int)get_optional_param('tournament_id', 0);
		$club_id = (int)get_optional_param('club_id', 0);
		$league_id = (int)get_optional_param('league_id', 0);

		$user1_id = (int)get_required_param('user1_id');
		$user2_id = (int)get_required_param('user2_id');
		$global = (int)get_optional_param('global', 0);

		$this->add_pair($user1_id, $user2_id, PAIR_POLICY_NOTHING, $tournament_id, $club_id, $league_id, $global);
	}

	function delete_pair_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, 'Delete a pair policy between two players in a tournament/club/league.');
		$help->request_param('tournament_id', 'Policy is deleted for this tournament id.', 'policy is deleted globally.');
		$help->request_param('league_id', 'Policy is deleted for this league id.', 'policy is deleted globally.');
		$help->request_param('club_id', 'Policy is deleted for this club id.', 'policy is deleted globally.');
		$help->request_param('user1_id', 'First player user ID.');
		$help->request_param('user2_id', 'Second player user ID.');
		$help->request_param('global', 'If 0 (default), the policy is deleted for this tournament/club/league only. If 1, it may be deleted at a broader scope depending on caller permissions.', '0');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		check_permissions(PERMISSION_USER);
		
		$players  = (int)get_required_param('players');
		$tables   = (int)get_required_param('tables');
		$games    = (int)get_required_param('games');
		$restrictions_json = get_optional_param('restrictions', '[]');
		$restrictions   = json_decode($restrictions_json, true);
		if (!is_array($restrictions))
		{
			$restrictions = array();
		}

		$seatingDef = new SeatingDef($players, $tables, $games, $restrictions);
		$seatingDef->normalizeRestrictions();
		$hash = $seatingDef->hash;

		Db::begin();
		// If already exists, just return the hash.
		$query = new DbQuery('SELECT hash FROM seatings WHERE hash = ?', $hash);
		if (!$query->next())
		{
			$query = new DbQuery('SELECT seating, players_score, numbers_score, tables_score, players_max_score, numbers_max_score, tables_max_score FROM seatings WHERE hash LIKE(?)', $hash . '%');
			if ($row = $query->next())
			{
				list ($seating_json, $players_score, $numbers_score, $tables_score, $players_max_score, $numbers_max_score, $tables_max_score) = $row;
				echo get_label('We have found a similar but not exactly the same seating arrangement. It is pretty good but we can do better. If you wait for a few hours and request this seating again, you will receive an improved version.');
			}
			else
			{
				$seating = $seatingDef->generateInitialSeating();
				$players_score = $players_max_score = $seatingDef->calculatePlayersScore($seating);
				$numbers_score = $numbers_max_score = $seatingDef->calculateNumbersScore($seating); 
				$tables_score = $tables_max_score = $seatingDef->calculateTablesScore($seating);
				$seating_json = json_encode($seating);
				echo get_label('We do not have a good seating arrangement for this configuration. We have generated a very basic initial seating for you. Now we are improving and optimizing it. If you wait for a few hours and request this seating again, you will receive an improved version.');
			}
			Db::exec(get_label('seating'), 
				'INSERT INTO seatings (hash, seating, players_score, numbers_score, tables_score, players_max_score, numbers_max_score, tables_max_score)'.
				' VALUES (?, ?, ?, ?, ?, ?, ?, ?)', 
					$hash, $seating_json, $players_score, $numbers_score, $tables_score, $players_max_score, $numbers_max_score, $tables_max_score);
		}
		Db::commit();

		$this->response['hash'] = $hash;
	}

	function create_op_help()
	{
		$help = new ApiHelp(0, 'Create a seating configuration.');
		$help->request_param('players', 'Total number of players (min 10).');
		$help->request_param('tables', 'Number of tables per round (min 1).');
		$help->request_param('games', 'Number of games each player must play (min 1).');
		$help->request_param('restrictions', 'JSON array of groups of player numbers that cannot sit together.', '[]');
		$help->response_param('hash', 'Seating configuration hash (primary key).');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		check_permissions(PERMISSION_ADMIN);
		$hash = get_required_param('hash');
		Db::exec(get_label('seating'), 'DELETE FROM seatings WHERE hash = ?', $hash);
	}

	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Delete a seating configuration.');
		$help->request_param('hash', 'Seating configuration hash.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Sitting Operations', CURRENT_VERSION);

?>
