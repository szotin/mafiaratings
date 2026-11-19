<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/league.php';
require_once 'include/series.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';
require_once 'include/cards.php';
require_once 'include/checkbox_filter.php';

define('FLAG_FILTER_CLOSED', 0x0001);
define('FLAG_FILTER_NOT_CLOSED', 0x0002);

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
		
		$count = 0;
		$query = new DbQuery('SELECT id, name FROM league_requests l', $condition);
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			if ($count++ == 0)
			{
				echo '<section class="tiles-sec"><div class="sec-hd"><h2>' . get_label('Pending requests for creating a league') . '</h2></div><div class="tiles">';
			}
			echo '<div class="tile"><img class="logo" src="images/' . ICONS_DIR . 'league.png" alt=""><div class="who-txt"><span class="who-name">' . $name . '</span></div>';
			if ($admin)
			{
				echo '<div class="tile-actions">';
				echo '<button class="icon" onclick="mr.acceptLeague(' . $id . ')" title="' . get_label('Accept [0]', $name) . '"><img src="images/accept.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.declineLeague(' . $id . ')" title="' . get_label('Decline [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '</div>';
			}
			echo '</div>';
		}
		if ($count > 0)
		{
			echo '</div></section>';
		}
	}
	
	protected function title_actions()
	{
		global $_profile;
		if ($_profile == NULL)
		{
			return '';
		}
		return '<a class="btn" href="#" onclick="mr.createLeague(); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Create league') . '</span></a>';
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		// Closed leagues as a filter of three states, as the other lists have them: all of them,
		// only the closed ones, or none of them - the last is where the list starts. The old address
		// with "closed" in it still opens the closed ones.
		$filter = FLAG_FILTER_NOT_CLOSED;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		else if (isset($_REQUEST['closed']))
		{
			$filter = FLAG_FILTER_CLOSED;
		}
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar"><div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('leagues')));
		echo '</div>';
		show_checkbox_filter(array(get_label('closed~plural')), $filter, 'filterClosed');
		echo '</div>';

		$this->show_requests();

		$condition = new SQL(' WHERE TRUE');
		if ($filter & FLAG_FILTER_CLOSED)
		{
			$condition->add(' AND (l.flags & ' . LEAGUE_FLAG_CLOSED . ') <> 0');
		}
		if ($filter & FLAG_FILTER_NOT_CLOSED)
		{
			$condition->add(' AND (l.flags & ' . LEAGUE_FLAG_CLOSED . ') = 0');
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
		list ($count) = Db::record(get_label('league'), 'SELECT count(*) FROM leagues l ', $condition);
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT l.id, nl.name, l.flags, l.web_site, (SELECT count(*) FROM league_clubs lc WHERE lc.league_id = l.id AND lc.flags = 0),' .
			' (SELECT count(*) FROM series s WHERE s.league_id = l.id AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0) FROM leagues l' .
			' JOIN names nl ON nl.id = l.name_id AND (nl.langs & '.$_lang.') <> 0', $condition);
		$query->add(' ORDER BY nl.name LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		
		$league_pic = new Picture(LEAGUE_PICTURE);
		$leagues_count = 0;
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $url, $clubs, $series_count) = $row;
			$closed = ($flags & LEAGUE_FLAG_CLOSED) != 0;
			if ($leagues_count++ == 0)
			{
				// The tiles of series.php: as wide, all as tall, the numbers in frames on the right.
				echo '<div class="tiles wide">';
			}
			$league_pic->set($id, $name, $flags);
			echo '<div class="tile' . ($closed ? ' is-off' : '') . '">' . card_logo($league_pic, $name);
			echo '<div class="who-txt"><a class="who-name" href="league_main.php?bck=1&id=' . $id . '">' . $name . '</a>';
			if (!empty($url))
			{
				echo '<span class="who-sub">' . PageBase::icon('link') . PageBase::url_host($url) . '</span>';
			}
			if ($closed)
			{
				echo '<span class="tile-meta"><span class="chip off">' . get_label('closed~fem') . '</span></span>';
			}
			// The buttons of a manager under the name: beside it they left the name no room.
			if ($_profile != NULL)
			{
				$buttons = PageBase::capture(function() use ($id, $name, $flags) { show_league_buttons($id, $name, $flags); });
				if (trim(strip_tags($buttons, '<button>')) != '')
				{
					echo '<div class="tile-actions under">' . $buttons . '</div>';
				}
			}
			echo '</div>';
			echo '<div class="tile-side tile-nums">';
			echo '<a class="tile-num" href="league_clubs.php?bck=1&id=' . $id . '" title="' . get_label('Clubs') . '"><b>' . $clubs . '</b><span>' . get_label('Clubs') . '</span></a>';
			echo '<a class="tile-num" href="league_series.php?bck=1&id=' . $id . '" title="' . get_label('Series') . '"><b>' . $series_count . '</b><span>' . get_label('Series') . '</span></a>';
			echo '</div>';
			echo '</div>';
		}
		if ($leagues_count > 0)
		{
			echo '</div>';
		}
		else
		{
			echo '<p class="empty">' . get_label('No leagues found.') . '</p>';
		}
		show_pages_navigation($page_size, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filterClosed()
		{
			goTo({filter: checkboxFilterFlags(), closed: undefined, page: undefined});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Leagues'));

?>