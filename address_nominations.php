<?php

require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_RATING);

class Page extends AddressPageBase
{
	private $min_games;
	private $games_count;

	protected function prepare()
	{
		parent::prepare();
		
		list($timezone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON a.city_id = c.id WHERE a.id = ?', $this->id);
		date_default_timezone_set($timezone);
		
		list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON g.event_id = e.id WHERE e.address_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
		if (isset($_REQUEST['min']))
		{
			$this->min_games = (int)$_REQUEST['min'];
		}
		else
		{
			$this->min_games = (int)round($this->games_count / 100) * 10;
			$this->min_games -= $this->min_games % 10;
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang;
		
		$noms = array(
			array(get_label('Ratings'), 'SUM(p.rating_earned)', 'count(*)', 0),
			array(get_label('Number of wins'), 'SUM(p.won)', 'count(*)', 1),
			array(get_label('Voted against civilians'), 'SUM(p.voted_civil)', 'SUM(p.voted_civil + p.voted_mafia + p.voted_sheriff)', 1),
			array(get_label('Voted against mafia'), 'SUM(p.voted_mafia)', 'SUM(p.voted_civil + p.voted_mafia + p.voted_sheriff)', 1),
			array(get_label('Voted against sheriff'), 'SUM(p.voted_sheriff)', 'SUM(p.voted_civil + p.voted_mafia + p.voted_sheriff)', 1),
			array(get_label('Voted by civilians'), 'SUM(p.voted_by_civil)', 'SUM(p.voted_by_civil + p.voted_by_mafia + p.voted_by_sheriff)', 1),
			array(get_label('Voted by mafia'), 'SUM(p.voted_by_mafia)', 'SUM(p.voted_by_civil + p.voted_by_mafia + p.voted_by_sheriff)', 1),
			array(get_label('Voted by sheriff'), 'SUM(p.voted_by_sheriff)', 'SUM(p.voted_by_civil + p.voted_by_mafia + p.voted_by_sheriff)', 1),
			array(get_label('Nominated civilians'), 'SUM(p.nominated_civil)', 'SUM(p.nominated_civil + p.nominated_mafia + p.nominated_sheriff)', 1),
			array(get_label('Nominated mafia'), 'SUM(p.nominated_mafia)', 'SUM(p.nominated_civil + p.nominated_mafia + p.nominated_sheriff)', 1),
			array(get_label('Nominated sheriff'), 'SUM(p.nominated_sheriff)', 'SUM(p.nominated_civil + p.nominated_mafia + p.nominated_sheriff)', 1),
			array(get_label('Nominated by civilians'), 'SUM(p.nominated_by_civil)', 'SUM(p.nominated_by_civil + p.nominated_by_mafia + p.nominated_by_sheriff)', 1),
			array(get_label('Nominated by mafia'), 'SUM(p.nominated_by_mafia)', 'SUM(p.nominated_by_civil + p.nominated_by_mafia + p.nominated_by_sheriff)', 1),
			array(get_label('Nominated by sheriff'), 'SUM(p.nominated_by_sheriff)', 'SUM(p.nominated_by_civil + p.nominated_by_mafia + p.nominated_by_sheriff)', 1),
			array(get_label('Survived'), 'SUM(IF(p.kill_type = ' . KILL_TYPE_SURVIVED . ', 1, 0))', 'count(*)', 1),
			array(get_label('Killed in day'), 'SUM(IF(p.kill_type = ' . KILL_TYPE_DAY . ', 1, 0))', 'count(*)', 1),
			array(get_label('Killed in night'), 'SUM(IF(p.kill_type = ' . KILL_TYPE_NIGHT . ', 1, 0))', 'count(*)', 1),
			array(get_label('Killed first night'), 'SUM(IF(p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.kill_round = 1, 1, 0))', 'count(*)', 1),
			array(get_label('Warnings'), 'SUM(p.warns)', 'count(*)', 0),
			array(get_label('Arranged'), 'SUM(IF(p.was_arranged >= 0, 1, 0))', 'count(*)', 1),
			array(get_label('Arranged first night'), 'SUM(IF(p.was_arranged = 0, 1, 0))', 'count(*)', 1),
			array(get_label('Checked by don'), 'SUM(IF(p.checked_by_don >= 0, 1, 0))', 'count(*)', 1),
			array(get_label('Checked by sheriff'), 'SUM(IF(p.checked_by_sheriff >= 0, 1, 0))', 'count(*)', 1),
		);
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$nom = 0;
		if (isset($_REQUEST['nom']))
		{
			$nom = (int)$_REQUEST['nom'];
		}
		if ($nom >= count($noms))
		{
			$nom = 0;
		}
		
		$roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}
		
		$sort = 0;
		if (isset($_REQUEST['sort']))
		{
			$sort = $_REQUEST['sort'];
		}
		
	
		// The toolbar of a list (see new_look.css): the place, the dates and how many games a player
		// needs on the first line; on the second the kinds of games, and at its right end what is
		// counted - the nomination and the role.
		echo '<div class="toolbar">';
		echo '<div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		echo '<select id="min" class="tb-end" onchange="filterChanged()" title="' . get_label('Show only players who played not less than a specific number of games.') . '">';
		echo card_min_games_options($this->min_games, $this->games_count);
		echo '</select>';
		echo '<div class="tb-line tb-start">';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $filter, 'filterChanged');
		echo '<div class="tb-pair">';
		echo '<select id="nom" onchange="filterChanged()" title="' . get_label('Nomination to view.') . '">';
		for ($i = 0; $i < count($noms); ++$i)
		{
			show_option($i, $nom, $noms[$i][0]);
		}
		echo '</select>';
		show_roles_select($roles, 'filterChanged()', get_label('Use only the stats of a specific role.'));
		echo '</div></div>';
		echo '</div>';
		
		$condition = get_roles_condition($roles);
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}

		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		$query = new DbQuery(
			'SELECT p.user_id, nu.name, u.flags, count(*) as cnt, (' . $noms[$nom][1] . ') as abs, (' . $noms[$nom][1] . ') / (' . $noms[$nom][2] . ') as val, c.id, nc.name, c.flags' .
				' FROM players p' .
				' JOIN games g ON p.game_id = g.id' .
				' JOIN users u ON u.id = p.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN events e ON g.event_id = e.id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' WHERE e.address_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0',
			$this->id, $condition);
		$query->add(' GROUP BY p.user_id HAVING cnt >= ?', $this->min_games);
		
		if ($sort & 2)
		{
			if ($sort & 1)
			{
				$query->add(' ORDER BY abs, val, cnt DESC LIMIT 10');
			}
			else
			{
				$query->add(' ORDER BY abs DESC, val DESC, cnt DESC LIMIT 10');
			}
		}
		else if ($sort & 1)
		{
			$query->add(' ORDER BY val, abs, cnt DESC LIMIT 10');
		}
		else
		{
			$query->add(' ORDER BY val DESC, abs DESC, cnt DESC LIMIT 10');
		}
		
		// The table of the v3 design (see ratings.php): the place, the player, the club, the numbers.
		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Games played') . '</th>';
		// Two columns to sort by, each either way: a click on the one it is sorted by turns the order over.
		if ($sort & 2)
		{
			echo '<th class="num" scope="col" aria-sort="' . (($sort & 1) ? 'ascending' : 'descending') . '"><a class="th-sort" href="' . (($sort & 1) ? 'javascript:goTo({sort:2})' : 'javascript:goTo({sort:3})') . '">';
		}
		else
		{
			echo '<th class="num" scope="col"><a class="th-sort" href="javascript:goTo({sort:2})">';
		}
		echo get_label('Absolute') . '</a></th>';
		if (($sort & 2) == 0)
		{
			echo '<th class="num" scope="col" aria-sort="' . (($sort & 1) ? 'ascending' : 'descending') . '"><a class="th-sort" href="' . (($sort & 1) ? 'javascript:goTo({sort:0})' : 'javascript:goTo({sort:1})') . '">';
		}
		else
		{
			echo '<th class="num" scope="col"><a class="th-sort" href="javascript:goTo({sort:0})">';
		}
		echo ($noms[$nom][3] ? '%' : get_label('Per game')) . '</a></th>';
		echo '</tr></thead><tbody>';
		
		$number = 0;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $games_played, $abs, $val, $club_id, $club_name, $club_flags) = $row;

			echo '<tr><td class="rk"><span class="medal' . ($number <= 3 ? ' m' . $number : '') . '">' . sprintf('%02d', $number) . '</span></td>';
			$this->user_pic->set($id, $name, $flags);
			echo '<td><a class="who" href="user_info.php?id=' . $id . '&bck=1">' . card_avatar_of($this->user_pic, $id, $name, '') . '<span class="who-txt"><span class="who-name">' . $name . '</span></span></a></td>';
			echo '<td class="p2">' . card_club($this->club_pic, $club_id, $club_name, $club_flags) . '</td>';
			echo '<td class="num p3 dim">' . $games_played . '</td>';
			echo '<td class="num' . (($sort & 2) ? ' strong' : '') . '">' . number_format($abs, 0) . '</td>';
			echo '<td class="num' . (($sort & 2) == 0 ? ' strong' : '') . '">';
			if ($noms[$nom][3])
			{
				echo number_format($val * 100, 1) . '%';
			}
			else
			{
				echo number_format($val, 2);
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({roles: $('#roles').val(), filter: checkboxFilterFlags(), min: $('#min').val(), nom: $('#nom').val() });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Nomination Winners'));

?>
