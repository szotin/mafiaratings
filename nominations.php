<?php

require_once 'include/general_page_base.php';
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

class Page extends GeneralPageBase
{
	private $roles;
	private $min_games;
	private $games_count;
	private $condition;
	private $noms;
	private $nom;
	private $sort;

	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		
		$this->noms = array(
			array(get_label('Rating'), 'SUM(p.rating_earned)', 'count(*)', 0),
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
		
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$this->roles = (int)$_REQUEST['roles'];
		}
		
		date_default_timezone_set(get_timezone());
		$this->condition = new SQL();
		if ($this->filter & FLAG_FILTER_TOURNAMENT)
		{
			$this->condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($this->filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$this->condition->add(' AND g.tournament_id IS NULL');
		}
		if ($this->filter & FLAG_FILTER_RATING)
		{
			$this->condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($this->filter & FLAG_FILTER_NO_RATING)
		{
			$this->condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$this->condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$this->condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$this->condition->add(' AND g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$this->condition->add(' AND g.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$this->condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$this->condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		
		list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities ct ON ct.id = a.city_id WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0 ', $this->condition);
		$this->condition->add(get_roles_condition($this->roles));
		
		if (isset($_REQUEST['min']))
		{
			$this->min_games = (int)$_REQUEST['min'];
		}
		else
		{
			// Those who played 1000 games or more: everyone else is a player of a few evenings. Fewer when
			// the filters leave fewer games, so that the list is not empty.
			$this->min_games = min(1000, (int)round($this->games_count / 100) * 10);
		}
		
		$this->nom = 0;
		if (isset($_REQUEST['nom']))
		{
			$this->nom = (int)$_REQUEST['nom'];
		}
		if ($this->nom >= count($this->noms))
		{
			$this->nom = 0;
		}
		
		$this->sort = 0;
		if (isset($_REQUEST['sort']))
		{
			$this->sort = $_REQUEST['sort'];
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang;
		
		// The toolbar of a list (see new_look.css): the place, the dates and how many games a player
		// needs on the first line; on the second the kinds of games, and at its right end what is
		// counted - the nomination and the role.
		echo '<div class="toolbar">';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('players')));
		echo '<div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		echo '<select id="min" class="tb-end" onchange="filterNumber()" title="' . get_label('Show only players who played not less than a specific number of games.') . '">';
		echo card_min_games_options($this->min_games, $this->games_count);
		echo '</select>';
		echo '<div class="tb-line tb-start">';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $this->filter);
		echo '<div class="tb-pair">';
		echo '<select id="nom" onchange="filterNom()" title="' . get_label('Nomination to view.') . '">';
		for ($i = 0; $i < count($this->noms); ++$i)
		{
			show_option($i, $this->nom, $this->noms[$i][0]);
		}
		echo '</select>';
		show_roles_select($this->roles, 'filterRoles()', get_label('Use only the stats of a specific role.'));
		echo '</div></div>';
		echo '</div>';
		
		$query = new DbQuery(
			'SELECT p.user_id, nu.name, u.flags, count(*) as cnt, (' . $this->noms[$this->nom][1] . ') as abs, (' . $this->noms[$this->nom][1] . ') / (' . $this->noms[$this->nom][2] . ') as val, c.id, nc.name, c.flags' .
				' FROM players p'.
				' JOIN games g ON p.game_id = g.id'.
				' JOIN users u ON u.id = p.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id'.
				' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' JOIN events e ON e.id = g.event_id'.
				' JOIN addresses a ON a.id = e.address_id'.
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0',
			$this->condition);
		$query->add(' GROUP BY p.user_id HAVING cnt >= ?', $this->min_games);
		
		if ($this->sort & 2)
		{
			if ($this->sort & 1)
			{
				$query->add(' ORDER BY abs, val, cnt DESC LIMIT 10');
			}
			else
			{
				$query->add(' ORDER BY abs DESC, val DESC, cnt DESC LIMIT 10');
			}
		}
		else if ($this->sort & 1)
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
		if ($this->sort & 2)
		{
			echo '<th class="num" scope="col" aria-sort="' . (($this->sort & 1) ? 'ascending' : 'descending') . '"><a class="th-sort" href="' . (($this->sort & 1) ? 'javascript:sortBy(2)' : 'javascript:sortBy(3)') . '">';
		}
		else
		{
			echo '<th class="num" scope="col"><a class="th-sort" href="javascript:sortBy(2)">';
		}
		echo get_label('Absolute') . '</a></th>';
		if (($this->sort & 2) == 0)
		{
			echo '<th class="num" scope="col" aria-sort="' . (($this->sort & 1) ? 'ascending' : 'descending') . '"><a class="th-sort" href="' . (($this->sort & 1) ? 'javascript:sortBy(0)' : 'javascript:sortBy(1)') . '">';
		}
		else
		{
			echo '<th class="num" scope="col"><a class="th-sort" href="javascript:sortBy(0)">';
		}
		echo ($this->noms[$this->nom][3] ? '%' : get_label('Per game')) . '</a></th>';
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
			echo '<td class="num' . (($this->sort & 2) ? ' strong' : '') . '">' . number_format($abs, 0) . '</td>';
			echo '<td class="num' . (($this->sort & 2) == 0 ? ' strong' : '') . '">';
			if ($this->noms[$this->nom][3])
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
		parent::js();
?>
		var sort = <?php echo $this->sort; ?>;
		function sortBy(s)
		{
			if (s != sort)
			{
				goTo({sort: s});
			}
		}
		
		function filterRoles()
		{
			goTo({roles: $("#roles").val()});
		}
		
		function filterNumber()
		{
			goTo({min: $("#min").val()});
		}
		
		function filterNom()
		{
			goTo({nom: $("#nom").val()});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Nomination winners.'));

?>
