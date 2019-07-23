<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/languages.php';
require_once 'include/address.php';

define('COLUMN_COUNT', 5);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_lang_code;
	
		$is_manager = false;
		if ($_profile != NULL)
		{
			$is_manager = $_profile->is_club_manager($this->id);
		}
		
		echo '<p><table class="bordered light" width="100%"><tr class="darker"><td colspan="5"><b>' . get_label('Addresses') . '</b></td></tr>';
		$column_count = 0;
		$addr_count = 0;
		if ($is_manager)
		{
			++$column_count;
			++$addr_count;
			echo '<tr><td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';	
			echo '<table class="transp" width="100%">';
			echo '<tr class="light"><td align="left" style="padding: 2px;>';
			show_address_buttons(-1, '', 0, -1);
			echo '</td></tr><tr><td align="center"><a href="#" onclick="mr.createAddr(' . $this->id . ')">' . get_label('Create [0]', get_label('address'));
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '">';
			echo '</td></tr></table>';
			echo '</td>';
		}
		
		$address_pics = new Picture(ADDRESS_PICTURE);
		$query = new DbQuery(
			'SELECT a.id, a.name, a.address, a.map_url, a.flags, ct.name_' . $_lang_code . ', cr.name_' . $_lang_code .
				', (SELECT count(*) FROM events e WHERE e.address_id = a.id) cnt FROM addresses a' . 
				' JOIN cities ct ON a.city_id = ct.id' .
				' JOIN countries cr ON ct.country_id = cr.id' .
				' WHERE club_id = ?' .
				' ORDER BY (a.flags & ' . ADDRESS_FLAG_NOT_USED . '), cnt DESC, a.name',
			$this->id);
		while ($row = $query->next())
		{
			list ($addr_id, $addr_name, $addr, $addr_url, $addr_flags, $addr_city, $addr_country, $use_count) = $row;
			if (($addr_flags & ADDRESS_FLAG_NOT_USED) != 0 && $use_count <= 0 && !$is_manager)
			{
				continue;
			}
			
			if ($column_count == 0)
			{
				if ($addr_count > 0)
				{
					echo '</tr>';
				}
				echo '<tr>';
			}

			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top"';
			if (($addr_flags & ADDRESS_FLAG_NOT_USED) != 0)
			{
				echo ' class="dark">';
			}
			else
			{
				echo ' class="light">';
			}
			
			echo '<table class="transp" width="100%">';
			if ($is_manager)
			{
				echo '<tr class="darker"><td align="left" style="padding: 2px;">';
				show_address_buttons($addr_id, $addr_name, $addr_flags, $this->id);
				echo '</td></tr>';
			}
			
			echo '<tr><td align="center"><a href="address_info.php?bck=1&id=' . $addr_id . '">';
			echo '<b>' . $addr_name . '</b><br>';
			$address_pics->set($addr_id, $addr_name, $addr_flags);
			$address_pics->show(ICONS_DIR);
			echo '<br></a>' . addr_label($addr, $addr_city, $addr_country) . '<br>';
			
			echo '</td></tr></table>';
			echo '</td>';
			
			++$addr_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($addr_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Addresses'));

?>