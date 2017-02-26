<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';

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
	
	$rows = 10;
	if (isset($_REQUEST['r']))
	{
		$rows = $_REQUEST['r'];
	}
	
	$cols = 2;
	if (isset($_REQUEST['c']))
	{
		$cols = $_REQUEST['c'];
	}
	
	$icon = 1;
	if (isset($_REQUEST['i']))
	{
		$icon = $_REQUEST['i'];
	}
	
	$logo = $icon ? ICON_HEIGHT : TNAIL_HEIGHT;
	if (isset($_REQUEST['l']))
	{
		$logo = $_REQUEST['l'];
	}
	
	$page_size = $rows * $cols;
	
	$digits = 0;
	$div = 1;
	if ($event->system_id == NULL)
	{
		$query = new DbQuery(
			'SELECT p.user_id, u.name, r.nick_name, SUM(p.rating) as rating, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags FROM players p' . 
				' JOIN games g ON p.game_id = g.id' .
				' JOIN users u ON p.user_id = u.id' .
				' JOIN registrations r ON r.event_id = g.event_id AND r.user_id = p.user_id' .
				' WHERE g.event_id = ? GROUP BY p.user_id ORDER BY rating DESC, games, won DESC, u.id LIMIT ' . $page_size,
			$event->id);
	}
	else
	{
		list ($digits) = Db::record(get_label('rating system'), 'SELECT digits FROM systems WHERE id = ' . $event->system_id);
		for ($i = 0; $i < $digits; ++$i)
		{
			$div *= 10;
		}
		$query = new DbQuery(
			'SELECT p.user_id, u.name, r.nick_name, SUM((SELECT SUM(o.points) FROM points o WHERE o.system_id = ? AND (o.flag & p.flags) <> 0)) as rating, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags FROM players p' . 
			' JOIN games g ON p.game_id = g.id' .
			' JOIN users u ON p.user_id = u.id' .
			' JOIN registrations r ON r.event_id = g.event_id AND r.user_id = p.user_id' .
			' WHERE g.event_id = ? GROUP BY p.user_id ORDER BY rating DESC, games, won DESC, u.id LIMIT ' . $page_size,
			$event->system_id, $event->id);
	}
	
	$players = array();
	while ($row = $query->next())
	{
		$players[] = $row;
	}
	
	echo '<table width="100%"><tr><td><h2>' . $event->name . '</h2>';
	if ($logo > 0)
	{
		echo '</td><td align="right">';
		$event->show_pic($icon ? ICONS_DIR : TNAILS_DIR, 0, $logo);
	}
	echo '</td></tr></table>';
		
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
			list ($id, $name, $nick, $rating, $games_played, $games_won, $flags) = $players[$number++];
			
			if ($nick != $name)
			{
				$name = $nick . ' (' . $name . ')';
			}
			
			if ($j == 0)
			{
				echo '<td width="' . (100 / $cols) . '%" valign="top"><table class="bordered light" width="100%">';
				echo '<tr class="th-long darker"><td width="20">&nbsp;</td>';
				echo '<td colspan="2">'.get_label('Player').'</td>';
				echo '<td width="60" align="center">'.get_label('Rating').'</td>';
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
			if ($digits == 0)
			{
				echo $rating;
			}
			else
			{
				echo number_format($rating/$div, $digits);
			}
			echo '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			echo '</tr>';
		}
		echo '</table></td>';
	}
	echo '</tr></table></body>';
}
catch (Exception $e)
{
	echo $e->getMessage();
}

$refr = 60;
if (isset($_REQUEST['refr']))
{
	$refr = $_REQUEST['refr'];
}
?>

<script>
setTimeout(function() { window.location.replace(document.URL); }, <?php echo $refr * 1000; ?>);
</script>
