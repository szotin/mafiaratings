<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';
require_once 'include/cards.php';
require_once 'include/club_cards.php';
require_once 'include/checkbox_filter.php';

define('FLAG_FILTER_CLOSED', 0x0001);
define('FLAG_FILTER_NOT_CLOSED', 0x0002);

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
		
		// Closed clubs as a filter of three states, as the other lists have them: all of them, only
		// the closed ones, or none of them - the last is where the list starts. The old address with
		// "closed" in it, which added the closed ones to the rest, still does.
		$filter = FLAG_FILTER_NOT_CLOSED;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		else if (isset($_REQUEST['closed']))
		{
			$filter = 0;
		}

		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar"><div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('clubs')));
		echo '</div>';
		show_checkbox_filter(array(get_label('closed~plural')), $filter, 'filterClosed');
		echo '</div>';

		$delimiter = ' WHERE ';
		$condition = new SQL();
		if ($filter & FLAG_FILTER_CLOSED)
		{
			$condition->add($delimiter . '(c.flags & ' . CLUB_FLAG_CLOSED . ') <> 0');
			$delimiter = ' AND ';
		}
		if ($filter & FLAG_FILTER_NOT_CLOSED)
		{
			$condition->add($delimiter . '(c.flags & ' . CLUB_FLAG_CLOSED . ') = 0');
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
		
		$clubs = array();
		while ($row = $query->next())
		{
			$club = new stdClass();
			list ($club->id, $club->name, $club->flags, $url, $club->city, $memb_flags, $club->games) = $row;
			$club->mine = !is_null($memb_flags);
			if ($_profile != NULL)
			{
				$id = $club->id;
				$name = $club->name;
				$flags = $club->flags;
				$club->buttons = PageBase::capture(function() use ($id, $name, $flags, $memb_flags) { show_club_buttons($id, $name, $flags, $memb_flags); });
			}
			$clubs[] = $club;
		}
		// The tiles: see include/club_cards.php, shared by every list of clubs.
		club_tiles_show($clubs, get_label('No clubs found.'));
		show_pages_navigation($page_size, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filterClosed()
		{
			goTo({filter: checkboxFilterFlags(), closed: undefined, root: undefined, page: undefined});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Clubs'));

?>