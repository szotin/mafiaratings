<?php

require_once '../include/session.php';
require_once '../include/picture.php';
require_once '../include/cards.php';

define('PLAYERS_COUNT', 30); // six in a row, five rows: all of them in the dialog at once
define('GAMES_COUNT_PERIOD', 24 * 60 * 60 * 365); // one year

initiate_session();

try
{
	$name = '';
	if (isset($_REQUEST['name']))
	{
		$name = $_REQUEST['name'];
	}

	$club_id = -1;
	if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
	}

	$city_id = -1;
	if (isset($_REQUEST['city_id']))
	{
		$city_id = (int)$_REQUEST['city_id'];
	}

	$area_id = -1;
	if (isset($_REQUEST['area_id']))
	{
		$area_id = (int)$_REQUEST['area_id'];
	}
	
	$games_count_query = new SQL('SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id WHERE p.user_id = u.id AND g.start_time > UNIX_TIMESTAMP() - ' . GAMES_COUNT_PERIOD);
	if ($club_id > 0)
	{
		$games_count_query->add(' AND g.club_id = ?', $club_id);
	}
		
	$query = new DbQuery('SELECT DISTINCT u.id, nu.name, u.flags, c.id, nc.name, a.id, na.name, ct.id, nct.name, IF(nu.name = ?, 0, IF(LOCATE(?, nu.name) = 1, 1, 2)) as mtch, (', $name, $name, $games_count_query);
	$query->add(
			') as games_count' .
			' FROM names n'.
			' JOIN users u ON u.name_id = n.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON ct.id = u.city_id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0 ' .
			' LEFT OUTER JOIN cities a ON a.id = ct.area_id' .
			' LEFT OUTER JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0 ' .
			' WHERE TRUE');
	if (!empty($name))
	{
		$name_wildcard = '%' . $name . '%';
		$query->add(
				' AND (n.name LIKE ? OR' .
				' u.email LIKE ? OR' .
				' u.id IN (SELECT DISTINCT user_id FROM event_regs WHERE nickname LIKE ?))',
			$name_wildcard,
			$name_wildcard,
			$name_wildcard);
	}
	else if ($club_id > 0)
	{
		$query->add(' AND u.id IN (SELECT DISTINCT user_id FROM club_regs WHERE club_id = ?)', $club_id);
	}
	$query->add(' ORDER BY mtch, games_count DESC LIMIT ' . PLAYERS_COUNT);
	
//		echo $query->get_parsed_sql();
	// Each player a small tile: the picture, the name, the city.
	$pic = new Picture(USER_PICTURE);
	$user_count = 0;
	while ($row = $query->next())
	{
		list ($p_id, $p_name, $p_flags, $p_club_id, $p_club_name, $p_area_id, $p_area_name, $p_city_id, $p_city_name) = $row;
		if ($user_count++ == 0)
		{
			echo '<div class="reg-grid">';
		}
		echo '<a href="#" class="reg-p" onclick="userSelected(' . $p_id . '); return false;" title="' . htmlspecialchars($p_name, ENT_QUOTES, 'UTF-8') . '">' . card_avatar($pic, $p_id, $p_name, $p_flags, 'lg');
		echo '<b>' . $p_name . '</b><span>' . $p_city_name . '</span></a>';
	}
	if ($user_count > 0)
	{
		echo '</div>';
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>