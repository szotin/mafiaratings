<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/league.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	private function show_requests()
	{
		global $_profile;
		
		if ($_profile == NULL)
		{
			return;
		}
		
		$admin = is_permitted(PERMISSION_ADMIN);
		$condition = new SQL();
		if (!$admin)
		{
			$condition->add(' WHERE user_id = ?', $_profile->user_id);
		}
		
		$column_count = 0;
		$leagues_count = 0;
		$query = new DbQuery('SELECT id, name FROM league_requests l', $condition);
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
		
			if ($column_count == 0)
			{
				if ($leagues_count == 0)
				{
					echo '<p><table class="bordered" width="100%"><tr class="darker"><td colspan="' . COLUMN_COUNT . '">' . get_label('Pending requests for creating a league') . ':</td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}

			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="bottom" class="light">';
			
			echo '<table class="transp" width="100%">';
			if ($admin)
			{
				echo '<tr class="darker"><td align="left" style="padding:2px;">';
				echo '<button class="icon" onclick="mr.acceptLeague(' . $id . ')" title="' . get_label('Accept [0]', $name) . '"><img src="images/accept.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.declineLeague(' . $id . ')" title="' . get_label('Decline [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '</td></tr>';
			}
			echo '<tr><td align="center">';
			echo '<img src="images/' . ICONS_DIR . 'league.png" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '"><br><b>' . $name . '</b>';
			echo '</td></tr>';
			echo '</table>';
			echo '</td>';
			
			++$leagues_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($leagues_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table></p>';
		}
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('leagues')));
		echo ' <input type="checkbox" id="retired" onclick="filterRetired()"';
		if (isset($_REQUEST['retired']))
		{
			echo ' checked';
		}
		echo '> ' . get_label('Show retired leagues');
		echo '</td></tr></table></p>';
		
		$this->show_requests();
		
		$retired = isset($_REQUEST['retired']);
		$condition = new SQL(' WHERE (l.flags & ' . LEAGUE_FLAG_RETIRED);
		if ($retired)
		{
			$condition->add(') <> 0');
		}
		else
		{
			$condition->add(') = 0');
		}
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND l.id IN (SELECT league_id FROM league_clubs WHERE club_id = ?)', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND l.id IN (SELECT league_id FROM league_clubs WHERE club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?))', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND l.id IN (SELECT league_id FROM league_clubs WHERE club_id IN (SELECT id FROM clubs WHERE city_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND l.id IN (SELECT league_id FROM league_clubs WHERE club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE country_id = ?)))', $ccc_id);
			break;
		}
		
		$page_size = ROW_COUNT * COLUMN_COUNT;
		$column_count = 0;
		$leagues_count = 0;
		if ($_profile != NULL && !$retired)
		{
			--$page_size;
			++$column_count;
			++$leagues_count;
		}
		
		list ($count) = Db::record(get_label('league'), 'SELECT count(*) FROM leagues l ', $condition);
		
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT l.id, l.name, l.flags, l.web_site FROM leagues l', $condition);
		$query->add(' ORDER BY l.name LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		
		$league_pic = new Picture(LEAGUE_PICTURE);
			
		if ($_profile != NULL && !$retired)
		{
			echo '<table class="bordered" width="100%"><tr>';
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="center" class="light">';	
			echo '<table class="transp" width="100%">';
			echo '<tr><td align="center"><a href="#" onclick="mr.createLeague()">' . get_label('Create league');
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '">';
			echo '</td></tr></table>';
			echo '</td>';
		}
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $url) = $row;
			
			if ($column_count == 0)
			{
				if ($leagues_count == 0)
				{
					echo '<table class="bordered" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}

			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="bottom" class="light">';
			
			echo '<table class="transp" width="100%">';
			
			if ($_profile != NULL)
			{
				echo '<tr class="darker"><td align="left" style="padding:2px;">';
				show_league_buttons($id, $name, $flags);
				echo '</td></tr>';
			}
			
			echo '<tr><td align="center"><a href="league_main.php?bck=1&id=' . $id . '">';
			$league_pic->set($id, $name, $flags);
			$league_pic->show(ICONS_DIR, false);
			echo '<br><b>' . $name . '</b>';
			echo '</a></td></tr>';
			
			echo '</table>';
			echo '</td>';
			
			++$leagues_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($leagues_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
		show_pages_navigation($page_size, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filterRetired()
		{
			goTo({retired: ($("#retired").attr("checked") ? null : undefined), page: undefined});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Leagues'));

?>