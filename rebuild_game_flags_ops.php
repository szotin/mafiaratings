<?php

require_once 'include/session.php';
require_once 'include/game_state.php';
require_once 'include/club.php';

ob_start();
$result = array();
	
try
{
	initiate_session();
	if ($_profile == NULL)
	{
		throw new Exc(get_label('No permissions'));
	}
	
	if (!$_profile->is_admin())
	{
		throw new Exc(get_label('No permissions'));
	}
	
	if (isset($_REQUEST['rebuild']))
	{
		$last_id = 0;
		if (isset($_REQUEST['last_id']))
		{
			$last_id = $_REQUEST['last_id'];
		}
		else
		{
			list ($count) = Db::record('club', 'SELECT count(*) FROM games');
			$result['count'] = $count;
			lock_site(true);
			Db::exec(
				get_label('club'), 
				'UPDATE clubs c SET rating_limit = IFNULL((SELECT MIN(end_time) FROM games WHERE club_id = c.id), UNIX_TIMESTAMP()) - ?', 
				GLOBAL_RATING_INTERVAL * MAXIMUM_GLOBAL_RATING_GAMES);
		}
		$c = 0;
		
		$query = new DbQuery('SELECT g.id, g.end_time, g.club_id, count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.id > ? GROUP BY g.id ORDER BY g.id LIMIT 20', $last_id);
		while ($row = $query->next())
		{
			list($id, $end_game, $club_id, $players_count) = $row;
			$last_id = $id;
			++$c;
			try
			{
				if ($players_count == 10)
				{
					list($rating_limit) = Db::record(get_label('club'), 'SELECT ? - rating_limit FROM clubs WHERE id = ?', $end_game, $club_id);
					$rating_limit = floor($rating_limit / GLOBAL_RATING_INTERVAL);
					if ($rating_limit > MAXIMUM_GLOBAL_RATING_GAMES)
					{
						$rating_limit = MAXIMUM_GLOBAL_RATING_GAMES;
					}
					echo $id . ': ' . $rating_limit . '<br>';
					if ($rating_limit > 0)
					{
						--$rating_limit;
						Db::exec(
							get_label('club'), 
							'UPDATE clubs SET rating_limit = ? WHERE id = ?', $end_game - $rating_limit * GLOBAL_RATING_INTERVAL, $club_id);
						Db::exec(
							get_label('game'), 
							'UPDATE games SET flags = (flags | ?) WHERE id = ?', GAME_FLAG_CLUB_RATING | GAME_FLAG_GLOBAL_RATING, $id);
					}
					else
					{
						Db::exec(
							get_label('game'), 
							'UPDATE games SET flags = (flags & ~?) WHERE id = ?', GAME_FLAG_CLUB_RATING | GAME_FLAG_GLOBAL_RATING, $id);
					}
				}
				else
				{
					echo $id . ': ' . $players_count . ' players <br>';
					Db::exec(
						get_label('game'), 
						'UPDATE games SET flags = (flags & ~?) WHERE id = ?', GAME_FLAG_CLUB_RATING | GAME_FLAG_GLOBAL_RATING, $id);
				}
			}
			catch (Exception $e)
			{
				echo $e->getMessage() . '<br>';
			}
		}
		$result['recs'] = $c;
		$result['last_id'] = $last_id;
		if ($c <= 0)
		{
			lock_site(false);
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
	lock_site(false);
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	$result['message'] = $message;
}

echo json_encode($result);

?>