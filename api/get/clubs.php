<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/rules.php';
require_once '../../include/picture.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang;
		
		$name_contains = get_optional_param('name_contains');
		$name_starts = get_optional_param('name_starts');
		$club_id = (int)get_optional_param('club_id', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$city_id = (int)get_optional_param('city_id', -1);
		$area_id = (int)get_optional_param('area_id', -1);
		$country_id = (int)get_optional_param('country_id', -1);
		$user_id = (int)get_optional_param('user_id', -1);
		$langs = (int)get_optional_param('langs', 0);
		$rules_code = get_optional_param('rules_code');
		$scoring_id = (int)get_optional_param('scoring_id', -1);
		$lod = (int)get_optional_param('lod', 0);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		$lang = (int)get_optional_param('lang', $_lang);
		if (!is_valid_lang($lang))
		{
			$lang = $_lang;
		}
		
		$condition = new SQL(' WHERE (c.flags & ?) = 0', CLUB_FLAG_RETIRED);
		if ($name_contains != '')
		{
			$name_contains = '%' . $name_contains . '%';
			$condition->add(' AND name LIKE(?)', $name_contains);
		}
		
		if ($name_starts != '')
		{
			$name_starts1 = '% ' . $name_starts . '%';
			$name_starts2 = $name_starts . '%';
			$condition->add(' AND (name LIKE(?) OR name LIKE(?))', $name_starts1, $name_starts2);
		}
		
		if ($club_id > 0)
		{
			$condition->add(' AND c.id = ?', $club_id);
		}
		
		if ($league_id > 0)
		{
			$condition->add(' AND c.id IN (SELECT club_id FROM league_clubs WHERE league_id = ?)', $league_id);
		}
		
		if ($city_id > 0)
		{
			$condition->add(' AND c.city_id = ?', $city_id);
		}
		
		if ($area_id > 0)
		{
			$condition->add(' AND i.area_id = (SELECT area_id FROM cities WHERE id = ?)', $area_id);
		}
		
		if ($country_id > 0)
		{
			$condition->add(' AND i.country_id = ?', $country_id);
		}
		
		if ($user_id > 0)
		{
			$condition->add(' AND c.id IN (SELECT club_id FROM club_users WHERE user_id = ?)', $user_id);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (c.langs & ?) <> 0', $langs);
		}
		
		if (!empty($rules_code))
		{
			$condition->add(' AND c.rules = ?', $rules_code);
		}
		
		if ($scoring_id > 0)
		{
			$condition->add(' AND c.scoring_id = ?', $scoring_id);
		}

		list($count) = Db::record('club', 'SELECT count(*) FROM clubs c JOIN cities i ON i.id = c.city_id', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$clubs = array();
		if ($lod >= 1)
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, c.flags, c.langs, c.web_site, c.email, c.phone, c.city_id, ni.name, no.name, c.rules, c.scoring_id FROM clubs c' . 
				' JOIN cities i ON i.id = c.city_id' .
				' JOIN countries o ON o.id = i.country_id' .
				' JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0', $lang, $lang, $condition);
			$query->add(' ORDER BY name');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				$club = new stdClass();
				list ($club->id, $club->name, $flags, $club->langs, $web, $email, $phone, $club->city_id, $club->city, $club->country, $rules_code, $club->scoring_id) = $row;
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
				
				$server_url = get_server_url() . '/';
				$club_pic = new Picture(CLUB_PICTURE);
				$club_pic->set($club->id, $club->name, $flags);
				$club->icon = $server_url . $club_pic->url(ICONS_DIR);
				$club->picture = $server_url . $club_pic->url(TNAILS_DIR);
				$clubs[] = $club;
			}
		}
		else
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, c.langs, c.web_site, c.email, c.phone, c.city_id, rules, scoring_id FROM clubs c' . 
				' JOIN cities i ON i.id = c.city_id', $condition);
			$query->add(' ORDER BY name');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				$club = new stdClass();
				list ($club->id, $club->name, $club->langs, $web, $email, $phone, $club->city_id, $rules_code, $club->scoring_id) = $row;
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
		}
		$this->response['clubs'] = $clubs;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="clubs.php?name_contains=co">' . PRODUCT_URL . '/api/get/clubs.php?name_contains=co</a> returns clubs containing "co" in their name.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="clubs.php?name_starts=ga">' . PRODUCT_URL . '/api/get/clubs.php?name_starts=ga</a> returns clubs with names starting with "ga".', '-');
		$help->request_param('club_id', 'Club id. For example: <a href="clubs.php?club_id=1">/api/get/clubs.php?club_id=1</a> returns information Vancouver Mafia Club.', '-');
		$help->request_param('league_id', 'League id. For example: <a href="clubs.php?league_id=2">/api/get/clubs.php?league_id=2</a> returns all clubs of American Mafia League.', '-');
		$help->request_param('city_id', 'City id. For example: <a href="clubs.php?city_id=2">/api/get/clubs.php?city_id=2</a> returns all clubs from Moscow. List of the cities and their ids can be obtained using <a href="cities.php?help">/api/get/cities.php</a>.', '-');
		$help->request_param('area_id', 'City id. The difference vs city is that when area is set, the games from all nearby cities are also returned. For example: <a href="clubs.php?area_id=2">/api/get/clubs.php?area_id=2</a> returns all clubs from Moscow and nearby cities like Podolsk, Himki, etc. Though <a href="clubs.php?city_id=2">/api/get/clubs.php?city_id=2</a> returns only the clubs from Moscow itself.', '-');
		$help->request_param('country_id', 'Country id. For example: <a href="clubs.php?country_id=2">/api/get/clubs.php?country_id=2</a> returns all clubs from Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">/api/get/countries.php</a>.', '-');
		$help->request_param('user_id', 'User id. For example: <a href="clubs.php?user_id=25">/api/get/clubs.php?user_id=25</a> returns all clubs where Fantomas is a member.', '-');
		$help->request_param('langs', 'Languages filter. Bit combination of language ids. ' . LANG_ALL . ' - means any language (this is a default value). For example: <a href="clubs.php?langs=1">/api/get/clubs.php?langs=1</a> returns all clubs that support English as their language.' . valid_langs_help(), '-');
		$help->request_param('rules_code', 'Rules code. For example: <a href="clubs.php?rules_code=00000000000000010200000000000">' . PRODUCT_URL . '/api/get/clubs.php?rules_code=00000000000000010200000000000</a> returns all clubs using the rules with the code 00000000000000010200000000000. Please check <a href="rules.php?help">' . PRODUCT_URL . '/api/get/rules.php?help</a> for the meaning of rules codes and getting rules list.', '-');
		$help->request_param('scoring_id', 'Scoring id. For example: <a href="clubs.php?scoring_id=21">' . PRODUCT_URL . '/api/get/clubs.php?scoring_id=21</a> returns all clubs using VaWaCa scoring.', '-');
		$help->request_param('lang', 'Language id for returned names. For example: <a href="clubs.php?lang=2">/api/get/clubs.php?lang=2</a> returns club names in Russian.' . valid_langs_help(), 'default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.');
		$help->request_param('count', 'Returns clubs count instead of the clubs themselves. For example: <a href="clubs.php?contains=an&count">/api/get/clubs.php?contains=an&count</a> returns how many clubs contain "an" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="clubs.php?page=1">/api/get/clubs.php?page=1</a> returns the second page of clubs by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="clubs.php?page_size=32">/api/get/clubs.php?page_size=32</a> returns first 32 clubs; <a href="clubs.php?page_size=0">/api/get/clubs.php?page_size=0</a> returns clubs in one page; <a href="clubs.php">/api/get/clubs.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' clubs by alphabet.', '-');

		$param = $help->response_param('clubs', 'The array of clubs. Clubs are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Club id.');
			$param->sub_param('name', 'Club name.');
			$param->sub_param('icon', 'Club icon URL.', 1);
			$param->sub_param('picture', 'Club picture URL.', 1);
			$param->sub_param('langs', 'A bit combination of languages used in the club.' . valid_langs_help());
			$param->sub_param('web_site', 'Subj. Not set if unknown.');
			$param->sub_param('email', 'Subj.', 'unknown');
			$param->sub_param('phone', 'Subj.', 'unknown');
			$param->sub_param('city_id', 'City id');
			$param->sub_param('city', 'City name using default language for the profile.', 1);
			$param->sub_param('country', 'Country name using default language for the profile.', 1);
			$param->sub_param('scoring_id', 'Default scoring system used in the club.');
			api_rules_help($param->sub_param('rules', 'Game rules used in the club.'), true);
		$help->response_param('count', 'Total number of clubs satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Clubs', CURRENT_VERSION);

?>