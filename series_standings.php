<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

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
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*) FROM series_places WHERE series_id = ?', $this->id);
		
		$parent_series = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, l.id, l.name, l.flags, ss.stars, g.gaining'.
			' FROM series_series ss'.
			' JOIN series s ON s.id = ss.parent_id'.
			' JOIN leagues l ON l.id = s.league_id'.
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version'.
			' WHERE ss.child_id = ?', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $s->league_id, $s->league_name, $s->league_flags, $s->stars, $gaining) = $row;
			$gaining = json_decode($gaining);
			$s->points = get_gaining_points($gaining, $s->stars, $count, true);
			$parent_series[] = $s;
		}
		$parent_series_pic = new Picture(SERIES_PICTURE, new Picture(LEAGUE_PICTURE));
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_pages_navigation(PAGE_SIZE, $count);
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
		echo '<td width="80">'.get_label('Games played (won)').'</td>';
		echo '<td width="80">'.get_label('Win rate').'</td>';
		foreach ($parent_series as $s)
		{
			echo '<td width="36" align="center">';
			$parent_series_pic->set($s->id, $s->name, $s->flags)->set($s->league_id, $s->league_name, $s->league_flags);
			$parent_series_pic->show(ICONS_DIR, true, 32);
			echo '</td>';
		}
		echo '</tr>';

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, p.place, p.score, p.tournaments, p.games, p.wins, c.id, c.name, c.flags'.
			' FROM series_places p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id'.
			' WHERE p.series_id = ?'.
			' ORDER BY p.place'.
			' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		$player_pic = new Picture(USER_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		while ($row = $query->next())
		{
			list ($player_id, $player_name, $player_flags, $place, $points, $tournaments, $games, $wins, $club_id, $club_name, $club_flags) = $row;
			if ($player_id == $this->user_id)
			{
				echo '<tr align="center" class="darker">';
				$highlight = 'darker';
			}
			else
			{
				echo '<tr align="center">';
				$highlight = 'dark';
			}
			echo '<td class="' . $highlight . '">' . $place . '</td>';
			
			echo '<td width="50"><a href="series_player.php?id=' . $this->id . '&user_id=' . $player_id . '&bck=1">';
			$player_pic->set($player_id, $player_name, $player_flags);
			$player_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td align="left"><a href="series_player.php?id=' . $this->id . '&user_id=' . $player_id . '&bck=1">' . $player_name . '</a></td>';
			echo '<td width="50" align="center">';
			if (isset($club_id))
			{
				$club_pic->set($club_id, $club_name, $club_flags);
				$club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			
			echo '<td class="' . $highlight . '">' . format_score($points) . '</td>';
			echo '<td>' . $tournaments . '</td>';
			echo '<td>' . ($tournaments > 0 ? format_score($points / $tournaments) : '') . '</td>';
			echo '<td>' . $games . ' (' . $wins . ')</td>';
			echo '<td>' . ($games > 0 ? format_score($wins / $games) : '') . '</td>';
			foreach ($parent_series as $s)
			{
				if ($s->stars > 0)
				{
					echo '<td align="center">' . $s->points[$place-1] . '</td>';
				}
				else
				{
					echo '<td align="center"></td>';
				}
			}
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
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
