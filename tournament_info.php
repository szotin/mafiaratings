<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/event.php';

define('COLUMN_COUNT', 5);
define('ROW_COUNT', 6);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('COMMENTS_WIDTH', 300);

class Page extends TournamentPageBase
{
	private function show_details()
	{
		global $_page, $_lang_code, $_profile;
		
		$page_size = ROW_COUNT * COLUMN_COUNT;
		$event_count = 0;
		$column_count = 0;
		$now = time();
		
		$query = new DbQuery(
			'SELECT e.id, e.name, e.start_time, e.duration, e.flags, ct.name_' . $_lang_code . ', cr.name_' . $_lang_code . ', ct.timezone, a.id, a.flags, a.address, a.map_url, a.name FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN countries cr ON ct.country_id = cr.id' .
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
				if ($event_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			echo '<tr' . $dark_class . '><td align="center" style="height: 32px;"><b>' . $name . '</b></td></tr>';


			echo '<tr' . $light_class . '><td align="center"><a href="' . $url . '?bck=1&id=' . $id . '">';
			$event_pic->
				set($id, $name, $flags)->
				set($addr_id, $addr, $addr_flags);
			$event_pic->show(ICONS_DIR, false);
			echo '</a><br>' . format_date('l, F d, Y', $start_time, $event_timezone) . '</td></tr></table>';
			
			echo '</td>';
			
			++$event_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($event_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*) FROM events WHERE tournament_id = ? AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0', $this->id);
		show_pages_navigation(ROW_COUNT * COLUMN_COUNT, $count);
		
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
