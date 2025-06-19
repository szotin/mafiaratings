<?php

require_once '../include/tournament.php';
require_once '../include/picture.php';

try
{
	initiate_session();
	check_maintenance();

	echo '<!DOCTYPE HTML>';
	echo '<html>';
	echo '<head>';
	echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
	echo '</head><body>';
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc('Unknown tournament round');
	}
	$event_id = (int)$_REQUEST['id'];
	if (!isset($_REQUEST['table_num']))
	{
		throw new Exc('Unknown table');
	}
	$table_num = max((int)$_REQUEST['table_num'], 1);
	
	list ($event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $misc) = 
		Db::record('round', 
			'SELECT e.name, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags, e.misc'.
			' FROM events e'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' JOIN clubs c ON c.id = t.club_id'.
			' WHERE e.id = ?', $event_id);
			
	if (is_null($misc))
	{
		throw new Exc('No seating for this event');
	}
	$misc = json_decode($misc);
	if (!isset($misc->seating))
	{
		throw new Exc('No seating for this event');
	}
	if ($table_num - 1 >= count($misc->seating))
	{
		throw new Exc('Table ' . $table_num . ' is invalid. There are only ' . count($misc->seating) . ' tables.' );
	}

	$playing_now = array();
	$query = new DbQuery('SELECT game_num, game FROM current_games WHERE event_id = ? AND table_num = ? ORDER BY game_num', $event_id, $table_num);
	while ($row = $query->next())
	{
		list ($n, $g) = $row;
		$g = json_decode($g);
		if (isset($g->time)) 
		{
			// the game has started
			$playing_now[(int)$n] = (int)$n;
		}
	}
	//throw new Exc(formatted_json($playing_now));
	
	$game_num = 1;
	while (array_key_exists($game_num, $playing_now))
	{
		++$game_num;
	}

	$query = new DbQuery('SELECT game_num FROM games WHERE event_id = ? AND table_num = ? ORDER BY game_num', $event_id, $table_num);
	while ($row = $query->next())
	{
		
		list ($n) = $row;
		if ($game_num != $n)
		{
			break;
		}

		do
		{
			++$game_num;
		} 
		while (array_key_exists($game_num, $playing_now));
	}
	
	if ($game_num <= count($misc->seating[$table_num - 1]))
	{
		$seating = $misc->seating[$table_num - 1][$game_num - 1];
		$user_list = '';
		$delim = '';
		foreach ($seating as $user_id)
		{
			$user_list .= $delim . $user_id;
			$delim = ',';
		}
		
		$players = array();
		if (!empty($user_list))
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, eu.flags, tu.flags, cu.flags'.
					' FROM users u' .
					' LEFT OUTER JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' JOIN events e ON e.id = ?' .
					' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = e.id' .
					' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' .
					' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = e.club_id' .
					' WHERE u.id IN (' . $user_list . ')',
				$event_id);
			while ($row = $query->next())
			{
				$players[(int)$row[0]] = $row;
			}
		}
		
		$user_pic =
			new Picture(USER_EVENT_PICTURE,
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));
	
		echo '<table><tr><td colspan="10" align="center">';
		echo '<h2>Следующая игра. Cтол ' . $table_num . '. Игра ' . $game_num . '.</h2></td></tr>';
		echo '</tr><tr>';
		
		foreach ($seating as $user_id)
		{
			$name = '';
			if (isset($players[$user_id]))
			{
				list ($user_id, $name, $flags, $event_flags, $tournament_flags, $club_flags) = $players[$user_id];
			}
			echo '<td align="center"><b>' . $name . '</b></td>';
		}
		echo '</tr><tr>';
		$server_url = get_server_url();
		foreach ($seating as $user_id)
		{
			if (isset($players[$user_id]))
			{
				list ($user_id, $name, $flags, $event_flags, $tournament_flags, $club_flags) = $players[$user_id];
			}
			else
			{
				$flags = $event_flags = $tournament_flags = $club_flags = 0;
				$name = '';
			}
			echo '<td width="80">';
			$user_pic->
				set($user_id, $name, $event_flags, 'e' . $event_id)->
				set($user_id, $name, $tournament_flags, 't' . $tournament_id)->
				set($user_id, $name, $club_flags, 'c' . $club_id)->
				set($user_id, $name, $flags);
			echo '<img src="' . $server_url . '/' . $user_pic->url(ICONS_DIR) . '" width="80">';
			echo '</td>';
		}
		echo '</tr><tr>';
		for ($num = 1; $num <= 10; ++$num)
		{
			echo '<td align="center"><b>' . $num . '</b></td>';
		}
		echo '</tr></table>';
	}
}
catch (Exception $e)
{
	echo $e->getMessage();
	Exc::log($e);
}
?>

</body>
<script>
setTimeout(function() { window.location.replace(document.URL); }, <?php echo 60000; ?>);
</script>
