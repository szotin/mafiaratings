<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';
require_once 'include/cards.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	protected function title_actions()
	{
		global $_profile;
		if ($_profile == NULL)
		{
			return '';
		}
		return '<a class="btn" href="#" onclick="mr.createClub(); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Create [0]', get_label('club')) . '</span></a>';
	}
	
	protected function show_body()
	{
		global $_profile, $_lang, $_page;
		
		$closed = isset($_REQUEST['closed']);
		$root_only = isset($_REQUEST['root']);
		
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar"><div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('clubs')));
		echo '</div>';
		echo '<label class="fchip"><input type="checkbox" id="closed" onclick="filter()"' . ($closed ? ' checked' : '') . '>' . get_label('Show closed clubs') . '</label>';
		echo '<label class="fchip"><input type="checkbox" id="root" onclick="filter()"' . ($root_only ? ' checked' : '') . '>' . get_label('Root clubs only') . '</label>';
		echo '</div>';
		
		$delimiter = ' WHERE ';
		$condition = new SQL();
		if (!$closed)
		{
			$condition->add($delimiter . '(c.flags & ' . CLUB_FLAG_CLOSED . ') = 0');
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
		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
		}
		
		list ($count) = Db::record(get_label('club'), 'SELECT count(*) FROM clubs c ' .
				' LEFT OUTER JOIN club_regs u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id',
			$user_id, $condition);
		
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, c.web_site, ni.name, u.flags, (SELECT count(*) FROM games g WHERE g.club_id = c.id) as games FROM clubs c' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN club_regs u ON u.user_id = ? AND u.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0',
			$user_id, $condition);
		$query->add(' ORDER BY ISNULL(u.flags), games DESC, nc.name LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		
		$clubs_count = 0;
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $url, $city_name, $memb_flags, $games) = $row;
			if ($clubs_count++ == 0)
			{
				echo '<div class="tiles">';
			}
			$classes = 'tile';
			if ($flags & CLUB_FLAG_CLOSED)
			{
				$classes .= ' is-off';
			}
			if (!is_null($memb_flags))
			{
				$classes .= ' is-mine';
			}
			$this->club_pic->set($id, $name, $flags);
			echo '<div class="' . $classes . '">' . card_logo($this->club_pic, $name);
			echo '<div class="who-txt"><a class="who-name" href="club_main.php?bck=1&id=' . $id . '">' . $name . '</a>';
			echo '<span class="who-sub">' . PageBase::icon('pin') . $city_name . '</span></div>';
			if ($_profile != NULL)
			{
				$buttons = PageBase::capture(function() use ($id, $name, $flags, $memb_flags) { show_club_buttons($id, $name, $flags, $memb_flags); });
				if (trim(strip_tags($buttons, '<button>')) != '')
				{
					echo '<div class="tile-actions">' . $buttons . '</div>';
				}
			}
			echo '</div>';
		}
		if ($clubs_count > 0)
		{
			echo '</div>';
		}
		else
		{
			echo '<p class="empty">' . get_label('No clubs found.') . '</p>';
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
				closed: $("#closed").attr("checked") ? null : undefined,
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