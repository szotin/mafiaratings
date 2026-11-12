<?php

require_once '../../include/api.php';
require_once '../../include/rules.php';

define('CURRENT_VERSION', 0);
define('VIEW_DEFAULT', 0);
define('VIEW_DETAILED', 1);
define('VIEW_CODES', 2);

class ApiPage extends GetApiPageBase
{
	private function create_rule($name, $code, $view)
	{
		$rules = new stdClass();
		$rules->name = $name;
		switch ($view)
		{
			case VIEW_DETAILED:
				$rules->rules = rules_code_to_object($code, true);
				break;
			case VIEW_CODES:
				$rules->rules = $code;
				break;
			default:
				$rules->rules = rules_code_to_object($code, false);
				break;
		}
		return $rules;
	}
	
	protected function prepare_response()
	{
		$club_id = (int)get_required_param('club_id');
		$view = VIEW_DEFAULT;
		switch (get_optional_param('view'))
		{
			case 'all':
				$view = VIEW_DETAILED;
				break;
			case 'code':
				$view = VIEW_CODES;
				break;
		}
		
		// The same rules, in the same order, as the club is offered them - see
		// get_available_rules(). Each says where it comes from.
		$owners = array();
		$query = new DbQuery('SELECT r.id, r.club_id, r.league_id, r.name FROM rules r LEFT OUTER JOIN league_clubs lc ON lc.league_id = r.league_id AND lc.club_id = ? WHERE r.club_id = ? OR (r.club_id IS NULL AND r.league_id IS NULL) OR lc.club_id IS NOT NULL', $club_id, $club_id);
		while ($row = $query->next())
		{
			$owners['r' . $row[0]] = $row;
		}
		
		$rules = array();
		foreach (get_available_rules($club_id) as $r)
		{
			$rule = $this->create_rule($r->name, $r->rules, $view);
			if ($r->id == 'club')
			{
				$rule->club_id = $club_id;
				$rule->default = true;
			}
			else if ($r->id[0] == 'l')
			{
				$rule->league_id = (int)substr($r->id, 1);
				$rule->default = true;
			}
			else if (isset($owners[$r->id]))
			{
				list($rules_id, $owner_club_id, $owner_league_id, $rules_name) = $owners[$r->id];
				$rule->rules_id = (int)$rules_id;
				if ($owner_club_id != NULL)
				{
					$rule->club_id = (int)$owner_club_id;
				}
				else if ($owner_league_id != NULL)
				{
					$rule->league_id = (int)$owner_league_id;
				}
				else if ($rules_name == '')
				{
					$rule->default = true;
				}
			}
			$rules[] = $rule;
		}
		
		$this->response['rules'] = $rules;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('club_id', 'Club id. For example: <a href="club_rules.php?club_id=1">/api/get/club_rules.php?club_id=1</a> returns the rules of Russian Mafia of Vancouver.');
		$help->request_param('view', 'How to show the rules. Possible values are: "all" and "code".<br>"All" shows all default fields explicitly. <a href="club_rules.php?club_id=1&view=all">/api/get/club_rules.php?club_id=1&view=all</a> detailed view.<br>"Code" shows rules codes only. <a href="club_rules.php?club_id=1&view=code">/api/get/club_rules.php?club_id=1&view=all</a> code view.', 'shows only the fields with the non-default values');
		$param = $help->response_param('rules', 'Array of all rules the club can play by, in the order it is offered them. The first are the default rules of the club. Then its other rules and the rules of the site, by name. Then, by name, the rules of its leagues and the default rules of the global leagues - each of them a filter, given here as the default rules of the club put through it.');
			$param->sub_param('name', 'Rules name');
			$param->sub_param('rules_id', 'Id of the named rules. Missing for the default rules of the club and of a league, which are kept with the club and the league themselves.', 'they are default rules of the club or of a league');
			$param->sub_param('club_id', 'The club these rules belong to.', 'they do not belong to a club');
			$param->sub_param('league_id', 'The league these rules belong to.', 'they do not belong to a league');
			$param->sub_param('default', 'True for the default rules of their owner: of the club, of a league or, when they belong to neither, of the site.', 'they are not default rules');
			$rules_param = $param->sub_param('rules', 'Game rules.');
			api_rules_help($rules_param, true);
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Scores', CURRENT_VERSION);

?>