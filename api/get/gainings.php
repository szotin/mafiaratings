<?php

require_once '../../include/api.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang_code;
		
		$name_contains = get_optional_param('name_contains');
		$name_starts = get_optional_param('name_starts');
		$gaining_id = (int)get_optional_param('gaining_id', -1);
		$gaining_version = (int)get_optional_param('gaining_version', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		
		$condition = new SQL(' WHERE 1');
		if ($gaining_id > 0)
		{
			$condition->add(' AND s.id = ?', $gaining_id);
		}
		else
		{
			if ($name_contains != '')
			{
				$name_contains = '%' . $name_contains . '%';
				$condition->add(' AND s.name LIKE(?)', $name_contains);
			}
			
			if ($name_starts != '')
			{
				$name_starts1 = '% ' . $name_starts . '%';
				$name_starts2 = $name_starts . '%';
				$condition->add(' AND (s.name LIKE(?) OR s.name LIKE(?))', $name_starts1, $name_starts2);
			}
		
			if ($league_id > 0)
			{
				$condition->add(' AND (s.league_id = ? OR s.league_id IS NULL)', $league_id);
			}
			else
			{
				$condition->add(' AND s.league_id IS NULL');
			}
		}
		
		if ($gaining_version > 0)
		{
			$condition->add(' AND v.version = ?', $gaining_version);
		}
		else if ($gaining_version == 0)
		{
			$condition->add(' AND v.version = (SELECT MAX(v1.version) FROM gaining_versions v1 WHERE v1.gaining_id = s.id)');
		}
		
		list($count) = Db::record('gaining', 'SELECT count(DISTINCT s.id) FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$gainings = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.league_id, v.version, v.gaining FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id', $condition);
		$query->add(' ORDER BY s.name, v.version');
		if ($page_size > 0)
		{
			$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
		}
		
		$this->show_query($query);
		$current_gaining = NULL;
		while ($row = $query->next())
		{
			list ($gaining_id, $gaining_name, $gaining_league_id, $gaining_version, $gaining) = $row;
			if (count($gainings) > 0)
			{
				$current_gaining = $gainings[count($gainings)-1];
				if ($current_gaining->id != $gaining_id)
				{
					$current_gaining = NULL;
				}
			}
			
			if ($current_gaining == NULL)
			{
				$current_gaining = new stdClass();
				$current_gaining->id = (int)$gaining_id;
				$current_gaining->name = $gaining_name;
				if (!is_null($gaining_league_id))
				{
					$current_gaining->league_id = (int)$gaining_league_id;
				}
				$current_gaining->versions = array();
				$gainings[] = $current_gaining;
			}
			
			$v = new stdClass();
			$v->version = (int)$gaining_version;
			$v->rules = json_decode($gaining);
			$current_gaining->versions[] = $v;
		}
		$this->response['gainings'] = $gainings;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="gainings.php?name_contains=wa">' . PRODUCT_URL . '/api/get/gainings.php?name_contains=wa</a> returns gaining systems containing "wa" in their names.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="gainings.php?name_starts=фи">' . PRODUCT_URL . '/api/get/gainings.php?name_starts=фи</a> returns gaining systems with names starting with "фи".', '-');
		$help->request_param('gaining_id', 'gaining system id. For example: <a href="gainings.php?gaining_id=19"><?php echo PRODUCT_URL; ?>/api/get/gainings.php?gaining_id=19</a> returns information about FIIM gaining system.', '-');
		$help->request_param('gaining_version', 'gaining system version. For example: <a href="gainings.php?gaining_id=21&gaining_version=1"><?php echo PRODUCT_URL; ?>/api/get/gainings.php?gaining_id=21&gaining_version=1</a> returns information about VaWaCa gaining system version 1 (current version is 2). When 0, the latest version is returned.', 'all versions are returned');
		$help->request_param('league_id', 'League id. Returns all gaining systems used in this league. For example: <a href="gainings.php?league_id=2"><?php echo PRODUCT_URL; ?>/api/get/gainings.php?league_id=2</a> returns all gaining systems used in American Mafia League.', '-');
		
		$param = $help->response_param('gainings', 'The array of gaining systems.');
			$param->sub_param('id', 'gaining system id.');
			$param->sub_param('name', 'gaining system name.');
			$param->sub_param('league_id', 'League id that this system belongs to. Missing for gaining systems that do not belong to a league.');
			$versions_param = $param->sub_param('versions', 'List of versions of the gaining system.');
				$versions_param->sub_param('version', 'Version number.');
				$versions_param->sub_param('rules', 'gaining rules. Todo: make a detailed description of the format.');
		$help->response_param('count', 'Total number of gaining systems satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Gaining Systems', CURRENT_VERSION);

?>