<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_profile, $_lang, $_page;
		
		$retired = isset($_REQUEST['retired']);
		$root_only = isset($_REQUEST['root']);
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('clubs')));
		echo '<input type="checkbox" id="retired" onclick="filter()"';
		if (isset($_REQUEST['retired']))
		{
			echo ' checked';
		}
		echo '> ' . get_label('Show retired clubs');
		
		echo ' <input type="checkbox" id="root" onclick="filter()"';
		if (isset($_REQUEST['root']))
		{
			echo ' checked';
		}
		echo '> ' . get_label('Root clubs only');
		echo '</td></tr></table></p>';
		
		$delimiter = ' WHERE ';
		$condition = new SQL();
		if (!$retired)
		{
			$condition->add($delimiter . '(c.flags & ' . CLUB_FLAG_RETIRED . ') = 0');
			$delimiter = ' AND ';
		}
		if ($root_only)
		{
			$condition->add($delimiter . 'c.parent_id IS NULL');
			$delimiter = ' AND ';
		}
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add($delimiter . 'c.id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add($delimiter . 'u.user_id = ?', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add($delimiter . 'c.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add($delimiter . 'i.country_id = ?', $ccc_id);
			break;
		}
		
		$page_size = ROW_COUNT * COLUMN_COUNT;
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
				' LEFT OUTER JOIN club_users u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id',
				' LEFT OUTER JOIN club_users u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id',
			$user_id, $condition);
		
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, ni.name, u.flags, (SELECT count(*) FROM games g WHERE g.club_id = c.id) as games FROM clubs c' .
				' LEFT OUTER JOIN club_users u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0',
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
			$this->club_pic->set($id, $name, $flags);
			$this->club_pic->show(ICONS_DIR, false);
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
		show_pages_navigation($page_size, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter()
		{
			var params = 
			{
				retired: $("#retired").attr("checked") ? null : undefined,
				root: $("#root").attr("checked") ? null : undefined
			};
			goTo(params);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Clubs'));

?>