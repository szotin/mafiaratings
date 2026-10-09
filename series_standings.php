<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/gaining.php';
require_once 'include/datetime.php';

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
	
	// The standings of a series with no dates: the same tournament happening again and again, so
	// what tells its story is not one table of points but who was there each time and how they
	// did. Rows are the players in ranking order, columns are its tournaments oldest first, and a
	// cell is the place that player took that year.
	//
	// The word "winner" does not appear: nobody wins a tournament that never ends.
	private function show_history()
	{
		global $_page, $_lang;

		list ($count) = Db::record(get_label('series'), 'SELECT count(*) FROM series_places WHERE series_id = ? AND cut_stages = 0', $this->id);

		// A column per tournament rather than per year: nothing stops the same tournament from
		// happening twice in one year, and two of them sharing a column would have to hide one.
		$tournaments = array();
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time FROM series_tournaments st' .
			' JOIN tournaments t ON t.id = st.tournament_id' .
			' WHERE st.series_id = ? ORDER BY t.start_time, t.id', $this->id);
		while ($row = $query->next())
		{
			$t = new stdClass();
			list($t->id, $t->name, $t->flags, $t->start_time) = $row;
			$t->id = (int)$t->id;
			$t->year = get_datetime($t->start_time, $this->timezone)->format('Y');
			$tournaments[$t->id] = $t;
		}

		// The place of every player in every one of those tournaments, as the tournament itself
		// counted it. An exhibition player of the series is left out of the history as well.
		$places = array();
		$query = new DbQuery(
			'SELECT tp.tournament_id, tp.user_id, tp.place FROM tournament_places tp' .
			' JOIN series_tournaments st ON st.tournament_id = tp.tournament_id AND st.series_id = ?' .
			' LEFT OUTER JOIN series_regs sr ON sr.series_id = st.series_id AND sr.user_id = tp.user_id' .
			' WHERE (sr.flags IS NULL OR (sr.flags & ' . USER_FLAG_EXHIBITION_PLAYER . ') = 0)', $this->id);
		while ($row = $query->next())
		{
			list($tournament_id, $user_id, $place) = $row;
			$places[(int)$user_id][(int)$tournament_id] = (int)$place;
		}

		// What the table is made of goes on the left, the player search on the right, and the empty
		// cell between them is what holds the two groups apart. The page navigation is a block of
		// its own and gets its own line below.
		echo '<p><table class="transp" width="100%"><tr><td nowrap>';
		echo '<a href="javascript:showGaining()">' . get_label('Scoring system') . '</a>';
		echo '</td><td width="100%"></td>';
		echo '<td align="right" width="200" nowrap>';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'series=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		show_pages_navigation(PAGE_SIZE, $count);

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td width="40"></td>';
		echo '<td colspan="3" align="left">' . get_label('Player') . '</td>';
		echo '<td width="70">' . get_label('Points') . '</td>';
		echo '<td width="70">' . get_label('Tournaments played') . '</td>';
		echo '<td width="70">' . get_label('Best place') . '</td>';
		echo '<td width="70">' . get_label('Average place') . '</td>';
		foreach ($tournaments as $t)
		{
			echo '<td width="44" align="center" title="' . $t->name . '">' . $t->year . '</td>';
		}
		echo '</tr>';

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, p.place, p.score, c.id, c.name, c.flags' .
			' FROM series_places p' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' WHERE p.series_id = ? AND p.cut_stages = 0' .
			' ORDER BY p.place' .
			' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		$player_pic = new Picture(USER_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		while ($row = $query->next())
		{
			list ($player_id, $player_name, $player_flags, $place, $points, $club_id, $club_name, $club_flags) = $row;
			$player_id = (int)$player_id;

			$played = 0;
			$best = 0;
			$sum = 0;
			foreach ($tournaments as $t)
			{
				if (isset($places[$player_id][$t->id]))
				{
					$p = $places[$player_id][$t->id];
					++$played;
					$sum += $p;
					if ($best == 0 || $p < $best)
					{
						$best = $p;
					}
				}
			}

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

			echo '<td class="' . $highlight . '">' . format_gain($points) . '</td>';
			echo '<td>' . $played . '</td>';
			echo '<td>' . ($best > 0 ? $best : '') . '</td>';
			echo '<td>' . ($played > 0 ? format_gain($sum / $played) : '') . '</td>';
			foreach ($tournaments as $t)
			{
				echo '<td align="center">';
				if (isset($places[$player_id][$t->id]))
				{
					echo '<a href="tournament.php?bck=1&id=' . $t->id . '">' . $places[$player_id][$t->id] . '</a>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}

	protected function show_body()
	{
		global $_page, $_lang;

		// A series with no dates and no ladder is the same tournament happening again, and its
		// standings read as a history rather than as a table of points. Give such a series a
		// ladder and it goes back to the ordinary table, stage column and cut-off switch and all:
		// the three properties of a series are independent, and having no dates says nothing
		// about having stages.
		if (series_is_infinite($this->start_time) && $this->stages <= 1)
		{
			$this->show_history();
			return;
		}

		// A series with a ladder has one ranking per stage - see cut_stages in series_places -
		// and which of them is on screen is a choice. A series with no ladder has nothing to
		// choose and never shows the switch.
		$cut_stages = isset($_REQUEST['cut']) ? (int)$_REQUEST['cut'] : 0;
		if ($cut_stages < 0 || $cut_stages >= $this->stages)
		{
			$cut_stages = 0;
		}
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*) FROM series_places WHERE series_id = ? AND cut_stages = ?', $this->id, $cut_stages);
		
		$parent_series = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, ' . series_owner_fields() . ', ss.stars, g.gaining'.
			' FROM series_series ss'.
			' JOIN series s ON s.id = ss.parent_id'.
			series_owner_join().
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version'.
			' WHERE ss.child_id = ?', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $s->stars, $gaining) = $row;
			$s->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$s->gaining = json_decode($gaining);
			$parent_series[] = $s;
		}
		$parent_series_pic = new SeriesPicture();
		
		// What the table is made of goes on the left: the system the points come from, and which of
		// the per-stage rankings is on screen. The player search keeps the right, and the empty cell
		// between them is what holds the two groups apart. The page navigation is a block of its own
		// and gets its own line below.
		echo '<p><table class="transp" width="100%"><tr><td nowrap>';
		echo '<a href="javascript:showGaining()">' . get_label('Scoring system') . '</a>';
		if ($this->stages > 1)
		{
			echo '&emsp;&emsp;' . get_label('Standings') . ': <select onchange="goTo({cut: this.value, page: undefined})">';
			for ($c = 0; $c < $this->stages; ++$c)
			{
				show_option($c, $cut_stages, get_series_cut_name($this->stages, $c));
			}
			echo '</select>';
		}
		echo '</td><td width="100%"></td>';
		echo '<td align="right" width="200" nowrap>';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'series=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td width="40"></td>';
		echo '<td colspan="3" align="left">'.get_label('Player').'</td>';
		if ($this->stages > 1)
		{
			echo '<td width="80">'.get_label('Stage').'</td>';
		}
		echo '<td width="80">'.get_label('Points').'</td>';
		echo '<td width="80">'.get_label('Cut off').'</td>';
		echo '<td width="80">'.get_label('Total cut off').'</td>';
		echo '<td width="80">'.get_label('Tournaments played').'</td>';
		echo '<td width="80">'.get_label('Points per tournament').'</td>';
		// echo '<td width="80">'.get_label('Games played (won)').'</td>';
		// echo '<td width="80">'.get_label('Win rate').'</td>';
		foreach ($parent_series as $s)
		{
			echo '<td width="36" align="center">';
			$parent_series_pic->set($s->id, $s->name, $s->flags, $s->owner)->show(ICONS_DIR, true, 32);
			echo '</td>';
		}
		echo '</tr>';

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, p.place, p.score, p.tournaments, p.games, p.wins, p.total_cut_off, p.cut_off, p.stage, c.id, c.name, c.flags'.
			' FROM series_places p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id'.
			' WHERE p.series_id = ? AND p.cut_stages = ?'.
			' ORDER BY p.place'.
			' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id, $cut_stages);
		$player_pic = new Picture(USER_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		while ($row = $query->next())
		{
			list ($player_id, $player_name, $player_flags, $place, $points, $tournaments, $games, $wins, $total_cut_off, $cut_off, $stage, $club_id, $club_name, $club_flags) = $row;
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
			
			if ($this->stages > 1)
			{
				// The base stage is left blank. Everybody starts there, so naming it says nothing -
				// what the column is for is the players who went further than that. Highlighted
				// like the points next to it: the two of them together are the place.
				echo '<td class="' . $highlight . '">';
				if ($stage != SERIES_STAGE_BASE)
				{
					echo get_series_stage_name($this->type, $this->stages, $stage);
				}
				echo '</td>';
			}
			echo '<td class="' . $highlight . '">' . format_gain($points) . '</td>';
			echo '<td>' . format_gain($cut_off) . '</td>';
			echo '<td>' . format_gain($total_cut_off) . '</td>';
			echo '<td>' . $tournaments . '</td>';
			echo '<td>' . ($tournaments > 0 ? format_gain($points / $tournaments) : '') . '</td>';
			// echo '<td>' . $games . ' (' . $wins . ')</td>';
			// echo '<td>' . ($games > 0 ? format_gain($wins / $games) : '') . '</td>';
			foreach ($parent_series as $s)
			{
				if ($s->stars > 0)
				{
					echo '<td align="center">' . format_gain(get_gaining_points($s->id, $s->gaining, $s->stars, $place, $points, $count, 0, 0, 0, 0, true)) . '</td>';
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
