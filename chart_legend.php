<?php

require_once 'include/session.php';
require_once('include/user.php');
require_once('include/club.php');
require_once('include/chart.php');

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
	
	$players = array_fill(0, $chart_count, NULL);
	if (!empty($player_list))
	{
		$query = new DbQuery('SELECT u.id, u.name, u.flags, c.id, c.name, c.flags FROM users u LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE u.id IN(' . $player_list . ') ORDER BY FIELD(u.id, ' . $player_list . ')');
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
	
	echo '<table class="bordered" width="100%"><tr align="center">';
	$count = count($players);
	$percentage = 100 / $count;
	for ($i = 0; $i < $count; ++$i)
	{
		$player = $players[$i];
		if ($player != NULL)
		{
			list($user_id, $user_name, $user_flags, $club_id, $club_name, $club_flags) = $player;
			
			if ($user_id != $main_player)
			{
				// form new user list
				$player_array[$i] = 0;
				$player_list = chart_array_to_list($player_array, $chart_count);
				$player_array[$i] = $user_id;
				
				$a_open = '<a href="#" onclick="updateChart({ type: \'' . $type . '\', name: \'' . $name . '\', players: \'' . $player_list . '\', charts: ' . $chart_count;
				if ($main_player > 0)
				{
					$a_open .= ', main: ' . $main_player;
				}
				$a_open .= ' })" title="' . get_label('Remove [0] from the chart', $user_name) . '">';
				$a_close = '</a>';
			}
			else
			{
				$a_open = $a_close = '';
			}
			
			echo '<td width="' . $percentage . '%"><table class="transp" width="100%"><tr><td width="50">' . $a_open . '<img src="images/chart' . ($i + 1) . '.png">' . $a_close . '</td>';
			echo '<td width="64">' . $a_open . $user_name . $a_close . '</td><td><a href="user_competition.php?id=' . $user_id . '">';
			show_user_pic($user_id, $user_name, $user_flags, ICONS_DIR, 32, 32);
			echo '</a><a href="club_main.php?bck=1&id=' . $club_id . '">';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 32, 32);
			echo '</a></td>';
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
	echo $e->getMessage();
}

?>