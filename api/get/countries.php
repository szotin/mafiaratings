<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang;
		
		$contains = get_optional_param('contains');
		$starts = get_optional_param('starts');
		$page_size = get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		$page = get_optional_param('page', 0);
		$count_only = isset($_REQUEST['count']);
		$country = get_optional_param('country', 0);
		$lang = (int)get_optional_param('lang', $_lang);
		if (!is_valid_lang($lang))
		{
			$lang = $_lang;
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
		
		if (!is_numeric($country))
		{
			$condition->add($delim . 'n.name = ?', $country);
		}
		else if ($country > 0)
		{
			$condition->add($delim . 'c.id = ?', $country);
		}
		
		list($count) = Db::record('country', 'SELECT count(DISTINCT c.id) FROM countries c JOIN names n ON n.id = c.name_id', $condition);
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery('SELECT DISTINCT c.id, nc.name, c.code FROM countries c JOIN names n ON n.id = c.name_id JOIN names nc ON nc.id = c.name_id AND (nc.langs & ?) <> 0', $lang, $condition);
			$query->add(' ORDER BY nc.name');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$countries = array();
			while ($row = $query->next())
			{
				$country = new stdClass();
				list ($country->id, $country->country, $country->code) = $row;
				$country->id = (int)$country->id;
				$countries[] = $country;
			}
			$this->response['countries'] = $countries;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('contains', 'Search pattern. For example: <a href="countries.php?contains=us">/api/get/countries.php?contains=us</a> returns countries containing "us" in their name.', '-');
		$help->request_param('starts', 'Search pattern. For example: <a href="countries.php?starts=us">/api/get/countries.php?starts=us</a> returns countries with names starting with "us".', '-');
		$help->request_param('country', 'Country id or country name. For example: <a href="countries.php?country=1">/api/get/countries.php?country=1</a> returns information about Canada; <a href="countries.php?country=russia">/api/get/countries.php?country=russia</a> returns information about Russia.', '-');
		$help->request_param('lang', 'Language id for returned names. For example: <a href="countries.php?lang=2">/api/get/countries.php?lang=2</a> returns country names in Russian.' . valid_langs_help(), 'default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.');
		$help->request_param('count', 'Returns countries count instead of the countries themselves. For example: <a href="countries.php?contains=an&count">/api/get/countries.php?contains=an&count</a> returns how many countries contain "an" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="countries.php?page=1">/api/get/countries.php?page=1</a> returns the second page of countries by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="countries.php?page_size=32">/api/get/countries.php?page_size=32</a> returns first 32 countries; <a href="countries.php?page_size=0">/api/get/countries.php?page_size=0</a> returns countries in one page; <a href="countries.php">/api/get/countries.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' countries by alphabet.', '-');
		
		$param = $help->response_param('countries', 'The array of countries. Countries are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Country id.');
			$param->sub_param('country', 'Country name.');
			$param->sub_param('code', 'Country code.');
		$help->response_param('count', 'Total number of countries satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Countries', CURRENT_VERSION);

?>