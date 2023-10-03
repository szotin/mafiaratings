<?php

require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';

define('FLAG_FILTER_RATING', 0x0001);
define('FLAG_FILTER_NO_RATING', 0x0002);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_RATING);

class Page extends SeriesPageBase
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
		$this->condition = new SQL(' WHERE g.is_canceled = FALSE AND g.result > 0 AND st.series_id = ?', $this->id);
		if ($this->filter & FLAG_FILTER_RATING)
		{
			$this->condition->add(' AND g.is_rating <> 0');
		}
		if ($this->filter & FLAG_FILTER_NO_RATING)
		{
			$this->condition->add(' AND g.is_rating = 0');
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
		
		list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities ct ON ct.id = a.city_id JOIN series_tournaments st ON st.tournament_id = g.tournament_id', $this->condition);
		$this->condition->add(get_roles_condition($this->roles));
		
		if (isset($_REQUEST['min']))
		{
			$this->min_games = $_REQUEST['min'];
		}
		else
		{
			$this->min_games = round($this->games_count / 100) * 10;
			$this->min_games -= $this->min_games % 10;
		}
		
		$this->nom = 0;
		if (isset($_REQUEST['nom']))
		{
			$this->nom = $_REQUEST['nom'];
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
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('players')));
		show_roles_select($this->roles, 'filterRoles()', get_label('Use only the stats of a specific role.'));
		
		echo ' <select id="min" onchange="filterNumber()" title="' . get_label('Show only players who played not less than a specific number of games.') . '">';
		$max_option = round($this->games_count / 20) * 10;
		for ($i = 0; $i <= $max_option; $i += 10)
		{
			if ($i == 0)
			{
				show_option($i, $this->min_games, get_label('All players'));
			}
			else
			{
				show_option($i, $this->min_games, get_label('[0] or more games', $i));
			}
		}
		echo '</select> ';
		show_checkbox_filter(array(get_label('rating games')), $this->filter);
		
		echo '</td><td align="right"><select id="nom" onchange="filterNom()" title="' . get_label('Nomination to view.') . '">';
		for ($i = 0; $i < count($this->noms); ++$i)
		{
			show_option($i, $this->nom, $this->noms[$i][0]);
		}
		echo '</select>';
		echo '</td></tr></table></p>';
		
		$query = new DbQuery(
			'SELECT p.user_id, nu.name, u.flags, count(*) as cnt, (' . $this->noms[$this->nom][1] . ') as abs, (' . $this->noms[$this->nom][1] . ') / (' . $this->noms[$this->nom][2] . ') as val, c.id, c.name, c.flags' .
				' FROM players p JOIN games g ON p.game_id = g.id' .
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id' .
				' JOIN users u ON u.id = p.user_id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id' . 
				' JOIN events e ON e.id = g.event_id' . 
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities ct ON ct.id = a.city_id',
			$this->condition);
		$query->add(' GROUP BY p.user_id HAVING cnt > ?', $this->min_games);
		
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
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">' . get_label('Player') . '</td>';
		echo '<td width="100" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="100" align="center">';
		if ($this->sort & 2)
		{
			if ($this->sort & 1)
			{
				echo '&#x25B2; <a href="javascript:sortBy(2)">';
			}
			else
			{
				echo '&#x25BC; <a href="javascript:sortBy(3)">';
			}
		}
		else
		{
			echo '<a href="javascript:sortBy(2)">';
		}
		echo get_label('Absolute') . '</a></td>';
		echo '<td width="100" align="center">';
		if (($this->sort & 2) == 0)
		{
			if ($this->sort & 1)
			{
				echo '&#x25B2; <a href="javascript:sortBy(0)">';
			}
			else
			{
				echo '&#x25BC; <a href="javascript:sortBy(1)">';
			}
		}
		else
		{
			echo '<a href="javascript:sortBy(0)">';
		}
		if ($this->noms[$this->nom][3])
		{
			echo '%';
		}
		else
		{
			echo get_label('Per game');
		}
		echo '</a></td></tr>';
		
		$number = 0;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $games_played, $abs, $val, $club_id, $club_name, $club_flags) = $row;

			echo '<tr class="light"><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50">';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '</td><td><a href="series_player.php?id=' . $this->id . '&user_id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 40);
			echo '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td width="100" align="center">' . number_format($abs, 0) . '</td>';
			echo '<td width="100" align="center">';
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
		echo '</table>';
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
