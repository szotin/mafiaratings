<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/languages.php';
require_once 'include/club.php';

define('PAGE_SIZE', ADVERTS_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$expired = 0;
		if (isset($_REQUEST['expired']))
		{
			$expired = $_REQUEST['expired'];
		}
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('adverts')));
		echo '<input type="checkbox" id="expired" onclick="filterExpired()"';
		if ($expired)
		{
			echo ' checked';
		}
		echo '> ' . get_label('show expired adverts');
		echo '</td></tr></table></p>';
		
		$condition = new SQL(' FROM news n JOIN clubs c ON c.id = n.club_id JOIN cities ct ON ct.id = c.city_id');
		$delim = ' WHERE ';
		
		if (!$expired)
		{
			$condition->add($delim . ' expires >= UNIX_TIMESTAMP()');
			$delim = ' AND ';
		}
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add($delim . 'c.id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add($delim . 'c.id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add($delim . '(ct.id = ? OR ct.area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add($delim . 'ct.country_id = ?', $ccc_id);
			break;
		}
		
		list ($count) = Db::record(get_label('advert'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery('SELECT c.id, c.name, c.flags, ct.timezone, n.id, n.timestamp, n.message', $condition);
		$query->add(' ORDER BY n.timestamp DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $timezone, $id, $timestamp, $message) = $row;
			echo '<tr>';
			echo '<td width="100" align="center" valign="top" class="dark"><a href="club_main.php?id=' . $club_id . '&bck=1"><p>' . $club_name . '</p>';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, false);
			echo '</a></td><td valign="top"><b>' . format_date('l, F d, Y', $timestamp, $timezone) . ':</b><br>' . $message . '</td></tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filterExpired()
		{
			goTo({expired: ($("#expired").attr("checked") ? 1 : 0), page: undefined});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Adverts'));

?>