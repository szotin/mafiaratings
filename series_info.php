<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/event.php';

define('TOURNAMENT_COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('TOURNAMENT_ROW_COUNT', 6);
define('TOURNAMENT_COLUMN_WIDTH', (100 / TOURNAMENT_COLUMN_COUNT));
define('COMMENTS_WIDTH', 300);

class Page extends SeriesPageBase
{
	private function show_details()
	{
		global $_page, $_lang, $_profile;
		
		$now = time();
		$row_count = 0;
		$column_count = 0;
		$page_size = TOURNAMENT_ROW_COUNT * TOURNAMENT_COLUMN_COUNT;
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, t.duration, nct.name, ncr.name, ct.timezone, a.id, a.flags, a.address, a.map_url, a.name, c.id, c.name, c.flags' .
			' FROM series_tournaments st' .
			' JOIN tournaments t ON t.id = st.tournament_id' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN countries cr ON ct.country_id = cr.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = t.club_id' .
			' WHERE st.series_id = ?' .
			' ORDER BY t.start_time DESC, t.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size,
			$this->id);

		$club_pic = new Picture(CLUB_PICTURE);
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		while ($row = $query->next())
		{
			list (
				$tournament_id, $tournament_name, $tournament_flags, $tournament_start_time, $tournament_duration, 
				$tournament_city_name, $tournament_country_name, $tournament_timezone, 
				$tournament_addr_id, $tournament_addr_flags, $tournament_addr, $tournament_addr_url, $tournament_addr_name,
				$tournament_club_id, $tournament_club_name, $tournament_club_flags) = $row;
			$past = ($tournament_start_time + $tournament_duration <= $now);
			if ($past)
			{
				$url = 'tournament.php';
				$dark_class = ' class="dark"';
				$light_class = '';
			}
			else
			{
				$url = 'tournament.php';
				$dark_class = ' class="darker"';
				$light_class = ' class="dark"';
			}
			
			if ($tournament_name == $tournament_addr_name)
			{
				$tournament_name = $tournament_addr;
			}
			if ($column_count == 0)
			{
				if ($row_count == 0)
				{
					echo '<table class="bordered light" width="100%"><tr class="darker"><td colspan="' . TOURNAMENT_COLUMN_COUNT . '"><b>' . get_label('Tournaments') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . TOURNAMENT_COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			echo '<tr' . $dark_class . '><td align="center" style="height: 40px;" colspan="2"><b>' . $tournament_name . '</b></td></tr>';


			echo '<tr' . $light_class . ' style="height: 120px;"><td align="center" colspan="2"><a href="' . $url . '?bck=1&id=' . $tournament_id . '">';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, false);
			echo '</a><p>' . format_date_period($tournament_start_time, $tournament_duration, $tournament_timezone) . '</p></td></tr>';
			
			echo '<tr' . $dark_class . ' style="height: 40px;"><td align="center">' . $tournament_club_name . '</td><td width="34">';
			$club_pic->set($tournament_club_id, $tournament_club_name, $tournament_club_flags);
			$club_pic->show(ICONS_DIR, false, 30);
			echo '</td></tr></table>';
			
			echo '</td>';
			
			++$row_count;
			++$column_count;
			if ($column_count >= TOURNAMENT_COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($row_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (TOURNAMENT_COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}

	// The strength of the field, the same four numbers a tournament shows about itself.
	//
	// They are divided by the number of players the series counts, which is how many stand in its
	// full table - and that is exactly the count a series above divides them by when it counts
	// this one as a competition of its own, so the page says what the number does.
	private function show_properties()
	{
		list ($players_count) = Db::record(get_label('sеriеs'),
			'SELECT count(*) FROM series_places WHERE series_id = ? AND cut_stages = 0', $this->id);
		if ($players_count <= 0)
		{
			return;
		}

		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="2"><b>' . get_label('Properties') . '</b></td></tr>';
		echo '<tr><td width="200">' . get_label('Average rating') . ':</td><td>' .
			format_rating(1000 + $this->rating_sum / $players_count) . '</td></tr>';
		echo '<tr><td width="200">' . get_label('Average rating of top 20 players') . ':</td><td>' .
			format_rating(1000 + $this->rating_sum_20 / ($players_count > 20 ? 20 : $players_count)) . '</td></tr>';
		echo '<tr><td>' . get_label('Total traveling distance') . ':</td><td>' .
			get_label('[0] km', format_rating(convert_distance($this->traveling_distance, GEO_MILES, GEO_KILOMETERS))) . '</td></tr>';
		echo '<tr><td>' . get_label('Guest coefficient') . ':</td><td>' . format_rating($this->guest_coeff) . '</td></tr>';
		echo '</table></p>';
	}

	protected function show_body()
	{
		global $_profile;
		
		echo '<table width="100%"><tr valign="top"><td>';
		$this->show_details();
		$this->show_properties();
		echo '</td><td id="comments" width="' . COMMENTS_WIDTH . '"></td></tr></table>';
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
