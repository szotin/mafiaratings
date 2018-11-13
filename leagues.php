<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/league.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';

define('PAGE_SIZE', 30);
define('COLUMN_COUNT', 6);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	protected function show_filter_fields()
	{
		echo '<input type="checkbox" id="retired" onclick="filter()"';
		if (isset($_REQUEST['retired']))
		{
			echo ' checked';
		}
		echo '> ' . get_label('Show retired leagues');
	}
	
	protected function get_filter_js()
	{
		return '+ ($("#retired").attr("checked") ? "&retired=" : "")';
	}
	
	private function show_requests()
	{
		global $_profile;
		
		if ($_profile == NULL)
		{
			return;
		}
		
		$admin = $_profile->is_admin();
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
			echo '<tr><td align="center">';
			echo '<b>' . $name . '</b><br><img src="images/' . ICONS_DIR . 'league.png" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '">';
			echo '</td></tr>';
			if ($admin)
			{
				echo '<tr class="darker"><td align="left" style="padding:2px;">';
				echo '<button class="icon" onclick="mr.acceptLeague(' . $id . ')" title="' . get_label('Accept [0]', $name) . '"><img src="images/accept.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.declineLeague(' . $id . ')" title="' . get_label('Decline [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '</td></tr>';
			}
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
		global $_profile, $_lang_code, $_page;
		
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
		
		$page_size = PAGE_SIZE;
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
			
			echo '<tr><td align="center"><a href="league_main.php?bck=1&id=' . $id . '">';
			echo '<b>' . $name . '</b><br>';
			show_league_pic($id, $name, $flags, ICONS_DIR);
			echo '</a></td></tr>';
			
			if ($_profile != NULL)
			{
				echo '<tr class="darker"><td align="left" style="padding:2px;">';
				show_league_buttons($id, $name, $flags);
				echo '</td></tr>';
			}
			
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
	}
}

$page = new Page();
$page->run(get_label('Leagues'));

?>