<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang_code;
		
		$contains = '';
		if (isset($_REQUEST['contains']))
		{
			$contains = $_REQUEST['contains'];
		}
		
		$starts = '';
		if (isset($_REQUEST['starts']))
		{
			$starts = $_REQUEST['starts'];
		}
		
		$country = 0;
		if (isset($_REQUEST['country']))
		{
			$country = (int)$_REQUEST['country'];
			if ($country <= 0)
			{
				$query = new DbQuery('SELECT id FROM countries WHERE name_en = ? OR name_ru = ? ORDER BY id LIMIT 1', $_REQUEST['country'], $_REQUEST['country']);
				if ($row = $query->next())
				{
					list($country) = $row;
				}
			}
		}
		
		$city = 0;
		if (isset($_REQUEST['city']))
		{
			$city = (int)$_REQUEST['city'];
			if ($city <= 0)
			{
				$query = new DbQuery('SELECT id FROM cities WHERE name_en = ? OR name_ru = ? ORDER BY id LIMIT 1', $_REQUEST['city'], $_REQUEST['city']);
				if ($row = $query->next())
				{
					list($city) = $row;
				}
			}
		}
		
		$area = 0;
		if (isset($_REQUEST['area']))
		{
			$area = (int)$_REQUEST['area'];
			if ($area <= 0)
			{
				$query = new DbQuery('SELECT id FROM cities WHERE name_en = ? OR name_ru = ? ORDER BY id LIMIT 1', $_REQUEST['area'], $_REQUEST['area']);
				if ($row = $query->next())
				{
					list($area) = $row;
				}
			}
		}
		
		$page_size = DEFAULT_PAGE_SIZE;
		if (isset($_REQUEST['page_size']))
		{
			$page_size = (int)$_REQUEST['page_size'];
		}
		
		$page = 0;
		if (isset($_REQUEST['page']))
		{
			$page = (int)$_REQUEST['page'];
		}
		
		$count_only = isset($_REQUEST['count']);
		
		$condition = new SQL();
		$delim = ' WHERE ';
		if ($contains != '')
		{
			$contains = '%' . $contains . '%';
			$condition->add($delim . '(i.name_en LIKE(?) OR i.name_ru LIKE(?))', $contains, $contains);
			$delim = ' AND ';
		}
		
		if ($starts != '')
		{
			$starts1 = '% ' . $starts . '%';
			$starts2 = $starts . '%';
			$condition->add($delim . '(i.name_en LIKE(?) OR i.name_ru LIKE(?) OR i.name_en LIKE(?) OR i.name_ru LIKE(?))', $starts1, $starts1, $starts2, $starts2);
			$delim = ' AND ';
		}
		
		if ($city > 0)
		{
			$condition->add($delim . 'i.id = ?', $city);
		}
		else if ($area)
		{
			$query1 = new DbQuery('SELECT area_id FROM cities WHERE id = ?', $area);
			list($parent_city) = $query1->record('city');
			if ($parent_city == NULL)
			{
				$parent_city = $area;
			}
			$condition->add($delim . ' i.area_id = (SELECT area_id FROM cities WHERE id = ?)', $area);
		}
		else if ($country > 0)
		{
			$condition->add($delim . 'i.country_id = ?', $country);
		}
		
		list($count) = Db::record('city', 'SELECT count(*) FROM cities i JOIN countries o ON o.id = i.country_id', $condition);
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery('SELECT i.id, i.name_' . $_lang_code . ', o.id, o.name_' . $_lang_code . ', i.area_id FROM cities i JOIN countries o ON o.id = i.country_id', $condition);
			$query->add(' ORDER BY i.name_' . $_lang_code);
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$cities = array();
			while ($row = $query->next())
			{
				$city = new stdClass();
				list ($city->id, $city->city, $city->country_id, $city->country, $area) = $row;
				$city->id = (int)$city->id;
				$city->country_id = (int)$city->country_id;
				if ($area != NULL)
				{
					$city->area_id = (int)$area;
				}
				else
				{
					$city->area_id = $city->id;
				}
				$cities[] = $city;
			}
			$this->response['cities'] = $cities;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('contains', 'Search pattern. For example: <a href="cities.php?contains=va"><?php echo PRODUCT_URL; ?>/api/get/cities.php?contains=va</a> returns cities containing "va" in their name.', '-');
		$help->request_param('starts', 'Search pattern. For example: <a href="cities.php?starts=va"><?php echo PRODUCT_URL; ?>/api/get/cities.php?starts=va</a> returns cities with names starting with "va".', '-');
		$help->request_param('city', 'City id or city name. For example: <a href="cities.php?city=1"><?php echo PRODUCT_URL; ?>/api/get/cities.php?city=1</a> returns information about Vancouver; <a href="cities.php?city=moscow"><?php echo PRODUCT_URL; ?>/api/get/cities.php?city=moscow</a> returns information about Moscow.', '-');
		$help->request_param('area', 'City id or city name. For example: <a href="cities.php?area=1"><?php echo PRODUCT_URL; ?>/api/get/cities.php?area=1</a> returns cities near Vancouver; <a href="cities.php?area=moscow"><?php echo PRODUCT_URL; ?>/api/get/cities.php?area=moscow</a> returns cities near Moscow.', '-');
		$help->request_param('country', 'Country id or country name. For example: <a href="cities.php?country=2"><?php echo PRODUCT_URL; ?>/api/get/cities.php?country=2</a> returns Russian cities; <a href="cities.php?country=canada"><?php echo PRODUCT_URL; ?>/api/get/cities.php?country=canada</a> returns Canadian cities.', '-');
		$help->request_param('lang', 'Language for city and country names. 1 is English; 2 is Russian. For example: <a href="cities.php?lang=2"><?php echo PRODUCT_URL; ?>/api/get/cities.php?lang=2</a> returns cities names in Russian. If not specified, default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.', '-');
		$help->request_param('count', 'Returns cities count instead of the cities themselves. For example: <a href="cities.php?contains=mo&count"><?php echo PRODUCT_URL; ?>/api/get/cities.php?contains=mo&count</a> returns how many cities contain "mo" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="cities.php?page=1"><?php echo PRODUCT_URL; ?>/api/get/cities.php?page=1</a> returns the second page of cities by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . DEFAULT_PAGE_SIZE . '. For example: <a href="cities.php?page_size=32"><?php echo PRODUCT_URL; ?>/api/get/cities.php?page_size=32</a> returns first 32 cities; <a href="cities.php?page_size=0"><?php echo PRODUCT_URL; ?>/api/get/cities.php?page_size=0</a> returns cities in one page; <a href="cities.php"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a> returns first ' . DEFAULT_PAGE_SIZE . ' cities by alphabet.', '-');

		$param = $help->response_param('cities', 'The array of cities. Cities are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'City id.');
			$param->sub_param('city', 'City name.');
			$param->sub_param('country_id', 'Country id that this city belongs to.');
			$param->sub_param('country', 'Country name.');
			$param->sub_param('area_id', 'City id. This is the id of the center city grouping other cities around it. For example Burnaby, Richmond, and Vancouver have Vancouver id as their area id.');
		$help->response_param('count', 'Total number of cities satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Cities', CURRENT_VERSION);

?>