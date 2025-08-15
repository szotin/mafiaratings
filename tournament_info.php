<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/utilities.php';

define('ROUND_COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('ROUND_ROW_COUNT', 6);
define('ROUND_COLUMN_WIDTH', (100 / ROUND_COLUMN_COUNT));
define('USER_COLUMN_COUNT', 6);
define('USER_COLUMN_WIDTH', (100 / USER_COLUMN_COUNT));
define('COMMENTS_WIDTH', 300);

class Page extends TournamentPageBase
{
	private function show_details()
	{
		global $_page, $_lang, $_profile;
		
		$now = time();
		
		list($games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE tournament_id = ? AND (flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING, $this->id);
		
		$players = array();
		$referees = array();
		$applications = array();
		if ($games_count > 0)
		{
			$query = new DbQuery(
				'SELECT DISTINCT u.id, nu.name, u.flags, c.id, c.name, c.flags, tu.flags, cu.flags, nc.name, ct.lat, ct.lon, tu.rating' . 
				' FROM players p' . 
				' JOIN users u ON u.id = p.user_id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN games g ON p.game_id = g.id' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' . 
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = g.tournament_id' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = g.club_id' .
				' JOIN cities ct ON ct.id = tu.city_id' .
				' JOIN names nc ON nc.id = ct.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' WHERE g.tournament_id = ? ORDER BY nu.name', $this->id);
			while ($row = $query->next())
			{
				$players[] = $row;
			}
			
			$query = new DbQuery(
				'SELECT DISTINCT u.id, nu.name, u.flags, c.id, c.name, c.flags, tu.flags, cu.flags' . 
				' FROM games g' . 
				' JOIN users u ON u.id = g.moderator_id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' . 
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = g.tournament_id' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = g.club_id' .
				' WHERE g.tournament_id = ? ORDER BY nu.name', $this->id);
			while ($row = $query->next())
			{
				$referees[] = $row;
			}
		}
		else
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, c.id, c.name, c.flags, tu.flags, cu.flags, nc.name, ct.lat, ct.lon, tu.rating' . 
				' FROM tournament_users tu' . 
				' JOIN users u ON tu.user_id = u.id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' . 
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
				' JOIN cities ct ON ct.id = tu.city_id' .
				' JOIN names nc ON nc.id = ct.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' WHERE tu.tournament_id = ? ORDER BY nu.name', $this->club_id, $this->id);
			while ($row = $query->next())
			{
				$user_tournament_flags = (int)$row[6];
				if ($user_tournament_flags & USER_TOURNAMENT_FLAG_NOT_ACCEPTED)
				{
					$applications[] = $row;
				}
				else
				{
					if ($user_tournament_flags & USER_PERM_REFEREE)
					{
						$referees[] = $row;
					}
					
					if ($user_tournament_flags & USER_PERM_PLAYER)
					{
						$players[] = $row;
					}
				}
			}
		}
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		$club_pic = new Picture(CLUB_PICTURE);
		
		$row_count = 0;
		$column_count = 0;
		foreach ($players as $row)
		{
			list ($user_id, $user_name, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $tournament_user_flags, $club_user_flags, $city, $lat, $lon, $rating) = $row;
			if ($column_count == 0)
			{
				if ($row_count == 0)
				{
					echo '<table class="bordered light" width="100%"><tr class="darker"><td colspan="' . USER_COLUMN_COUNT . '"><b>' . get_label('Participants') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
				
			}
			
			echo '<td width="' . USER_COLUMN_WIDTH . '%" align="center" valign="top">';
			echo '<table class="transp" width="100%"><tr class="dark"><td width="36">';
			$club_pic->set($user_club_id, $user_club_name, $user_club_flags);
			$club_pic->show(ICONS_DIR, false, 24);
			echo '</td><td><b>' . $user_name . '</b></td></tr>';
			echo '<tr><td colspan="2" align="center">';
			$tournament_user_pic->
				set($user_id, $user_name, $tournament_user_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_user_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			if ($games_count > 0)
			{
				$tournament_user_pic->show(ICONS_DIR, true, 64);
			}
			else
			{
				echo '<a href="user_info.php?bck=1&id=' . $user_id . '">';
				$tournament_user_pic->show(ICONS_DIR, false, 64);
				echo '</a>';
			}
			echo '</td></tr>';
			echo '<tr class="dark"><td colspan="2" style="padding-left:6px;">';
			echo $city . '<br>';
			echo get_label('[0] km', format_rating(get_distance($this->lat, $this->lon, $lat, $lon, GEO_KILOMETERS))) . '<br>';
			echo get_label('Rating: [0]', format_rating($rating+1000)) . '</td></tr>';
			echo '</table>';
			echo '</td>';
			
			++$row_count;
			++$column_count;
			if ($column_count >= USER_COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		
		if ($row_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (USER_COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
		
		$row_count = 0;
		$column_count = 0;
		foreach ($applications as $row)
		{
			list ($user_id, $user_name, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $tournament_user_flags, $club_user_flags, $city, $lat, $lon, $rating) = $row;
			if ($column_count == 0)
			{
				if ($row_count == 0)
				{
					echo '<table class="bordered light" width="100%"><tr class="darker"><td colspan="' . USER_COLUMN_COUNT . '"><b>' . get_label('Applicants') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
				
			}
			
			echo '<td width="' . USER_COLUMN_WIDTH . '%" align="center" valign="top">';
			echo '<table class="transp" width="100%"><tr class="dark"><td width="36">';
			$club_pic->set($user_club_id, $user_club_name, $user_club_flags);
			$club_pic->show(ICONS_DIR, false, 24);
			echo '</td><td><b>' . $user_name . '</b></td></tr>';
			echo '<tr><td colspan="2" align="center">';
			$tournament_user_pic->
				set($user_id, $user_name, $tournament_user_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_user_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			if ($games_count > 0)
			{
				$tournament_user_pic->show(ICONS_DIR, true, 64);
			}
			else
			{
				echo '<a href="user_info.php?bck=1&id=' . $user_id . '">';
				$tournament_user_pic->show(ICONS_DIR, false, 64);
				echo '</a>';
			}
			echo '</td></tr>';
			echo '<tr class="dark"><td colspan="2" style="padding-left:6px;">';
			echo $city . '<br>';
			echo get_label('[0] km', format_rating(get_distance($this->lat, $this->lon, $lat, $lon, GEO_KILOMETERS))) . '<br>';
			echo get_label('Rating: [0]', format_rating($rating+1000)) . '</td></tr>';
			echo '</table>';
			echo '</td>';
			
			++$row_count;
			++$column_count;
			if ($column_count >= USER_COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		
		if ($row_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (USER_COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
		
		$row_count = 0;
		$column_count = 0;
		foreach ($referees as $row)
		{
			list ($user_id, $user_name, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $tournament_user_flags, $club_user_flags) = $row;
			if ($column_count == 0)
			{
				if ($row_count == 0)
				{
					echo '<table class="bordered light" width="100%"><tr class="darker"><td colspan="' . USER_COLUMN_COUNT . '"><b>' . get_label('Referees') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
				
			}
			
			echo '<td width="' . USER_COLUMN_WIDTH . '%" align="center" valign="top">';
			echo '<table class="transp" width="100%"><tr class="dark"><td width="36">';
			$club_pic->set($user_club_id, $user_club_name, $user_club_flags);
			$club_pic->show(ICONS_DIR, false, 24);
			echo '</td><td><b>' . $user_name . '</b></td></tr>';
			echo '<tr><td colspan="2" align="center">';
			$tournament_user_pic->
				set($user_id, $user_name, $tournament_user_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_user_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			if ($games_count > 0)
			{
				$tournament_user_pic->show(ICONS_DIR, true, 64);
			}
			else
			{
				echo '<a href="user_info.php?bck=1&id=' . $user_id . '">';
				$tournament_user_pic->show(ICONS_DIR, false, 64);
				echo '</a>';
			}
			echo '</td></tr></table>';
			echo '</td>';
			
			++$row_count;
			++$column_count;
			if ($column_count >= USER_COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		
		if ($row_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (USER_COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
		
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="2"><b>' . get_label('Properties') . '</b></td></tr>';
		echo '<tr><td width="200">'.get_label('Average rating').':</td><td>';
		if ($this->num_regs > 0)
		{
			echo format_rating(1000 + $this->rating_sum / $this->num_regs);
		}			
		echo '</td></tr>';
//		echo '<tr><td>'.get_label('Rating sum').':</td><td>' . format_rating(1000 * $this->num_regs + $this->rating_sum) . '</td></tr>';
		echo '<tr><td width="200">'.get_label('Average rating of top 20 players').':</td><td>';
		if ($this->num_regs > 0)
		{
			echo format_rating(1000 + $this->rating_sum_20 / ($this->num_regs > 20 ? 20 : $this->num_regs));
		}
		echo '</td></tr>';
//		echo '<tr><td>'.get_label('Rating sum of top 20 players').':</td><td>' . format_rating(1000 * ($this->num_regs > 20 ? 20 : $this->num_regs) + $this->rating_sum_20) . '</td></tr>';
		echo '<tr><td>'.get_label('Total traveling distance').':</td><td>' . get_label('[0] km', format_rating(convert_distance($this->traveling_distance, GEO_MILES, GEO_KILOMETERS))) . '</td></tr>';
		echo '<tr><td>'.get_label('Guest coefficient').':</td><td>' . format_rating($this->guest_coeff, 0) . '</td></tr>';
		echo '</table></p>';
		
		$page_size = ROUND_ROW_COUNT * ROUND_COLUMN_COUNT;
		list ($count) = Db::record(get_label('event'), 'SELECT count(*) FROM events WHERE tournament_id = ? AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0', $this->id);
		show_pages_navigation($page_size, $count);
		
		$row_count = 0;
		$column_count = 0;
		$query = new DbQuery(
			'SELECT e.id, e.name, e.start_time, e.duration, e.flags, nct.name, ncr.name, ct.timezone, a.id, a.flags, a.address, a.map_url, a.name FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN countries cr ON ct.country_id = cr.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0' .
			' WHERE e.tournament_id = ? AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0' .
			' ORDER BY e.start_time DESC, e.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size,
			$this->id);

		$event_pic = new Picture(EVENT_PICTURE, new Picture(ADDRESS_PICTURE));
		while ($row = $query->next())
		{
			list ($id, $name, $start_time, $duration, $flags, $city_name, $country_name, $event_timezone, $addr_id, $addr_flags, $addr, $addr_url, $addr_name) = $row;
			$past = ($start_time + $duration <= $now);
			if ($past)
			{
				$url = 'event_standings.php';
				$dark_class = ' class="dark"';
				$light_class = '';
			}
			else
			{
				$url = 'event_info.php';
				$dark_class = ' class="darker"';
				$light_class = ' class="dark"';
			}
			
			if ($name == $addr_name)
			{
				$name = $addr;
			}
			if ($column_count == 0)
			{
				if ($row_count == 0)
				{
					echo '<table class="bordered light" width="100%"><tr class="darker"><td colspan="' . ROUND_COLUMN_COUNT . '"><b>' . get_label('Rounds') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . ROUND_COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			echo '<tr' . $dark_class . '><td align="center" style="height: 32px;"><b>' . $name . '</b></td></tr>';


			echo '<tr' . $light_class . '><td align="center"><a href="' . $url . '?bck=1&id=' . $id . '">';
			$event_pic->
				set($id, $name, $flags)->
				set($addr_id, $addr, $addr_flags);
			$event_pic->show(ICONS_DIR, false);
			echo '</a><br>' . format_date($start_time, $event_timezone) . '</td></tr></table>';
			
			echo '</td>';
			
			++$row_count;
			++$column_count;
			if ($column_count >= ROUND_COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($row_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (ROUND_COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
		show_pages_navigation($page_size, $count);
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<table width="100%"><tr valign="top"><td>';
		$this->show_details();
		echo '</td><td id="comments" width="' . COMMENTS_WIDTH . '"></td></tr></table>';
	}
	
	protected function js_on_load()
	{
		global $_profile;
		
		echo 'mr.showComments("tournament", ' . $this->id . ", 5);\n";
		if (isset($_REQUEST['approve']) && $_profile != NULL && (!isset($_REQUEST['_login_']) || $_REQUEST['_login_'] == $_profile->user_id))
		{
			$league_id = (int)$_REQUEST['approve'];
			if ($league_id > 0 && is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
			{
?>
				mr.approveTournament(<?php echo $this->id; ?>, <?php echo $league_id; ?>);
<?php
			}
		}
	}
	
	protected function js()
	{
		parent::js();
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
