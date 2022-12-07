<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/languages.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang;
		
		$contains = get_optional_param('contains');
		$starts = get_optional_param('starts');
		$city = get_optional_param('city', 0);
		$page_size = get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		$page = get_optional_param('page', 0);
		$count_only = isset($_REQUEST['count']);
		$lang = (int)get_optional_param('lang', $_lang);
		if (!is_valid_lang($lang))
		{
			$lang = $_lang;
		}
		
		$country = get_optional_param('country', 0);
		if (!is_numeric($country))
		{
			list($country) = Db::record(get_label('country'), 'SELECT c.id FROM countries c JOIN names n ON n.id = c.name_id WHERE n.name = ? ORDER BY c.id LIMIT 1', $country);
		}
		
		$area = get_optional_param('area', 0);
		if (!is_numeric($area))
		{
			list($area) = Db::record(get_label('city'), 'SELECT c.id FROM cities c JOIN names n ON n.id = c.name_id WHERE n.name = ? ORDER BY c.id LIMIT 1', $area);
		}
		
		$condition = new SQL();
		$delim = ' WHERE ';
		if ($contains != '')
		{
			$contains = '%' . $contains . '%';
			$condition->add($delim . 'n.name LIKE(?)', $contains);
			$delim = ' AND ';
		}
		
		if ($starts != '')
		{
			$starts1 = '% ' . $starts . '%';
			$starts2 = $starts . '%';
			$condition->add($delim . '(n.name LIKE(?) OR n.name LIKE(?))', $starts1, $starts2);
			$delim = ' AND ';
		}
		
		if (!is_numeric($city))
		{
			$condition->add($delim . 'n.name = ?', $city);
		}
		else if ($city > 0)
		{
			$condition->add($delim . 'i.id = ?', $city);
		}
		else if ($area > 0)
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
		
		list($count) = Db::record('city', 
			'SELECT count(DISTINCT i.id) FROM cities i' .
			' JOIN countries o ON o.id = i.country_id' .
			' JOIN names n ON n.id = i.name_id' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0', $lang, $lang, $condition);
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery(
				'SELECT DISTINCT i.id, ni.name, o.id, no.name, i.area_id FROM cities i' .
				' JOIN countries o ON o.id = i.country_id' .
				' JOIN names n ON n.id = i.name_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0' .
				' JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0', $lang, $lang, $condition);
			$query->add(' ORDER BY ni.name');
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
		$help->request_param('contains', 'Search pattern. For example: <a href="cities.php?contains=va">/api/get/cities.php?contains=va</a> returns cities containing "va" in their name.', '-');
		$help->request_param('starts', 'Search pattern. For example: <a href="cities.php?starts=va">/api/get/cities.php?starts=va</a> returns cities with names starting with "va".', '-');
		$help->request_param('city', 'City id or city name. For example: <a href="cities.php?city=1">/api/get/cities.php?city=1</a> returns information about Vancouver; <a href="cities.php?city=moscow">/api/get/cities.php?city=moscow</a> returns information about Moscow.', '-');
		$help->request_param('area', 'City id or city name. For example: <a href="cities.php?area=1">/api/get/cities.php?area=1</a> returns cities near Vancouver; <a href="cities.php?area=moscow">/api/get/cities.php?area=moscow</a> returns cities near Moscow.', '-');
		$help->request_param('country', 'Country id or country name. For example: <a href="cities.php?country=2">/api/get/cities.php?country=2</a> returns Russian cities; <a href="cities.php?country=canada">/api/get/cities.php?country=canada</a> returns Canadian cities.', '-');
		$help->request_param('lang', 'Language for city and country names. For example: <a href="cities.php?lang=2">/api/get/cities.php?lang=2</a> returns cities names in Russian.' . valid_langs_help(), 'default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.');
		$help->request_param('count', 'Returns cities count instead of the cities themselves. For example: <a href="cities.php?contains=mo&count">/api/get/cities.php?contains=mo&count</a> returns how many cities contain "mo" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="cities.php?page=1">/api/get/cities.php?page=1</a> returns the second page of cities by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="cities.php?page_size=32">/api/get/cities.php?page_size=32</a> returns first 32 cities; <a href="cities.php?page_size=0">/api/get/cities.php?page_size=0</a> returns cities in one page; <a href="cities.php">/api/get/cities.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' cities by alphabet.', '-');

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