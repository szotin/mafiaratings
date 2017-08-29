<?php

require_once 'include/general_page_base.php';
require_once 'include/country.php';
require_once 'include/city.php';
require_once 'include/pages.php';

define("PAGE_SIZE",20);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_lang_code, $_page;
		
		$query = new DbQuery(
			'SELECT i.id, i.name_' . $_lang_code . ', i.flags, o.name_' . $_lang_code . ', i.timezone FROM cities i' . 
			' JOIN countries o ON i.country_id = o.id' .
			' WHERE (i.flags & ' . CITY_FLAG_NOT_CONFIRMED . ') <> 0' .
			' ORDER BY i.name_' . $_lang_code);
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
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
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
			'SELECT i.id, i.name_' . $_lang_code . ', i.flags, o.name_' . $_lang_code . ', i.timezone, n.name_' . $_lang_code . ' FROM cities i' . 
				' JOIN countries o ON i.country_id = o.id' .
				' LEFT OUTER JOIN cities n ON i.area_id = n.id',
			$condition);
		$query->add(' ORDER BY i.name_' . $_lang_code . ' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td width="56">';
		echo '<button class="icon" onclick="mr.createCity()" title="' . get_label('Create [0]', get_label('city')) . '"><img src="images/create.png" border="0"></button>';
		echo '<td>'.get_label('City').'</td><td width="180">'.get_label('Near').'</td><td width="180">'.get_label('Country').'</td><td width="180">'.get_label('Time zone').'</td></tr>';
		while ($row = $query->next())
		{
			list($id, $city_name, $flags, $country_name, $timezone, $near) = $row;
			if ($near == NULL)
			{
				$near = '&nbsp;';
			}
			
			echo '<tr><td class="dark">';
			show_city_buttons($id, $city_name, $flags);
			echo '</td><td>' . $city_name . '</td><td>' . $near . '</td><td>' . $country_name . '</td><td width="180">' . $timezone . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Cities'), U_PERM_ADMIN);

?>