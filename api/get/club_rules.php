<?php

require_once '../../include/api.php';
require_once '../../include/rules.php';

define('CURRENT_VERSION', 0);
define('VIEW_DEFAULT', 0);
define('VIEW_DETAILED', 1);
define('VIEW_CODES', 2);

class ApiPage extends GetApiPageBase
{
	private function create_rule($row, $view)
	{
		list($name, $code) = $row;
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
		
		$rules = array();
		$rules[] = $this->create_rule(Db::record('club', 'SELECT name, rules FROM clubs WHERE id = ?', $club_id), $view);
		
		$query = new DbQuery('SELECT name, rules FROM club_rules WHERE club_id = ? ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			$rules[] = $this->create_rule($row, $view);
		}
		
		$query = new DbQuery('SELECT l.name, c.rules FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ?', $club_id);
		while ($row = $query->next())
		{
			$rules[] = $this->create_rule($row, $view);
		}
		
		$this->response['rules'] = $rules;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('club_id', 'Club id. For example: <a href="club_rules.php?club_id=1"><?php echo PRODUCT_URL; ?>/api/get/club_rules.php?club_id=1</a> returns the rules of Russian Mafia of Vancouver.');
		$help->request_param('view', 'How to show the rules. Possible values are: "all" and "code".<br>"All" shows all default fields explicitly. <a href="club_rules.php?club_id=1&view=all"><?php echo PRODUCT_URL; ?>/api/get/club_rules.php?club_id=1&view=all</a> detailed view.<br>"Code" shows rules codes only. <a href="club_rules.php?club_id=1&view=code"><?php echo PRODUCT_URL; ?>/api/get/club_rules.php?club_id=1&view=all</a> code view.', 'shows only the fields with the non-default values');
		$param = $help->response_param('rules', 'Array of all rules used in the club. The first rules in the array are default rules used in the club.');
			$param->sub_param('name', 'Rules name');
			$rules_param = $param->sub_param('rules', 'Game rules.');
			api_rules_help($rules_param, true);
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Scores', CURRENT_VERSION);

?>