<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/country.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;

		check_permissions(PERMISSION_ADMIN);
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('countries')));
		echo '</td></tr></table></p>';
		
		$query = new DbQuery(
			'SELECT c.id, n.name, c.flags, c.code FROM countries c' . 
			' JOIN names n ON n.id = c.name_id AND (n.langs & ?) <> 0 ' .
			' WHERE (flags & ' . COUNTRY_FLAG_NOT_CONFIRMED .') <> 0' .
			' ORDER BY n.name', $_lang);
		if ($row = $query->next())
		{
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker">';
			echo '<td colspan="3"><b>' . get_label('Please confirm the next countries') . ':</b></td></tr>';
			do
			{
				list($id, $name, $flags, $code) = $row;
				echo '<tr><td width="56">';
				show_country_buttons($id, $name, $flags);
				echo '</td><td>' . $name . '</td><td width="50">' . $code . '</td></tr>';
			
			} while ($row = $query->next());
			echo '</table></p>';
		}
		
		$condition = new SQL(' WHERE (c.flags & ' . COUNTRY_FLAG_NOT_CONFIRMED . ') = 0');
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND c.id = (SELECT i.country_id FROM clubs cl JOIN cities i ON i.id = c.city_id WHERE cl.id = ?)', $ccc_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND c.id = (SELECT i.country_id FROM cities i WHERE i.id = ?)', $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND c.id = ?', $ccc_id);
			break;
		}
		
		list ($count) = Db::record(get_label('country'), 'SELECT count(*) FROM countries c', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery('SELECT c.id, n.name, c.flags, c.code FROM countries c JOIN names n ON n.id = c.name_id AND (n.langs & ?) <> 0', $_lang, $condition);
		$query->add(' ORDER BY n.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td width="56">';
		echo '<button class="icon" onclick="mr.createCountry()" title="' . get_label('Create country') . '"><img src="images/create.png" border="0"></button>';
		echo '</td><td>'.get_label('Country').'</td>';
		echo '<td width="50">'.get_label('Code').'</td></tr>';
		while ($row = $query->next())
		{
			list($id, $name, $flags, $code) = $row;
			echo '<tr><td class="dark">';
			show_country_buttons($id, $name, $flags);
			echo '</td><td>' . $name . '</td>';
			echo '</td><td>' . $code . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Countries'));

?>