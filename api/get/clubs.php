<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/rules.php';

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
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$city = 0;
		if (isset($_REQUEST['city']))
		{
			$city = (int)$_REQUEST['city'];
		}
		
		$area = 0;
		if (isset($_REQUEST['area']))
		{
			$area = (int)$_REQUEST['area'];
		}
		
		$country = 0;
		if (isset($_REQUEST['country']))
		{
			$country = (int)$_REQUEST['country'];
		}
		
		$user = 0;
		if (isset($_REQUEST['user']))
		{
			$user = (int)$_REQUEST['user'];
		}
		
		$langs = 0;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
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
		
		$condition = new SQL(' WHERE (c.flags & ?) = 0', CLUB_FLAG_RETIRED);
		if ($contains != '')
		{
			$contains = '%' . $contains . '%';
			$condition->add(' AND name LIKE(?)', $contains);
		}
		
		if ($starts != '')
		{
			$starts1 = '% ' . $starts . '%';
			$starts2 = $starts . '%';
			$condition->add(' AND (name LIKE(?) OR name LIKE(?))', $starts1, $starts2);
		}
		
		if ($club > 0)
		{
			$condition->add(' AND c.id = ?', $club);
		}
		else if ($city > 0)
		{
			$condition->add(' AND c.city_id = ?', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND i.area_id = (SELECT area_id FROM cities WHERE id = ?)', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND i.country_id = ?', $country);
		}
		
		if ($user > 0)
		{
			$condition->add(' AND c.id IN (SELECT club_id FROM user_clubs WHERE user_id = ?)', $user);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (c.langs & ?) <> 0', $langs);
		}
		
		list($count) = Db::record('club', 'SELECT count(*) FROM clubs c JOIN cities i ON i.id = c.city_id', $condition);
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, c.langs, c.web_site, c.email, c.phone, c.city_id, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ', rules, scoring_id FROM clubs c' . 
				' JOIN cities i ON i.id = c.city_id' .
				' JOIN countries o ON o.id = i.country_id', $condition);
			$query->add(' ORDER BY name');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$clubs = array();
			while ($row = $query->next())
			{
				$club = new stdClass();
				list ($club->id, $club->name, $club->langs, $web, $email, $phone, $club->city_id, $club->city, $club->country, $rules_code, $club->scoring_id) = $row;
				$club->id = (int)$club->id;
				$club->scoring_id = (int)$club->scoring_id;
				$club->rules = rules_code_to_object($rules_code);
				$club->city_id = (int)$club->city_id;
				$club->langs = (int)$club->langs;
				if ($web != NULL)
				{
					$club->web_site = $web;
				}
				if ($email != NULL)
				{
					$club->email = $email;
				}
				if ($phone != NULL)
				{
					$club->phone = $web;
				}
				$clubs[] = $club;
			}
			$this->response['clubs'] = $clubs;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('contains', 'Search pattern. For example: <a href="clubs.php?contains=co"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?contains=co</a> returns clubs containing "co" in their name.', '-');
		$help->request_param('starts', 'Search pattern. For example: <a href="clubs.php?starts=co"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?starts=co</a> returns clubs with names starting with "co".', '-');
		$help->request_param('club', 'Club id or club name. For example: <a href="clubs.php?club=1"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?club=1</a> returns information Vancouver Mafia Club.', '-');
		$help->request_param('city', 'City id. For example: <a href="clubs.php?city=2"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?city=2</a> returns all clubs from Moscow. List of the cities and their ids can be obtained using <a href="cities.php?help"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a>.', '-');
		$help->request_param('area', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="clubs.php?area=2"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?area=2</a> returns all clubs from Moscow and nearby cities like Podolsk, Himki, etc. Though <a href="clubs.php?city=2"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?city=2</a> returns only the clubs from Moscow itself.', '-');
		$help->request_param('country', 'Country id. For example: <a href="clubs.php?country=2"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?country=2</a> returns all clubs from Russia. List of the countries and their ids can be obtained using <a href="countries.php?help"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a>.', '-');
		$help->request_param('user', 'User id. For example: <a href="clubs.php?user=25"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?user=25</a> returns all clubs where Fantomas is a member.', '-');
		$help->request_param('langs', 'Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="clubs.php?langs=1"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?langs=1</a> returns all clubs that support English as their language.', '-');
		$help->request_param('count', 'Returns clubs count instead of the clubs themselves. For example: <a href="clubs.php?contains=an&count"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?contains=an&count</a> returns how many clubs contain "an" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="clubs.php?page=1"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?page=1</a> returns the second page of clubs by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . DEFAULT_PAGE_SIZE . '. For example: <a href="clubs.php?page_size=32"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?page_size=32</a> returns first 32 clubs; <a href="clubs.php?page_size=0"><?php echo PRODUCT_URL; ?>/api/get/clubs.php?page_size=0</a> returns clubs in one page; <a href="clubs.php"><?php echo PRODUCT_URL; ?>/api/get/clubs.php</a> returns first ' . DEFAULT_PAGE_SIZE . ' clubs by alphabet.', '-');

		$param = $help->response_param('clubs', 'The array of clubs. Clubs are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Club id.');
			$param->sub_param('name', 'Club name.');
			$param->sub_param('langs', 'Languages used in the club. A bit combination of: 1 - English; 2 - Russian.');
			$param->sub_param('web_site', 'Subj. Not set if unknown.');
			$param->sub_param('email', 'Subj. Not set if unknown.');
			$param->sub_param('phone', 'Subj. Not set if unknown.');
			$param->sub_param('city_id', 'City id');
			$param->sub_param('city', 'City name using default language for the profile.');
			$param->sub_param('country', 'Country name using default language for the profile.');
			$param->sub_param('rules', 'Game rules used in the club.');
			$param->sub_param('scoring_id', 'Default scoring used in the club.');
		$help->response_param('count', 'Total number of clubs satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Clubs', CURRENT_VERSION);

?>