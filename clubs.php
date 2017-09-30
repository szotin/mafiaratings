<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';

define('PAGE_SIZE', 30);
define('COLUMN_COUNT', 6);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->ccc_title = get_label('Show only clubs from a specific city, or country.');
	}
	
	protected function show_filter_fields()
	{
		echo '<input type="checkbox" id="retired" onclick="filter()"';
		if (isset($_REQUEST['retired']))
		{
			echo ' checked';
		}
		echo '> ' . get_label('Show retired clubs');
	}
	
	protected function get_filter_js()
	{
		return '+ ($("#retired").attr("checked") ? "&retired=" : "")';
	}

	protected function show_body()
	{
		global $_profile, $_lang_code, $_page;
		
		$retired = isset($_REQUEST['retired']);
		
		$condition = new SQL(' WHERE (c.flags & ' . CLUB_FLAG_RETIRED);
		if ($retired)
		{
			$condition->add(') <> 0');
		}
		else
		{
			$condition->add(') = 0');
		}
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND c.id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND u.user_id = ?', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND c.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND i.country_id = ?', $ccc_id);
			break;
		}
		
		$page_size = PAGE_SIZE;
		$column_count = 0;
		$clubs_count = 0;
		if ($_profile != NULL && !$retired)
		{
			--$page_size;
			++$column_count;
			++$clubs_count;
		}
		
		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
		}
		
		list ($count) = Db::record(get_label('club'), 'SELECT count(*) FROM clubs c ' .
				' LEFT OUTER JOIN user_clubs u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id',
				' LEFT OUTER JOIN user_clubs u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id',
			$user_id, $condition);
		
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, i.name_' . $_lang_code . ', u.flags, (SELECT count(*) FROM games g WHERE g.club_id = c.id) as games FROM clubs c' .
				' LEFT OUTER JOIN user_clubs u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id',
			$user_id, $condition);
		$query->add(' ORDER BY ISNULL(u.flags), games DESC, c.name LIMIT ' . ($_page * $page_size) . ',' . $page_size);
			
		if ($_profile != NULL && !$retired)
		{
			echo '<table class="bordered" width="100%"><tr>';
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';	
			echo '<table class="transp" width="100%">';
			echo '<tr><td align="left" class="light wide">';
			show_club_buttons(-1, '', 0, 0);
			echo '</td></tr><tr><td align="center"><a href="#" onclick="mr.createClub()">' . get_label('Create [0]', get_label('club'));
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '">';
			echo '</td></tr></table>';
			echo '</td>';
		}
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $url, $city_name, $memb_flags) = $row;
			
			if ($column_count == 0)
			{
				if ($clubs_count == 0)
				{
					echo '<table class="bordered" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}

			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';
			
			echo '<table class="transp" width="100%">';
			if ($_profile != NULL)
			{
				echo '<tr class="darker"><td align="left" style="padding:2px;">';
				show_club_buttons($id, $name, $flags, $memb_flags);
				echo '</td></tr>';
			}
			
			echo '<tr><td align="center"><a href="club_main.php?bck=1&id=' . $id . '">';
			echo '<b>' . $name . '</b><br>';
			show_club_pic($id, $name, $flags, ICONS_DIR);
			echo '<br></a>' . $city_name . '<br>';
			
			echo '</td></tr></table>';
			echo '</td>';
			
			++$clubs_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($clubs_count > 0)
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
$page->run(get_label('Clubs'), PERM_ALL);

?>