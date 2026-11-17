<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/league.php';
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
		
		$closed = isset($_REQUEST['closed']);
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar"><div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('leagues')));
		echo '</div>';
		echo '<label class="fchip"><input type="checkbox" id="closed" onclick="filterClosed()"' . ($closed ? ' checked' : '') . '>' . get_label('Show closed leagues') . '</label>';
		echo '</div>';
		
		$this->show_requests();
		
		$condition = new SQL(' WHERE (l.flags & ' . LEAGUE_FLAG_CLOSED);
		if ($closed)
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
		list ($count) = Db::record(get_label('league'), 'SELECT count(*) FROM leagues l ', $condition);
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT l.id, nl.name, l.flags, l.web_site, (SELECT count(*) FROM league_clubs lc WHERE lc.league_id = l.id AND lc.flags = 0) FROM leagues l' .
			' JOIN names nl ON nl.id = l.name_id AND (nl.langs & '.$_lang.') <> 0', $condition);
		$query->add(' ORDER BY nl.name LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		
		$league_pic = new Picture(LEAGUE_PICTURE);
		$leagues_count = 0;
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $url, $clubs) = $row;
			if ($leagues_count++ == 0)
			{
				echo '<div class="tiles">';
			}
			$league_pic->set($id, $name, $flags);
			echo '<div class="tile' . (($flags & LEAGUE_FLAG_CLOSED) ? ' is-off' : '') . '">' . card_logo($league_pic, $name);
			echo '<div class="who-txt"><a class="who-name" href="league_main.php?bck=1&id=' . $id . '">' . $name . '</a>';
			echo '<span class="who-sub">' . PageBase::icon('users') . get_label('Clubs') . ': ' . $clubs;
			if (!empty($url))
			{
				echo ' · ' . PageBase::url_host($url);
			}
			echo '</span></div>';
			if ($_profile != NULL)
			{
				$buttons = PageBase::capture(function() use ($id, $name, $flags) { show_league_buttons($id, $name, $flags); });
				if (trim(strip_tags($buttons, '<button>')) != '')
				{
					echo '<div class="tile-actions">' . $buttons . '</div>';
				}
			}
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
			goTo({closed: ($("#closed").attr("checked") ? null : undefined), page: undefined});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Leagues'));

?>