<?php

require_once 'include/general_page_base.php';
require_once 'include/country.php';
require_once 'include/city.php';
require_once 'include/pages.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_lang, $_page;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('cities')));
		echo '</td></tr></table></p>';
		
		check_permissions(PERMISSION_ADMIN);
		$query = new DbQuery(
			'SELECT i.id, ni.name, i.flags, no.name, i.timezone FROM cities i' . 
			' JOIN countries o ON i.country_id = o.id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0' .
			' WHERE (i.flags & ' . CITY_FLAG_NOT_CONFIRMED . ') <> 0' .
			' ORDER BY ni.name', $_lang, $_lang);
		if ($row = $query->next())
		{
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker">';
			echo '<td colspan="4"><b>' . get_label('Please confirm the next cities') . ':</b></td></tr>';
			do
			{
				list($id, $city_name, $flags, $country_name, $timezone) = $row;
				echo '<tr><td width="56">';
				show_city_buttons($id, $city_name, $flags);
				echo '</td><td>' . $city_name . '</td><td width="180">' . $country_name . '</td><td width="180">' . $timezone . '</td></tr>';
				
			
			} while ($row = $query->next());
			echo '</table></p>';
		}
		
		$condition = new SQL(' WHERE (i.flags & ' . CITY_FLAG_NOT_CONFIRMED . ') = 0');
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND i.id IN (SELECT a.city_id FROM addresses a WHERE a.club_id = ?)', $ccc_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND i.id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND i.country_id = ?', $ccc_id);
			break;
		}
		list ($count) = Db::record(get_label('city'), 'SELECT count(*) FROM cities i ', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT i.id, ni.name, i.flags, no.name, i.timezone, i.area_id, na.name FROM cities i' . 
				' JOIN countries o ON i.country_id = o.id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0' .
				' JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0' .
				' LEFT OUTER JOIN cities a ON i.area_id = a.id' .
				' LEFT OUTER JOIN names na ON na.id = a.name_id AND (na.langs & ?) <> 0',
			$_lang, $_lang, $_lang, $condition);
		$query->add(' ORDER BY ni.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td width="56">';
		echo '<button class="icon" onclick="mr.createCity()" title="' . get_label('Create [0]', get_label('city')) . '"><img src="images/create.png" border="0"></button>';
		echo '<td>'.get_label('City').'</td><td width="180">'.get_label('Area').'</td><td width="180">'.get_label('Country').'</td><td width="180">'.get_label('Time zone').'</td></tr>';
		while ($row = $query->next())
		{
			list($id, $city_name, $flags, $country_name, $timezone, $area_id, $area_name) = $row;
			if ($area_id == NULL || $area_id == $id)
			{
				$area_name = '';
			}
			
			echo '<tr><td class="dark">';
			show_city_buttons($id, $city_name, $flags);
			echo '</td><td>' . $city_name . '</td><td>' . $area_name . '</td><td>' . $country_name . '</td><td width="180">' . $timezone . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Cities'));

?>