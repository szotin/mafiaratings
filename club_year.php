<?php

require_once 'include/page_base.php';
require_once 'include/game_player.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('VIEW_OVERAL', 0);
define('VIEW_NOM_WINNERS', 1);

class Page extends ClubPageBase
{
	private $this_year;
	private $year;
	private $view;
	private $from;
	private $to;
	private $min_games;
	private $games_count;
	
	private $views;

	protected function prepare()
	{
		parent::prepare();
		
		$this->views = array(
			get_label('Overal stats'),
			get_label('Nomination winners')
		);
		
		list($timezone) = Db::record(get_label('club'), 'SELECT i.timezone FROM clubs c JOIN cities i ON c.city_id = i.id WHERE c.id = ?', $this->id);
		date_default_timezone_set($timezone);
		
		$this->this_year = date('Y', time());
		if (isset($_REQUEST['year']))
		{
			$this->year = $_REQUEST['year'];
		}
		else
		{
			$this->year = $this->this_year;
		}
		
		if ($this->year > 0)
		{
			$this->from = mktime(0, 0, 0, 1, 1, $this->year);
			$this->to = mktime(0, 0, 0, 1, 1, $this->year + 1);
		}
		
		$this->view = VIEW_OVERAL;
		if (isset($_REQUEST['view']))
		{
			$this->view = $_REQUEST['view'];
		}
		if ($this->view >= count($this->views))
		{
			$this->view = VIEW_OVERAL;
		}

		if ($this->year > 0)
		{
			list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE club_id = ? AND  start_time >= ? AND start_time < ?', $this->id, $this->from, $this->to);
		}
		else
		{
			list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE club_id = ?', $this->id);
		}
		
		if ($this->year > 0)
		{
			$this->_title = $this->name . ': ' . $this->views[$this->view] . ' ' . $this->year;
		}
		else
		{
			$this->_title = $this->name . ': ' . $this->views[$this->view];
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$min_year = $max_year = $this->year;
		$query = new DbQuery('SELECT MIN(start_time), MAX(start_time) FROM games WHERE club_id = ?', $this->id);
		if ($row = $query->next())
		{
			list ($min_time, $max_time) = $row;
			if ($min_time > 0 && $max_time > 0)
			{
				$min_year = date('Y', $min_time);
				$max_year = date('Y', $max_time);
			}
		}
		
		echo '<form name="filter" method="get"><input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<select name="year" onchange="document.filter.submit()">';
		show_option(0, $this->year, get_label('All time'));
		for ($i = $min_year; $i <= $max_year; ++$i)
		{
			show_option($i, $this->year, $i);
		}
		echo '</select></td><td align="right"><select name="view" onchange="document.filter.submit()">';
		for ($i = 0; $i < count($this->views); ++$i)
		{
			show_option($i, $this->view, $this->views[$i]);
		}
		echo '</select></td></tr></table>';
		
		switch ($this->view)
		{
		case VIEW_OVERAL:
			$this->show_overal();
			break;
		case VIEW_NOM_WINNERS:
			$this->show_nom_winners();
			break;
		}
		echo '</form>';
	}
	
	function show_overal()
	{
		$playing_count = 0;
		$civils_win_count = 0;
		$mafia_win_count = 0;
		if ($this->year > 0)
		{
			$query = new DbQuery('SELECT result, count(*) FROM games WHERE club_id = ? AND start_time >= ? AND start_time < ? GROUP BY result', $this->id, $this->from, $this->to);
		}
		else
		{
			$query = new DbQuery('SELECT result, count(*) FROM games WHERE club_id = ? GROUP BY result', $this->id);
		}
		while ($row = $query->next())
		{
			switch ($row[0])
			{
				case 0:
					$playing_count = $row[1];
					break;
				case 1:
					$civils_win_count = $row[1];
					break;
				case 2:
					$mafia_win_count = $row[1];
					break;
			}
		}
		$games_count = $civils_win_count + $mafia_win_count + $playing_count;
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="2"><a href="club_games.php?bck=1&id=' . $this->id . '"><b>' . get_label('Stats') . '</b></a></td></tr>';
		echo '<tr><td width="200">'.get_label('Games played').':</td><td>' . ($civils_win_count + $mafia_win_count) . '</td></tr>';
		if ($civils_win_count + $mafia_win_count > 0)
		{
			echo '<tr><td>'.get_label('Mafia won in').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			echo '<tr><td>'.get_label('Civilians won in').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
		}
		if ($playing_count > 0)
		{
			echo '<tr><td>'.get_label('Still playing').'</td><td>' . $playing_count . '</td></tr>';
		}
		
		if ($civils_win_count + $mafia_win_count > 0)
		{
			if ($this->year > 0)
			{
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p, games g WHERE p.game_id = g.id AND g.club_id = ? AND g.start_time >= ? AND g.start_time < ?', $this->id, $this->from, $this->to);
			}
			else
			{
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p, games g WHERE p.game_id = g.id AND g.club_id = ?', $this->id);
			}
			echo '<tr><td>'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
			
			if ($this->year > 0)
			{
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT moderator_id) FROM games WHERE club_id = ? AND start_time >= ? AND start_time < ?', $this->id, $this->from, $this->to);
			}
			else
			{
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT moderator_id) FROM games WHERE club_id = ?', $this->id);
			}
			echo '<tr><td>'.get_label('People moderated').':</td><td>' . $counter . '</td></tr>';
			
			if ($this->year > 0)
			{
				list ($a_game, $s_game, $l_game) = Db::record(
					get_label('game'),
					'SELECT AVG(end_time - start_time), MIN(end_time - start_time), MAX(end_time - start_time) ' .
						'FROM games WHERE result > 0 AND result < 3 AND club_id = ? AND start_time >= ? AND start_time < ?', 
					$this->id, $this->from, $this->to);
			}
			else
			{
				list ($a_game, $s_game, $l_game) = Db::record(
					get_label('game'),
					'SELECT AVG(end_time - start_time), MIN(end_time - start_time), MAX(end_time - start_time) ' .
						'FROM games WHERE result > 0 AND result < 3 AND club_id = ?', 
					$this->id);
			}
			echo '<tr><td>'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
			echo '<tr><td>'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
			echo '<tr><td>'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
		}
		echo '</table>';
		
		if ($games_count > 0)
		{
			if ($this->year > 0)
			{
				$query = new DbQuery(
					'SELECT p.kill_type, p.role, count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.club_id = ? AND g.start_time >= ? AND g.start_time < ? AND g.result IN(1, 2) GROUP BY p.kill_type, p.role',
					$this->id, $this->from, $this->to);
			}
			else
			{
				$query = new DbQuery(
					'SELECT p.kill_type, p.role, count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.club_id = ? AND g.result IN(1, 2) GROUP BY p.kill_type, p.role',
					$this->id);
			}
			$killed = array();
			while ($row = $query->next())
			{
				list ($kill_type, $role, $count) = $row;
				if (!isset($killed[$kill_type]))
				{
					$killed[$kill_type] = array();
				}
				$killed[$kill_type][$role] = $count;
			}
			
			foreach ($killed as $kill_type => $roles)
			{
				echo '<table class="bordered light" width="100%">';
				echo '<tr class="darker"><td colspan="2"><b>';
				switch ($kill_type)
				{
				case 0:
					echo get_label('Survived');
					break;
				case 1:
					echo get_label('Killed in day');
					break;
				case 2:
					echo get_label('Killed in night');
					break;
				case 3:
					echo get_label('Killed by warnings');
					break;
				case 4:
					echo get_label('Commited suicide');
					break;
				case 5:
					echo get_label('Killed by moderator');
					break;
				}
				echo ':</b></td></tr>';
				foreach ($roles as $role => $count)
				{
					echo '<tr><td width="200">';
					switch ($role)
					{
					case PLAYER_ROLE_CIVILIAN:
						echo get_label('Civilians');
						break;
					case PLAYER_ROLE_SHERIFF:
						echo get_label('Sheriffs');
						break;
					case PLAYER_ROLE_MAFIA:
						echo get_label('Mafiosies');
						break;
					case PLAYER_ROLE_DON:
						echo get_label('Dons');
						break;
					}
					echo ':</td><td>' . $count . '</td></tr>';
				}
				echo '</table>';
			}
		}
	}
	
	function show_nom_winners()
	{
		$noms = array(
			array(get_label('Points'), 'IFNULL(SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = ' . $this->scoring_id . ' AND (o.flag & p.flags) <> 0)), 0) / 100', 'count(*)', 0),
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
			array(get_label('Survived'), 'SUM(IF(p.kill_type = 0, 1, 0))', 'count(*)', 1),
			array(get_label('Killed in day'), 'SUM(IF(p.kill_type = 1, 1, 0))', 'count(*)', 1),
			array(get_label('Killed in night'), 'SUM(IF(p.kill_type = 2, 1, 0))', 'count(*)', 1),
			array(get_label('Killed first night'), 'SUM(IF(p.kill_type = 2 AND p.kill_round = 0, 1, 0))', 'count(*)', 1),
			array(get_label('Warnings'), 'SUM(p.warns)', 'count(*)', 0),
			array(get_label('Arranged'), 'SUM(IF(p.was_arranged >= 0, 1, 0))', 'count(*)', 1),
			array(get_label('Arranged first night'), 'SUM(IF(p.was_arranged = 0, 1, 0))', 'count(*)', 1),
			array(get_label('Checked by don'), 'SUM(IF(p.checked_by_don >= 0, 1, 0))', 'count(*)', 1),
			array(get_label('Checked by sheriff'), 'SUM(IF(p.checked_by_sheriff >= 0, 1, 0))', 'count(*)', 1),
		);
		
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
		
		if (isset($_REQUEST['min']))
		{
			$min_games = $_REQUEST['min'];
		}
		else
		{
			$min_games = round($this->games_count / 100) * 10;
			$min_games -= $min_games % 10;
		}
	
		$roles_condition = get_roles_condition($roles);
		if ($this->year > 0)
		{
			$query = new DbQuery(
				'SELECT p.user_id, u.name, u.flags, count(*) as cnt, (' . $noms[$nom][1] . ') as abs, (' . $noms[$nom][1] . ') / (' . $noms[$nom][2] . ') as val' .
					' FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON u.id = p.user_id' .
					' WHERE g.club_id = ? AND g.start_time >= ? AND g.start_time < ?',
				$this->id, $this->from, $this->to, $roles_condition);
		}
		else
		{
			$query = new DbQuery(
				'SELECT p.user_id, u.name, u.flags, count(*) as cnt, (' . $noms[$nom][1] . ') as abs, (' . $noms[$nom][1] . ') / (' . $noms[$nom][2] . ') as val' .
					' FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON u.id = p.user_id' .
					' WHERE g.club_id = ?',
				$this->id);
		}
		$query->add(' GROUP BY p.user_id HAVING cnt > ?', $min_games);
		
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
		
		echo '<input type="hidden" name="sort" id="sort" value="' . $sort . '">';
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">';
		show_roles_select($roles, 'filter');
		echo '</td>';
		echo '<td width="100" align="center">&gt; <select name="min" onchange="document.filter.submit()">';
		$max_option = round($this->games_count / 20) * 10;
		for ($i = 0; $i <= $max_option; $i += 10)
		{
			show_option($i, $min_games, get_label('[0] games', $i));
		}
		echo '</td>';
		
		echo '<td width="200" colspan="2" align="center"><select name="nom" onchange="document.filter.submit()">';
		for ($i = 0; $i < count($noms); ++$i)
		{
			show_option($i, $nom, $noms[$i][0]);
		}
		echo '</select></td></tr>';
		
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">' . get_label('Player') . '</td>';
		echo '<td width="100" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="100" align="center">';
		if ($sort & 2)
		{
			if ($sort & 1)
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
		if (($sort & 2) == 0)
		{
			if ($sort & 1)
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
		if ($noms[$nom][3])
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
			list ($id, $name, $flags, $games_played, $abs, $val) = $row;

			echo '<tr class="light"><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $flags, ICONS_DIR, 50, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
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
}

$page = new Page();
$page->run(get_label('Club'), PERM_ALL);

?>

<script>
function sortBy(s)
{
	if (s != $('#sort').val())
	{
		$('#sort').val(s);
		//console.log($('#sort').val());
		document.filter.submit();
	}
}
</script>