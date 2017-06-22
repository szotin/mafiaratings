<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

try
{
	initiate_session();
	check_maintenance();
	
	echo '<!DOCTYPE HTML>';
	echo '<html>';
	echo '<head>';
	echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
	echo '<script src="js/labels_' . $_lang_code . '.js"></script>';
	if (is_mobile())
	{
		echo '<link rel="stylesheet" href="mobile.css" type="text/css" media="screen" />';
	}
	else
	{
		echo '<link rel="stylesheet" href="desktop.css" type="text/css" media="screen" />';
	}
	echo '<link rel="stylesheet" href="common.css" type="text/css" media="screen" />';
	
	echo '</head><body>';
	
	$event = new Event();
	$event->load($_REQUEST['id']);
	
	$is_manager = ($_profile != NULL && $_profile->is_manager());
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
		
		$query = new DbQuery(
			'SELECT p.user_id, u.name, r.nick_name, SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = ? AND (o.flag & p.flags) <> 0)) as rating, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags FROM players p' . 
			' JOIN games g ON p.game_id = g.id' .
			' JOIN users u ON p.user_id = u.id' .
			' JOIN registrations r ON r.event_id = g.event_id AND r.user_id = p.user_id' .
			' WHERE g.event_id = ? GROUP BY p.user_id ORDER BY rating DESC, games, won DESC, u.id LIMIT ' . $page_size,
			$event->scoring_id, $event->id);
		
		$players = array();
		while ($row = $query->next())
		{
			$players[] = $row;
		}
		
		if ($title)
		{
			echo '<table width="100%"><tr><td><h2>' . $event->name . '</h2>';
			if ($logo_height > 0)
			{
				echo '</td><td align="right">';
				$icon = (abs($logo_height - ICON_HEIGHT) < abs($logo_height - TNAIL_HEIGHT));
				$event->show_pic($icon ? ICONS_DIR : TNAILS_DIR, 0, $logo_height);
			}
			echo '</td></tr></table>';
		}
			
		if (count($players) == 0)
		{
			$page_size = $rows * $cols;
		
			echo '<center><h2>' . get_label('The event hasn\'t started yet. Current ratings:') . '</h2></center>';
			$query = new DbQuery(
				'SELECT u.id, u.name, u.name, r.rating as rating, r.games as games, r.games_won as won, u.flags' . 
					' FROM users u, club_ratings r, events e, registrations reg WHERE reg.event_id = e.id AND reg.user_id = u.id AND u.id = r.user_id AND e.id = ? AND r.club_id = e.club_id' .
					' AND r.role = 0 AND type_id = (SELECT id FROM rating_types WHERE def = 1 LIMIT 1) ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT ' . $page_size,
				$event->id);
			while ($row = $query->next())
			{
				$players[] = $row;
			}

			if (count($players) == 0)
			{
				$query = new DbQuery(
					'SELECT u.id, u.name, u.name, r.rating as rating, r.games as games, r.games_won as won, u.flags' . 
						' FROM users u, club_ratings r, events e WHERE u.id = r.user_id AND e.id = ? AND r.club_id = e.club_id' .
						' AND r.role = 0 AND type_id = (SELECT id FROM rating_types WHERE def = 1 LIMIT 1) ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT ' . $page_size,
					$event->id);
				while ($row = $query->next())
				{
					$players[] = $row;
				}
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
				list ($id, $name, $nick, $points, $games_played, $games_won, $flags) = $players[$number++];
				
				if ($nick != $name)
				{
					$name = $nick . ' (' . $name . ')';
				}
				
				if ($j == 0)
				{
					echo '<td width="' . (100 / $cols) . '%" valign="top"><table class="bordered light" width="100%">';
					echo '<tr class="th-long darker">';
					echo '<td width="20"><button class="icon" onclick="window.location.replace(\'event_screen.php?id=' . $event->id . '&settings\')" title="' . get_label('Settings') . '"><img src="images/settings.png" border="0"></button></td>';
					echo '<td colspan="2">'.get_label('Player').'</td>';
					echo '<td width="60" align="center">'.get_label('Points').'</td>';
					echo '<td width="60" align="center">'.get_label('Games played').'</td>';
					echo '<td width="60" align="center">'.get_label('Games won').'</td>';
					echo '</tr>';
				}
				
				echo '<tr>';
				echo '<td align="center" class="dark">' . $number . '</td>';
				echo '<td width="50">';
				show_user_pic($id, $flags, ICONS_DIR, 50, 50);
				echo '</td><td>' . $name . '</td>';
				echo '<td align="center" class="lighter">';
				echo format_score($points);
				echo '</td>';
				echo '<td align="center">' . $games_played . '</td>';
				echo '<td align="center">' . $games_won . '</td>';
				echo '</tr>';
			}
			echo '</table></td>';
		}
		echo '</tr></table>';
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
