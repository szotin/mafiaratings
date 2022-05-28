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
		global $_lang_code;
		
		$name_contains = get_optional_param('name_contains');
		$name_starts = get_optional_param('name_starts');
		$league_id = (int)get_optional_param('league_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$langs = (int)get_optional_param('langs', 0);
		$scoring_id = (int)get_optional_param('scoring_id', -1);
		$rules_code = get_optional_param('rules_code');
		$lod = (int)get_optional_param('lod', 0);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		
		$condition = new SQL(' WHERE (l.flags & ?) = 0', LEAGUE_FLAG_RETIRED);
		if ($name_contains != '')
		{
			$name_contains = '%' . $name_contains . '%';
			$condition->add(' AND l.name LIKE(?)', $name_contains);
		}
		
		if ($name_starts != '')
		{
			$name_starts1 = '% ' . $name_starts . '%';
			$name_starts2 = $name_starts . '%';
			$condition->add(' AND (l.name LIKE(?) OR l.name LIKE(?))', $name_starts1, $name_starts2);
		}
		
		if ($league_id > 0)
		{
			$condition->add(' AND l.id = ?', $league_id);
		}
		
		if ($club_id > 0)
		{
			$condition->add(' AND l.id IN (SELECT league_id FROM league_clubs WHERE club_id = ?)', $club_id);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (l.langs & ?) <> 0', $langs);
		}
		
		if ($scoring_id > 0)
		{
			$condition->add(' AND l.scoring_id = ?', $scoring_id);
		}

		if (empty($rules_code))
		{
			list($count) = Db::record('league', 'SELECT count(*) FROM leagues l', $condition);
			$count = (int)$count;
		}
		else
		{
			$count = 0;
			$query = new DbQuery('SELECT l.rules FROM leagues l', $condition);
			while ($row = $query->next())
			{
				list($rules) = $row;
				$rules_filter = json_decode($rules);
				if (are_rules_allowed($rules_code, $rules_filter))
				{
					++$count;
				}
			}
		}
		$this->response['count'] = $count;
		if ($count_only)
		{
			return;
		}
		
		$leagues = array();
		if ($lod >= 1)
		{
			$query = new DbQuery(
				'SELECT l.id, l.name, l.flags, l.langs, l.web_site, l.email, l.phone, l.rules, l.scoring_id FROM leagues l', $condition);
			$query->add(' ORDER BY l.name');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				list ($id, $name, $flags, $langs, $web, $email, $phone, $rules, $scoring_id) = $row;
				$rules_filter = json_decode($rules);
				if (!are_rules_allowed($rules_code, $rules_filter))
				{
					continue;
				}
				
				$league = new stdClass();
				$league->id = (int)$id;
				$league->name = $name;
				$league->langs = (int)$langs;
				$league->scoring_id = (int)$scoring_id;
				$league->rules = $rules_filter;
				if ($web != NULL)
				{
					$league->web_site = $web;
				}
				if ($email != NULL)
				{
					$league->email = $email;
				}
				if ($phone != NULL)
				{
					$league->phone = $web;
				}
				
				$server_url = get_server_url() . '/';
				$league_pic = new Picture(LEAGUE_PICTURE);
				$league_pic->set($league->id, $league->name, $flags);
				$league->icon = $server_url . $league_pic->url(ICONS_DIR);
				$league->picture = $server_url . $league_pic->url(TNAILS_DIR);
				$leagues[] = $league;
			}
		}
		else
		{
			$query = new DbQuery(
				'SELECT l.id, l.name, l.langs, l.web_site, l.email, l.phone, rules, scoring_id FROM leagues l', $condition);
			$query->add(' ORDER BY l.name');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				list ($id, $name, $langs, $web, $email, $phone, $rules, $scoring_id) = $row;
				$rules_filter = json_decode($rules);
				if (!are_rules_allowed($rules_code, $rules_filter))
				{
					continue;
				}
				
				$league = new stdClass();
				list ($league->id, $league->name, $league->langs, $web, $email, $phone, $rules, $league->scoring_id) = $row;
				$league->id = (int)$id;
				$league->name = $name;
				$league->langs = (int)$langs;
				$league->scoring_id = (int)$scoring_id;
				$league->rules = $rules_filter;
				if ($web != NULL)
				{
					$league->web_site = $web;
				}
				if ($email != NULL)
				{
					$league->email = $email;
				}
				if ($phone != NULL)
				{
					$league->phone = $web;
				}
				$leagues[] = $league;
			}
		}
		$this->response['count'] = $count;
		$this->response['leagues'] = $leagues;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="leagues.php?name_contains=am">' . PRODUCT_URL . '/api/get/leagues.php?name_contains=am</a> returns leagues containing "co" in their name.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="leagues.php?name_starts=am">' . PRODUCT_URL . '/api/get/leagues.php?name_starts=am</a> returns leagues with names starting with "co".', '-');
		$help->request_param('league_id', 'League id. For example: <a href="leagues.php?league_id=2"><?php echo PRODUCT_URL; ?>/api/get/leagues.php?league_id=2</a> returns information about American Mafia League.', '-');
		$help->request_param('club_id', 'Club id. For example: <a href="leagues.php?club_id=1"><?php echo PRODUCT_URL; ?>/api/get/leagues.php?club_id=1</a> returns all leagues that Vancouver Mafia Club belongs to.', '-');
		$help->request_param('langs', 'Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="leagues.php?langs=1"><?php echo PRODUCT_URL; ?>/api/get/leagues.php?langs=1</a> returns all leagues that support English as their language.', '-');
		$help->request_param('scoring_id', 'Scoring id. For example: <a href="leagues.php?scoring_id=10">' . PRODUCT_URL . '/api/get/leagues.php?scoring_id=10</a> returns all leagues using VaWaCa scoring.', '-');
		$help->request_param('scoring_id', 'Scoring id. For example: <a href="leagues.php?scoring_id=10">' . PRODUCT_URL . '/api/get/leagues.php?scoring_id=10</a> returns all leagues using VaWaCa scoring.', '-');
		$help->request_param('rules_code', 'Rules code. For example: <a href="leagues.php?rules_code=00100000000100200300000120000">' . PRODUCT_URL . '/api/get/leagues.php?rules_code=00100000000100200300000120000</a> returns all leagues where the rules with the code 00100000000100200300000120000 are allowed. Please check <a href="rules.php?help">' . PRODUCT_URL . '/api/get/rules.php?help</a> for the meaning of rules codes and getting rules list.', '-');
		$help->request_param('count', 'Returns leagues count instead of the leagues themselves. For example: <a href="leagues.php?contains=an&count"><?php echo PRODUCT_URL; ?>/api/get/leagues.php?contains=an&count</a> returns how many leagues contain "an" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="leagues.php?page=1"><?php echo PRODUCT_URL; ?>/api/get/leagues.php?page=1</a> returns the second page of leagues by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="leagues.php?page_size=32"><?php echo PRODUCT_URL; ?>/api/get/leagues.php?page_size=32</a> returns first 32 leagues; <a href="leagues.php?page_size=0"><?php echo PRODUCT_URL; ?>/api/get/leagues.php?page_size=0</a> returns leagues in one page; <a href="leagues.php"><?php echo PRODUCT_URL; ?>/api/get/leagues.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' leagues by alphabet.', '-');

		$param = $help->response_param('leagues', 'The array of leagues. Leagues are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'League id.');
			$param->sub_param('name', 'League name.');
			$param->sub_param('icon', 'League icon URL.', 1);
			$param->sub_param('picture', 'League picture URL.', 1);
			$param->sub_param('langs', 'Languages used in the league. A bit combination of: 1 - English; 2 - Russian.');
			$param->sub_param('web_site', 'Subj. Not set if unknown.');
			$param->sub_param('email', 'Subj.', 'unknown');
			$param->sub_param('phone', 'Subj.', 'unknown');
			$param->sub_param('scoring_id', 'Default scoring system used in the league.');
			api_rules_filter_help($param->sub_param('rules', 'Game rules filter. Specifies what rules are allowed in the league. Example: { "split_on_four": true, "extra_points": ["fiim", "maf-club"] } - linching 2 players on 4 must be allowed; extra points assignment is allowed in ФИИМ or maf-club styles, but no others.'));
		$help->response_param('count', 'Total number of leagues satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Leagues', CURRENT_VERSION);

?>