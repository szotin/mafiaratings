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
		$scoring_id = (int)get_optional_param('scoring_id', -1);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		
		$condition = new SQL(' WHERE 1');
		if ($scoring_id > 0)
		{
			$condition->add(' AND s.id = ?', $scoring_id);
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
		
			if ($club_id > 0)
			{
				$condition->add(' AND (s.club_id = ? OR s.club_id IS NULL)', $club_id);
			}
			else
			{
				$condition->add(' AND s.club_id IS NULL');
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
		
		if ($scoring_version > 0)
		{
			$condition->add(' AND v.version = ?', $scoring_version);
		}
		else if ($scoring_version == 0)
		{
			$condition->add(' AND v.version = (SELECT MAX(v1.version) FROM scoring_versions v1 WHERE v1.scoring_id = s.id)');
		}
		
		list($count) = Db::record('scoring', 'SELECT count(DISTINCT s.id) FROM scoring_versions v JOIN scorings s ON s.id = v.scoring_id', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$scorings = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.club_id, s.league_id, v.version, v.scoring FROM scoring_versions v JOIN scorings s ON s.id = v.scoring_id', $condition);
		$query->add(' ORDER BY s.name, v.version');
		if ($page_size > 0)
		{
			$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
		}
		
		$this->show_query($query);
		$current_scoring = NULL;
		while ($row = $query->next())
		{
			list ($scoring_id, $scoring_name, $scoring_club_id, $scoring_league_id, $scoring_version, $scoring) = $row;
			if (count($scorings) > 0)
			{
				$current_scoring = $scorings[count($scorings)-1];
				if ($current_scoring->id != $scoring_id)
				{
					$current_scoring = NULL;
				}
			}
			
			if ($current_scoring == NULL)
			{
				$current_scoring = new stdClass();
				$current_scoring->id = (int)$scoring_id;
				$current_scoring->name = $scoring_name;
				if (!is_null($scoring_club_id))
				{
					$current_scoring->club_id = (int)$scoring_club_id;
				}
				if (!is_null($scoring_league_id))
				{
					$current_scoring->league_id = (int)$scoring_league_id;
				}
				$current_scoring->versions = array();
				$scorings[] = $current_scoring;
			}
			
			$v = new stdClass();
			$v->version = (int)$scoring_version;
			$v->rules = json_decode($scoring);
			$current_scoring->versions[] = $v;
		}
		$this->response['scorings'] = $scorings;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="scorings.php?name_contains=wa">' . PRODUCT_URL . '/api/get/scorings.php?name_contains=wa</a> returns scoring systems containing "wa" in their names.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="scorings.php?name_starts=фи">' . PRODUCT_URL . '/api/get/scorings.php?name_starts=фи</a> returns scoring systems with names starting with "фи".', '-');
		$help->request_param('scoring_id', 'Scoring system id. For example: <a href="scorings.php?scoring_id=19"><?php echo PRODUCT_URL; ?>/api/get/scorings.php?scoring_id=19</a> returns information about FIGM scoring system.', '-');
		$help->request_param('scoring_version', 'Scoring system version. For example: <a href="scorings.php?scoring_id=21&scoring_version=1"><?php echo PRODUCT_URL; ?>/api/get/scorings.php?scoring_id=21&scoring_version=1</a> returns information about VaWaCa scoring system version 1 (current version is 2). When 0, the latest version is returned.', 'all versions are returned');
		$help->request_param('club_id', 'Club id. Returns all scoring systems used in this club. For example: <a href="scorings.php?club_id=1"><?php echo PRODUCT_URL; ?>/api/get/scorings.php?club_id=1</a> returns all scoring systems used in Vancouver Mafia Club.', '-');
		$help->request_param('league_id', 'League id. Returns all scoring systems used in this league. For example: <a href="scorings.php?league_id=2"><?php echo PRODUCT_URL; ?>/api/get/scorings.php?league_id=2</a> returns all scoring systems used in American Mafia League.', '-');
		
		$param = $help->response_param('scorings', 'The array of scoring systems.');
			$param->sub_param('id', 'Scoring system id.');
			$param->sub_param('name', 'Scoring system name.');
			$param->sub_param('club_id', 'Club id that this system belongs to. Missing for scoring systems that do not belong to a club.');
			$param->sub_param('league_id', 'League id that this system belongs to. Missing for scoring systems that do not belong to a league.');
			$versions_param = $param->sub_param('versions', 'List of versions of the scoring system.');
				$versions_param->sub_param('version', 'Version number.');
				$versions_param->sub_param('rules', 'Scoring rules. Todo: make a detailed description of the format.');
		$help->response_param('count', 'Total number of scoring systems satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Scoring Systems', CURRENT_VERSION);

?>