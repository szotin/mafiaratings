<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/tournament.php';
require_once 'include/address.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_page, $_lang, $_profile;
		
		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER, $this->id);
		$page_size = ROW_COUNT * COLUMN_COUNT;
		$tournament_count = 0;
		$column_count = 0;
		
		$condition = new SQL('t.club_id = ? AND UNIX_TIMESTAMP() <= t.start_time + t.duration', $this->id);
		if ($is_manager)
		{
			--$page_size;
			++$tournament_count;
			++$column_count;
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*) FROM tournaments t WHERE ', $condition);
		show_pages_navigation($page_size, $count);
		
		if ($is_manager)
		{
			echo '<table class="bordered light" width="100%"><tr>';
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';	
			echo '<table class="transp" width="100%">';
			echo '<tr class="light"><td align="left" style="padding:2px;>';
			show_club_buttons(-1, '', 0, 0);
			echo '</td></tr><tr><td align="center"><a href="#" onclick="mr.createTournament(' . $this->id . ')">' . get_label('Create [0]', get_label('tournament'));
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '">';
			echo '</td></tr></table>';
			echo '</td>';
		}
		
		$query = new DbQuery('SELECT t.id, t.name, t.start_time, t.duration, t.flags, nct.name, ncr.name, ct.timezone, a.id, a.flags, a.address, a.map_url, a.name FROM tournaments t');
		$query->add(
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN countries cr ON ct.country_id = cr.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0 WHERE ',
			$condition);
		$query->add(' ORDER BY t.start_time LIMIT ' . ($_page * $page_size) . ',' . $page_size);

		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		while ($row = $query->next())
		{
			list ($id, $name, $start_time, $duration, $flags, $city_name, $country_name, $tournament_timezone, $addr_id, $addr_flags, $addr, $addr_url, $addr_name) = $row;
			if ($name == $addr_name)
			{
				$name = $addr;
			}
			if ($column_count == 0)
			{
				if ($tournament_count == 0)
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
			if ($_profile != NULL)
			{
				echo '<tr><td class="dark" style="padding:2px;">';
				show_tournament_buttons($id, $start_time, $duration, $flags, $this->id, $this->flags, $is_manager);
				echo '</td></tr>';	
			}
			
			echo '<tr><td align="center"><a href="tournament_info.php?bck=1&id=' . $id . '"><b>' . format_date('l, F d, Y <br> H:i', $start_time, $tournament_timezone) . '</b><br>';
			$tournament_pic->set($id, $name, $flags);
			$tournament_pic->show(ICONS_DIR, false);
			echo '</a><br>' . $name;
			echo '</td></tr></table>';
			
			echo '</td>';
			
			++$tournament_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($tournament_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
		show_pages_navigation($page_size, $count);
	}
}

$page = new Page();
$page->run(get_label('Upcoming tournaments'));

?>