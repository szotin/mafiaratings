<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';

try
{
	initiate_session();
	check_maintenance();
	
	$club_pic = new Picture(CLUB_PICTURE);
	$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, $club_pic));
	$user_pic = new Picture(USER_PICTURE);
	
	echo '<!DOCTYPE HTML>';
	echo '<html>';
	echo '<head>';
	echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
	echo '<script src="js/labels_' . $_lang_code . '.js"></script>';
	echo '<link rel="stylesheet" href="desktop.css" type="text/css" media="screen" />';
	echo '<link rel="stylesheet" href="common.css" type="text/css" media="screen" />';
	
	echo '</head><body>';
	
	$event = new Event();
	$event->load($_REQUEST['id']);
	
	$is_manager = ($_profile != NULL && $_profile->is_club_manager($event->club_id));
	$rows = 10;
	$cols = 2;
	$title = 1;
	$refr = 60;
	$logo_height = TNAIL_HEIGHT;
	$edit_settings = isset($_REQUEST['settings']);
	
	if ($is_manager && isset($_REQUEST['save']))
	{
		if (isset($_REQUEST['r']))
		{
			$rows = (int)$_REQUEST['r'];
		}
		
		if (isset($_REQUEST['c']))
		{
			$cols = (int)$_REQUEST['c'];
		}

		$title = isset($_REQUEST['t']);

		if (isset($_REQUEST['refr']))
		{
			$refr = (int)$_REQUEST['refr'];
		}
		
		if (isset($_REQUEST['l']))
		{
			$logo_height = (int)$_REQUEST['l'];
		}
		$json = json_encode(array($rows, $cols, $logo_height, $title, $refr));
		$query = Db::exec(get_label('event'), 'UPDATE events SET standings_settings = ? WHERE id = ?', $json, $event->id);
		header('location: event_screen.php?id=' . $event->id);
	}
	else
	{
		list ($standings_settings) = Db::record(get_label('event'), 'SELECT e.standings_settings FROM events e WHERE e.id = ?', $event->id);
		if (is_null($standings_settings))
		{
			$edit_settings = true;
			$query = Db::query('SELECT e.standings_settings FROM events e WHERE e.standings_settings IS NOT NULL AND e.club_id = ? ORDER BY e.id DESC LIMIT 1', $event->club_id);
			if ($row = $query->next())
			{
				list ($standings_settings) = $row;
			}
		}
		
		if (!is_null($standings_settings))
		{
			list ($rows, $cols, $logo_height, $title, $refr) = json_decode($standings_settings);
		}
	}
	
	if ($edit_settings && $is_manager)
	{
		echo '<h1>' . get_label('Standings screen settings') . '</h1>';
		echo '<form method="get" action="event_screen.php">';
		echo '<input type="hidden" name="id" value="' . $event->id . '">';
		echo '<input type="hidden" name="save">';
		echo '<table class="dialog_form" width="100%">';
		echo '<tr><td width="100">'.get_label('Rows').':</td><td><input name="r" value="' . $rows . '"></td></tr>';
		echo '<tr><td width="100">'.get_label('Columns').':</td><td><input name="c" value="' . $cols . '"></td></tr>';
		echo '<tr><td width="100">'.get_label('Logo height').':</td><td><input name="l" value="' . $logo_height . '"> (' . get_label('Use 0 for no logo') . ')</td></tr>';
		echo '<tr><td width="100">'.get_label('Refresh every').':</td><td><input name="refr" value="' . $refr . '"> ' . get_label('sec') . '</td></tr>';
		echo '<tr><td colspan="2"><input type="checkbox" name="t"';
		if ($title)
		{
			echo ' checked';
		}
		echo '> ' . get_label('show title') . '</td></tr>';
		echo '</table>';
		echo '<br><input value="'.get_label('Save').'" class="btn norm" type="submit">';
		// echo ' <input value="'.get_label('Default values').'" type="submit" class="btn long" onClick="resetToDefault()">';
		echo '</form>';
	}
	else
	{
		$page_size = $rows * $cols;
		
		if ($title)
		{
			echo '<table width="100%"><tr><td><h2>' . $event->name . '</h2>';
			if ($logo_height > 0)
			{
				echo '</td><td align="right">';
				$icon = (abs($logo_height - ICON_HEIGHT) < abs($logo_height - TNAIL_HEIGHT));
				$event_pic->
					set($event->id, $event->name, $event->flags)->
					set($event->tournament_id, $event->tournament_name, $event->tournament_flags)->
					set($event->club_id, $event->club_name, $event->club_flags);
				$event_pic->show($icon ? ICONS_DIR : TNAILS_DIR, 0, $logo_height);
			}
			echo '</td></tr></table>';
		}
		
		$condition = new SQL(' AND g.event_id = ?', $event->id);
		$scoring_system = new ScoringSystem($event->scoring_id);
		$scores = new Scores($scoring_system, $condition);
		$players_count = count($scores->players);
			
		if ($players_count == 0)
		{
			$players = array();
			$page_size = $rows * $cols;
		
			echo '<center><h2>' . get_label('The event hasn\'t started yet. Current ratings:') . '</h2></center>';
			$query = new DbQuery(
				'SELECT u.id, u.name, r.nick_name, u.rating, u.games, u.games_won, u.flags, c.id, c.name, c.flags FROM registrations r' . 
				' JOIN users u ON r.user_id = u.id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' WHERE r.event_id = ? ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $page_size,
				$event->id);
			
			while ($row = $query->next())
			{
				$players[] = $row;
			}

			if (count($players) == 0)
			{
				$query = new DbQuery(
					'SELECT u.id, u.name, u.name, u.rating, u.games, u.games_won, u.flags, c.id, c.name, c.flags FROM users u' . 
					' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
					' WHERE c.id = ? ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $page_size,
					$event->club_id);
				while ($row = $query->next())
				{
					$players[] = $row;
				}
			}
			
			$number = 0;
			echo '<table width="100%"><tr>';
			for ($i = 0; $i < $cols; ++$i)
			{
				for ($j = 0; $j < $rows; ++$j)
				{
					
					if ($number >= count($players))
					{
						break;
					}
					list ($id, $name, $nick, $points, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags) = $players[$number++];
					
					if (!empty($nick) && $nick != $name)
					{
						$name = $nick . ' (' . $name . ')';
					}
					
					if ($j == 0)
					{
						echo '<td width="' . (100 / $cols) . '%" valign="top"><table class="bordered light" width="100%">';
						echo '<tr class="th-long darker">';
						echo '<td width="20"><button class="icon" onclick="window.location.replace(\'event_screen.php?id=' . $event->id . '&settings\')" title="' . get_label('Settings') . '"><img src="images/settings.png" border="0"></button></td>';
						echo '<td colspan="3">'.get_label('Player').'</td>';
						echo '<td width="60" align="center">'.get_label('Rating').'</td>';
						echo '<td width="60" align="center">'.get_label('Games played').'</td>';
						echo '<td width="60" align="center">'.get_label('Victories').'</td>';
						echo '</tr>';
					}
					
					echo '<tr>';
					echo '<td align="center" class="dark">' . $number . '</td>';
					echo '<td width="50">';
					$user_pic->set($id, $name, $flags);
					$user_pic->show(ICONS_DIR, 50);
					echo '</td><td>' . $name . '</td>';
					echo '<td width="50" align="center">';
					$club_pic->set($club_id, $club_name, $club_flags);
					$club_pic->show(ICONS_DIR, 40);
					echo '</td>';
					echo '<td align="center" class="lighter">';
					echo format_rating($points);
					echo '</td>';
					echo '<td align="center">' . $games_played . '</td>';
					echo '<td align="center">' . $games_won . '</td>';
					echo '</tr>';
				}
				echo '</table></td>';
			}
			echo '</tr></table>';
		}
		else
		{
			$number = 0;
			echo '<table width="100%"><tr>';
			for ($i = 0; $i < $cols; ++$i)
			{
				for ($j = 0; $j < $rows; ++$j)
				{
					if ($number >= $players_count)
					{
						break;
					}
					$score = $scores->players[$number++];
					
					if ($j == 0)
					{
						echo '<td width="' . (100 / $cols) . '%" valign="top"><table class="bordered light" width="100%">';
						echo '<tr class="th-long darker">';
						echo '<td width="20"><button class="icon" onclick="window.location.replace(\'event_screen.php?id=' . $event->id . '&settings\')" title="' . get_label('Settings') . '"><img src="images/settings.png" border="0"></button></td>';
						echo '<td colspan="3">'.get_label('Player').'</td>';
						echo '<td width="60" align="center">'.get_label('Points').'</td>';
						echo '<td width="60" align="center">'.get_label('Games played').'</td>';
						echo '<td width="60" align="center">'.get_label('Victories').'</td>';
						echo '</tr>';
					}
					
					echo '<tr>';
					echo '<td align="center" class="dark">' . $number . '</td>';
					echo '<td width="50">';
					$user_pic->set($score->id, $score->name, $score->flags);
					$user_pic->show(ICONS_DIR, 50);
					echo '</td><td>' . $score->name . '</td>';
					echo '<td width="50" align="center">';
					$club_pic->set($score->club_id, $score->club_name, $score->club_flags);
					$club_pic->show(ICONS_DIR, 40);
					echo '</td>';
					echo '<td align="center" class="lighter">';
					echo $score->points_str();
					echo '</td>';
					echo '<td align="center">' . $score->games_played . '</td>';
					echo '<td align="center">' . $score->games_won . '</td>';
					echo '</tr>';
				}
				echo '</table></td>';
			}
			echo '</tr></table>';
		}
		echo '</body>';
	}
}
catch (Exception $e)
{
	echo $e->getMessage();
	Exc::log($e);
}
?>

<script>
setTimeout(function() { window.location.replace(document.URL); }, <?php echo $refr * 1000; ?>);
</script>
