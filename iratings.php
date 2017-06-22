<?php

require_once 'include/db.php';
require_once 'include/image.php';
require_once 'include/localization.php';
require_once 'include/log.php';
require_once 'include/user.php';

try
{
	$css = '';
	if (isset($_REQUEST['css']))
	{
		$css = $_REQUEST['css'];
	}
	
	$_lang_code = 'ru';
	if (isset($_REQUEST['lang']))
	{
		$_lang_code = $_REQUEST['lang'];
	}
	require_once 'include/languages/' . $_lang_code . '/labels.php';
	$_default_date_translations = include('include/languages/' . $_lang_code . '/date.php');

	$page_size = 15;
	if (isset($_REQUEST['psize']))
	{
		$page_size = $_REQUEST['psize'];
	}

	$page = 0;
	if (isset($_REQUEST['p']))
	{
		$page = $_REQUEST['p'];
	}

	$club_id = -1;
	if (isset($_REQUEST['club']))
	{
		$club_id = $_REQUEST['club'];
	}
	
	if (isset($_REQUEST['type']))
	{
		$type_id = $_REQUEST['type'];
	}
	else
	{
		list($type_id) = Db::record(get_label('points'), 'SELECT id FROM rating_types ORDER BY def DESC, id LIMIT 1');
	}

	$global_rating = (isset($_REQUEST['gr']) && $_REQUEST['gr']);
	$no_header = (isset($_REQUEST['h']) && !$_REQUEST['h']);

	$view_id = 0;
	if (isset($_REQUEST['roles']))
	{
		switch ($_REQUEST['roles'])
		{
			case 'a':
				$view_id = 0;
				break;
			case 'r':
				$view_id = 1;
				break;
			case 'b':
				$view_id = 2;
				break;
			case 'c':
				$view_id = 3;
				break;
			case 's':
				$view_id = 4;
				break;
			case 'm':
				$view_id = 5;
				break;
			case 'd':
				$view_id = 6;
				break;
			default:
				$view_id = $_REQUEST['roles'];
				break;
		}
	}
	
	$cols = 'nhurgwpa';
	if (isset($_REQUEST['cols']))
	{
		$cols = $_REQUEST['cols'];
	}

	if ($club_id <= 0)
	{
		$condition = new SQL(' FROM ratings r JOIN users u ON u.id = r.user_id WHERE');
	}
	else if ($global_rating)
	{
		$condition = new SQL(' FROM ratings r JOIN users u ON r.user_id = u.id JOIN user_clubs c ON c.user_id = r.user_id WHERE c.club_id = ? AND', $club_id);
	}
	else
	{
		$condition = new SQL(' FROM club_ratings r JOIN users u ON u.id = r.user_id WHERE r.club_id = ? AND', $club_id);
	}
	$condition->add(' r.role = ? AND type_id = ?', $view_id, $type_id);

	//		list ($count) = Db::record(get_label('rating'), 'SELECT count(*)', $condition);
	//		show_pages_navigation($page_size, $count);
			
	$query = new DbQuery('SELECT u.id, u.name, r.rating, r.games, r.games_won, u.flags', $condition);
	$query->add(' ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT ' . ($page * $page_size) . ',' . $page_size);
			
	$cols_count = strlen($cols);
	
	$style = '';
	if (isset($_REQUEST['style']))
	{
		$style = $_REQUEST['style'];
	}
	
	echo '<html>';
	echo '<head>';
	echo '<title>Points</title>';
	echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
	if ($style != '')
	{
		echo '<link rel="stylesheet" href="' . $style . '" type="text/css" media="screen" />';
	}
	echo '</head>';
	echo '<body>';
	
	echo '<table border="1" cellspacing="0" cellpadding="3">';
	if (!$no_header)
	{
		echo '<tr>';
		for ($i = 0; $i < $cols_count; ++$i)
		{
			switch ($cols[$i])
			{
				case 'n':
					echo '<th width="20">&nbsp;</th>';
					break;
				case 'h':
					echo '<th width="50">&nbsp;</th>';
					break;
				case 'u':
					echo '<th>'.get_label('Player').'</th>';
					break;
				case 'r':
					echo '<th width="60">'.get_label('Points').'</th>';
					break;
				case 'g':
					echo '<th width="60">'.get_label('Games played').'</th>';
					break;
				case 'w':
					echo '<th width="60">'.get_label('Games won').'</th>';
					break;
				case 'p':
					echo '<th width="60">'.get_label('Winning %').'</th>';
					break;
				case 'a':
					echo '<th width="60">'.get_label('Points per game').'</th>';
					break;
			}
		}
		echo '</tr>';
	}

	$number = $page * $page_size;
	while ($row = $query->next())
	{
		++$number;
		list ($id, $name, $points, $games_played, $games_won, $flags) = $row;
		
		echo '<tr>';
		for ($i = 0; $i < $cols_count; ++$i)
		{
			switch ($cols[$i])
			{
				case 'n':
					echo '<td width="20" align="center">' . $number . '</td>';
					break;
				case 'h':
					echo '<td width="50"><a href="../user_info.php?id=' . $id . '" target="blank">';
					show_user_pic($id, $flags, ICONS_DIR, 50, 50);
					echo '</a></td>';
					break;
				case 'u':
					echo '<td><a href="../user_info.php?id=' . $id . '" target="blank">' . $name . '</a></td>';
					break;
				case 'r':
					echo '<td width="60" align="center">' . $points . '</td>';
					break;
				case 'g':
					echo '<td width="60" align="center">' . $games_played . '</td>';
					break;
				case 'w':
					echo '<td width="60" align="center">' . $games_won . '</td>';
					break;
				case 'p':
					if ($games_played != 0)
					{
						echo '<td width="60" align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
					}
					else
					{
						echo '<td width="60" align="center">&nbsp;</td>';
					}
					break;
				case 'a':
					if ($games_played != 0)
					{
						echo '<td width="60" align="center">' . number_format($points/$games_played, 2) . '</td>';
					}
					else
					{
						echo '<td width="60" align="center">&nbsp;</td>';
					}
					break;
			}
		}
		echo '</tr>';
	}
	echo '</table>';
	echo '</body>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>