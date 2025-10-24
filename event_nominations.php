<?php

require_once 'include/event.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';

define('FLAG_FILTER_RATING', 0x0001);
define('FLAG_FILTER_NO_RATING', 0x0002);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_RATING);

class Page extends EventPageBase
{
	private $games_count;

	protected function prepare()
	{
		parent::prepare();
		
		list($timezone) = Db::record(get_label('event'), 'SELECT c.timezone FROM events e JOIN addresses a ON e.address_id = a.id JOIN cities c ON a.city_id = c.id WHERE e.id = ?', $this->id);
		date_default_timezone_set($timezone);
		
		list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE g.event_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
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
			$nom = $_REQUEST['nom'];
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
		
		echo '<table class="transp" width="100%"><tr><td>';
		show_roles_select($roles, 'filterChanged()', get_label('Use only the stats of a specific role.'));
		echo '&emsp;&emsp;';
		show_date_filter();
		echo '&emsp;&emsp;';
		show_checkbox_filter(array(get_label('rating games')), $filter, 'filterChanged');
		echo '</td><td align="right">';
		echo '<select id="nom" onchange="filterChanged()">';
		for ($i = 0; $i < count($noms); ++$i)
		{
			show_option($i, $nom, $noms[$i][0]);
		}
		echo '</select>';
		echo '</td></tr></table>';
		
		$condition = get_roles_condition($roles);
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
			'SELECT p.user_id, nu.name, u.flags, count(*) as cnt,' . 
			' (' . $noms[$nom][1] . ') as abs, (' . $noms[$nom][1] . ') / (' . $noms[$nom][2] . ') as val,' .
			' c.id, c.name, c.flags, eu.nickname, eu.flags, tu.flags, cu.flags' .
				' FROM players p' .
				' JOIN games g ON p.game_id = g.id' .
				' JOIN users u ON u.id = p.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' LEFT OUTER JOIN event_regs eu ON eu.event_id = ? AND eu.user_id = u.id' .
				' LEFT OUTER JOIN tournament_regs tu ON tu.tournament_id = ? AND tu.user_id = u.id' .
				' LEFT OUTER JOIN club_regs cu ON cu.club_id = ? AND cu.user_id = u.id' .
				' WHERE g.event_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0',
				$this->id, $this->tournament_id, $this->club_id, $this->id, $condition);
		$query->add(' GROUP BY p.user_id');
		
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
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">' . get_label('Player') . '</td>';
		echo '<td width="100" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="100" align="center">';
		if ($sort & 2)
		{
			if ($sort & 1)
			{
				echo '&#x25B2; <a href="javascript:goTo({sort:2})">';
			}
			else
			{
				echo '&#x25BC; <a href="javascript:goTo({sort:3})">';
			}
		}
		else
		{
			echo '<a href="javascript:goTo({sort:2})">';
		}
		echo get_label('Absolute') . '</a></td>';
		echo '<td width="100" align="center">';
		if (($sort & 2) == 0)
		{
			if ($sort & 1)
			{
				echo '&#x25B2; <a href="javascript:goTo({sort:0})">';
			}
			else
			{
				echo '&#x25BC; <a href="javascript:goTo({sort:1})">';
			}
		}
		else
		{
			echo '<a href="javascript:goTo({sort:0})">';
		}
		if ($noms[$nom][3])
		{
			echo '%';
		}
		else
		{
			echo get_label('Per game');
		}
		echo '</a></td></tr>';
		
		$event_reg_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));

		$number = 0;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $games_played, $abs, $val, $club_id, $club_name, $club_flags, $user_nickname, $event_reg_flags, $tournament_reg_flags, $club_reg_flags) = $row;

			echo '<tr class="light"><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50">';
			$event_reg_pic->
				set($id, $user_nickname, $event_reg_flags, 'e' . $this->id)->
				set($id, $name, $tournament_reg_flags, 't' . $this->tournament_id)->
				set($id, $name, $club_reg_flags, 'c' . $this->club_id)->
				set($id, $name, $flags);
			$event_reg_pic->show(ICONS_DIR, true, 50);
			echo '</td><td><a href="event_player.php?id=' . $this->id . '&user_id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 40);
			echo '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td width="100" align="center">' . number_format($abs, 0) . '</td>';
			echo '<td width="100" align="center">';
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
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({roles: $('#roles').val(), filter: checkboxFilterFlags(), nom: $('#nom').val() });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Nomination Winners'));

?>