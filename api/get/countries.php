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
			$condition->add($delim . '(name_en LIKE(?) OR name_ru LIKE(?))', $contains, $contains);
			$delim = ' AND ';
		}
		
		if ($starts != '')
		{
			$starts1 = '% ' . $starts . '%';
			$starts2 = $starts . '%';
			$condition->add($delim . '(name_en LIKE(?) OR name_ru LIKE(?) OR name_en LIKE(?) OR name_ru LIKE(?))', $starts1, $starts1, $starts2, $starts2);
			$delim = ' AND ';
		}
		
		if ($country > 0)
		{
			$condition->add($delim . 'id = ?', $country);
		}
		
		list($count) = Db::record('country', 'SELECT count(*) FROM countries', $condition);
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery('SELECT id, name_' . $_lang_code . ', code FROM countries', $condition);
			$query->add(' ORDER BY name_' . $_lang_code);
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
		$help->request_param('contains', 'Search pattern. For example: <a href="countries.php?contains=us"><?php echo PRODUCT_URL; ?>/api/get/countries.php?contains=us</a> returns countries containing "us" in their name.', '-');
		$help->request_param('starts', 'Search pattern. For example: <a href="countries.php?starts=us"><?php echo PRODUCT_URL; ?>/api/get/countries.php?starts=us</a> returns countries with names starting with "us".', '-');
		$help->request_param('country', 'Country id or country name. For example: <a href="countries.php?country=1"><?php echo PRODUCT_URL; ?>/api/get/countries.php?country=1</a> returns information about Canada; <a href="countries.php?country=russia"><?php echo PRODUCT_URL; ?>/api/get/countries.php?country=russia</a> returns information about Russia.', '-');
		$help->request_param('count', 'Returns countries count instead of the countries themselves. For example: <a href="countries.php?contains=an&count"><?php echo PRODUCT_URL; ?>/api/get/countries.php?contains=an&count</a> returns how many countries contain "an" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="countries.php?page=1"><?php echo PRODUCT_URL; ?>/api/get/countries.php?page=1</a> returns the second page of countries by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . DEFAULT_PAGE_SIZE . '. For example: <a href="countries.php?page_size=32"><?php echo PRODUCT_URL; ?>/api/get/countries.php?page_size=32</a> returns first 32 countries; <a href="countries.php?page_size=0"><?php echo PRODUCT_URL; ?>/api/get/countries.php?page_size=0</a> returns countries in one page; <a href="countries.php"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a> returns first ' . DEFAULT_PAGE_SIZE . ' countries by alphabet.', '-');
		
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