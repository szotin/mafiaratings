<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

function compare_players($player1, $player2)
{
	if ($player1->points < $player2->points)
	{
		return 1;
	}
	if ($player1->points > $player2->points)
	{
		return -1;
	}
	if ($player1->tournaments > $player2->tournaments)
	{
		return 1;
	}
	if ($player1->tournaments < $player2->tournaments)
	{
		return -1;
	}
	return $player1->id - $player2->id;
}

class Page extends SeriesPageBase
{
	protected function prepare()
	{
		global $_page, $_lang;
		
		parent::prepare();
		
		$this->user_id = 0;
		$this->user_name = '';
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery(
				'SELECT nu.name, u.club_id, u.city_id, c.country_id'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN cities c ON c.id = u.city_id'.
				' WHERE u.id = ?', $this->user_id);
			if ($row = $query->next())
			{
				list($this->user_name, $this->user_club_id, $this->user_city_id, $this->user_country_id) = $row;
				$this->_title .= ' ' . get_label('Following [0].', $this->user_name);
			}
			else
			{
				$this->errorMessage(get_label('Player not found.'));
			}
		}
	}
	
	protected function show_body()
	{
		global $_page, $_lang;
		
		if (isset($_REQUEST['gaining_id']))
		{
			$gaining_id = (int)$_REQUEST['gaining_id'];
			if (isset($_REQUEST['gaining_version']))
			{
				list($gaining) = Db::record(get_label('gaining system'), 'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?', $gaining_id, (int)$_REQUEST['gaining_version']);
			}
			else
			{
				list($gaining) = Db::record(get_label('gaining system'), 'SELECT v.gaining FROM gainings g JOIN gaining_versions v ON v.gaining_id = g.id AND v.version = g.version WHERE g.id = ?', $gaining_id);
			}
		}
		else
		{
			list($gaining) = Db::record(get_label('gaining system'), 'SELECT v.gaining FROM series s JOIN gaining_versions v ON v.gaining_id = s.gaining_id AND v.version = s.gaining_version WHERE s.id = ?', $this->id);
		}
		$gaining = json_decode($gaining);
		
		$tournaments = array();
		$query = new DbQuery('SELECT s.tournament_id, s.stars, count(t.user_id) FROM series_tournaments s JOIN tournament_places t ON t.tournament_id = s.tournament_id WHERE s.series_id = ? GROUP BY s.tournament_id', $this->id);
		while ($row = $query->next())
		{
			list($tournament_id, $stars, $players) = $row;
			$tournaments[$tournament_id] = get_gaining_points($gaining, $stars, $players);
		}
		
//		print_json($gaining);
		$max_tournaments = isset($gaining->maxTournaments) ? $gaining->maxTournaments : 0;
		$players = array();
		$query = new DbQuery(
			'SELECT t.tournament_id, u.id, nu.name, u.flags, p.place, c.id, c.name, c.flags'.
			' FROM tournament_places p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN series_tournaments t ON t.tournament_id = p.tournament_id'.
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id'.
			' WHERE t.series_id = ? AND (t.flags & ' . SERIES_TOURNAMENT_FLAG_NOT_PAYED . ') = 0', $this->id);
		while ($row = $query->next())
		{
			list($tournament_id, $player_id, $player_name, $player_flags, $place, $club_id, $club_name, $club_flags) = $row;
			if (!isset($players[$player_id]))
			{
				$player = new stdClass();
				$player->id = (int)$player_id;
				$player->name = $player_name;
				$player->flags = (int)$player_flags;
				if (!is_null($club_id))
				{
					$player->club_id = (int)$club_id;
					$player->club_name = $club_name;
					$player->club_flags = (int)$club_flags;
				}
				$player->tournaments = 0;
				if ($max_tournaments > 0)
				{
					$player->p = array();
				}
				else
				{
					$player->points = 0;
				}
				$players[$player_id] = $player;
			}
			else
			{
				$player = $players[$player_id];
			}
			
			$points = $tournaments[$tournament_id][$place-1];
			if ($max_tournaments > 0)
			{
				if (count($player->p) >= $max_tournaments)
				{
					$min_index = 0;
					for ($i = 1; $i < $max_tournaments; ++$i)
					{
						if ($player->p[$i] < $player->p[$min_index])
						{
							$min_index = $i;
						}
					}
					if ($player->p[$min_index] <= $points)
					{
						$player->p[$min_index] = $points;
					}
				}
				else
				{
					$player->p[] = $points;
				}
			}
			else
			{
				$player->points += $tournaments[$tournament_id][$place-1];;
			}
			++$player->tournaments;
		}
		
		if ($max_tournaments > 0)
		{
			foreach ($players as $player)
			{
				$player->points = 0;
				foreach ($player->p as $p)
				{
					$player->points += $p;
				}
				unset($player->p);
			}
		}
		
		
		usort($players, "compare_players");
		if ($this->user_id > 0)
		{
			$_page = get_user_page($players, $this->user_id, PAGE_SIZE);
			if ($_page < 0)
			{
				$_page = 0;
				$this->no_user_error();
			}
		}
		
		$total_players_count = $players_count = count($players);
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_pages_navigation(PAGE_SIZE, $total_players_count);
		echo '</td>';
		echo '<td align="right"><a href="javascript:showGaining()">' . get_label('Scoring system') . '</a></td>';
		echo '<td align="right" width="200">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'series=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td width="40"></td>';
		echo '<td colspan="3" align="left">'.get_label('Player').'</td>';
		echo '<td width="80">'.get_label('Points').'</td>';
		echo '<td width="80">'.get_label('Tournaments played').'</td>';
		echo '<td width="80">'.get_label('Points per tournament').'</td>';
		echo '</tr>';
		
		$place = $page_start = $_page * PAGE_SIZE;
		if ($players_count > $place + PAGE_SIZE)
		{
			$players_count = $place + PAGE_SIZE;
		}
		$player_pic = new Picture(USER_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$ids = array_keys($players);
		for ($number = $place; $number < $players_count; ++$number)
		{
			$player_id = $ids[$number];
			$player = $players[$player_id];
			if ($player->id == $this->user_id)
			{
				echo '<tr align="center" class="darker">';
				$highlight = 'darker';
			}
			else
			{
				echo '<tr align="center">';
				$highlight = 'dark';
			}
			echo '<td class="' . $highlight . '">' . ++$place . '</td>';
			
			echo '<td width="50"><a href="series_player.php?id=' . $this->id . '&user_id=' . $player->id . '&bck=1">';
			$player_pic->set($player->id, $player->name, $player->flags);
			$player_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td align="left"><a href="series_player.php?id=' . $this->id . '&user_id=' . $player->id . '&bck=1">' . $player->name . '</a></td>';
			echo '<td width="50" align="center">';
			if (isset($player->club_id))
			{
				$club_pic->set($player->club_id, $player->club_name, $player->club_flags);
				$club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			
			echo '<td class="' . $highlight . '">' . format_score($player->points) . '</td>';
			echo '<td>' . $player->tournaments . '</td>';
			echo '<td>' . format_score($player->points / $player->tournaments) . '</td>';
				
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $total_players_count);
	}
	
	private function no_user_error()
	{
		$this->errorMessage(get_label('[0] did not play in [1].', $this->user_name, $this->name));
	}
	
	protected function js()
	{
?>		
		function showGaining()
		{
			dlg.infoForm('form/gaining_show.php?id=<?php echo $this->gaining_id; ?>&version=<?php echo $this->gaining_version; ?>');
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>
