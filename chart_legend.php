<?php

require_once 'include/session.php';
require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/chart.php';

initiate_session();

try
{
	if (!isset($_REQUEST['players']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('player')));
	}
	$player_list = $_REQUEST['players'];
	
	$chart_count = MAX_CHARTS_COUNT;
	if (isset($_REQUEST['charts']))
	{
		$chart_count = (int)$_REQUEST['charts'];
		if ($chart_count <= 0 || $chart_count > MAX_CHARTS_COUNT)
		{
			$chart_count = MAX_CHARTS_COUNT;
		}
	}
	
	if (!isset($_REQUEST['type']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('chart type')));
	}
	$type = $_REQUEST['type'];
	
	$name = '';
	if (isset($_REQUEST['name']))
	{
		$name = $_REQUEST['name'];
	}
	
	$main_player = 0;
	if (isset($_REQUEST['main']))
	{
		$main_player = (int)$_REQUEST['main'];
	}
	
	$player_array = chart_list_to_array($player_list, $chart_count);
	$player_list = chart_array_to_list($player_array, 0);
	$link = 'competition.php?user_id=';
	
	$players = array_fill(0, $chart_count, NULL);
	if (!empty($player_list))
	{
		if (isset($_REQUEST['event_id']))
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, e.id, eu.nickname, eu.flags, e.tournament_id, tu.flags, e.club_id, cu.flags' . 
				' FROM users u' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN events e ON e.id = ?' .
				' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = e.id' .
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = e.club_id' .
				' WHERE u.id IN(' . $player_list . ') ORDER BY FIELD(u.id, ' . $player_list . ')', $_REQUEST['event_id']);
		}
		else if (isset($_REQUEST['tournament_id']))
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, NULL, NULL, NULL, t.id, tu.flags, t.club_id, cu.flags' . 
				' FROM users u' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN tournaments t ON t.id = ?' .
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = t.id' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = t.club_id' .
				' WHERE u.id IN(' . $player_list . ') ORDER BY FIELD(u.id, ' . $player_list . ')', $_REQUEST['tournament_id']);
		}
		else if (isset($_REQUEST['club_id']))
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, NULL, NULL, NULL, NULL, NULL, cu.club_id, cu.flags' . 
				' FROM users u' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
				' WHERE u.id IN(' . $player_list . ') ORDER BY FIELD(u.id, ' . $player_list . ')', $_REQUEST['club_id']);
			$link = 'club_competition.php?id=' . $_REQUEST['club_id'] . '&user_id=';
		}
		else
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, NULL, NULL, NULL, NULL, NULL, NULL, NULL' . 
				' FROM users u' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' WHERE u.id IN(' . $player_list . ') ORDER BY FIELD(u.id, ' . $player_list . ')');
		}
		
		$player_num = 0;
		while (($row = $query->next()) && $player_num < $chart_count)
		{
			list($user_id) = $row;
			while ($player_array[$player_num] != $user_id)
			{
				++$player_num;
			}
			$players[$player_num] = $row;
			++$player_num;
		}
	}
	
	$user_pic =
		new Picture(USER_EVENT_PICTURE, 
		new Picture(USER_TOURNAMENT_PICTURE,
		new Picture(USER_CLUB_PICTURE,
		new Picture(USER_PICTURE))));
		
	echo '<table class="bordered" width="100%"><tr align="center">';
	$count = count($players);
	$percentage = 100 / $count;
	for ($i = 0; $i < $count; ++$i)
	{
		$player = $players[$i];
		if ($player != NULL)
		{
			list($user_id, $user_name, $user_flags, $event_id, $event_user_nickname, $event_user_flags, $tournament_id, $tournament_user_flags, $club_id, $club_user_flags) = $player;
			
			if ($user_id != $main_player)
			{
				// form new user list
				$player_array[$i] = 0;
				$player_list = chart_array_to_list($player_array, $chart_count);
				$player_array[$i] = $user_id;
				
				$a_open = '<a href="#" onclick="updateChart(\'' . $player_list . '\')" title="' . get_label('Remove [0] from the chart', $user_name) . '">';
				$a_close = '</a>';
			}
			else
			{
				$a_open = $a_close = '';
			}
			
			echo '<td width="' . $percentage . '%"><table class="transp" width="100%"><tr><td width="50">' . $a_open . '<img src="images/chart' . ($i + 1) . '.png">' . $a_close . '</td>';
			echo '<td>' . $a_open . $user_name . $a_close . '</td><td width="42">';
			// in the future implement links as navigation between competition charts
			// I started doing it, but have no time at the moment. Check the $link variable.
			// echo '<a href="' . $link . $user_id . '">';
			$user_pic->
				set($user_id, $event_user_nickname, $event_user_flags, 'e' . $event_id)->
				set($user_id, $user_name, $tournament_user_flags, 't' . $tournament_id)->
				set($user_id, $user_name, $club_user_flags, 'c' . $club_id)->
				set($user_id, $user_name, $user_flags);
			$user_pic->show(ICONS_DIR, false, 42);
//			echo '</a></td>';
			echo '</td>';
		}
		else
		{
			echo '<td width="' . $percentage . '%"><table class="transp" width="100%"><tr><td width="56"><img src="images/chart' . ($i + 1) . '.png"></td><td><input type="text" id="chart-player-' . $i . '" title="' . get_label('Select a player.') . '">';
		}
		echo '</tr></table></td>';
	}
	
	echo '</tr></table>';
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>