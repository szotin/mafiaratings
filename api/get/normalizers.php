<?php

require_once '../../include/api.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$name_contains = get_optional_param('name_contains');
		$name_starts = get_optional_param('name_starts');
		$normalizer_id = (int)get_optional_param('normalizer_id', -1);
		$normalizer_version = (int)get_optional_param('normalizer_version', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		
		$condition = new SQL(' WHERE 1');
		if ($normalizer_id > 0)
		{
			$condition->add(' AND s.id = ?', $normalizer_id);
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
		
		if ($normalizer_version > 0)
		{
			$condition->add(' AND v.version = ?', $normalizer_version);
		}
		else if ($normalizer_version == 0)
		{
			$condition->add(' AND v.version = (SELECT MAX(v1.version) FROM normalizer_versions v1 WHERE v1.normalizer_id = s.id)');
		}
		
		list($count) = Db::record('normalizer', 'SELECT count(DISTINCT s.id) FROM normalizer_versions v JOIN normalizers s ON s.id = v.normalizer_id', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$normalizers = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.club_id, s.league_id, v.version, v.normalizer FROM normalizer_versions v JOIN normalizers s ON s.id = v.normalizer_id', $condition);
		$query->add(' ORDER BY s.name, v.version');
		if ($page_size > 0)
		{
			$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
		}
		
		$this->show_query($query);
		$current_normalizer = NULL;
		while ($row = $query->next())
		{
			list ($normalizer_id, $normalizer_name, $normalizer_club_id, $normalizer_league_id, $normalizer_version, $normalizer) = $row;
			if (count($normalizers) > 0)
			{
				$current_normalizer = $normalizers[count($normalizers)-1];
				if ($current_normalizer->id != $normalizer_id)
				{
					$current_normalizer = NULL;
				}
			}
			
			if ($current_normalizer == NULL)
			{
				$current_normalizer = new stdClass();
				$current_normalizer->id = (int)$normalizer_id;
				$current_normalizer->name = $normalizer_name;
				if (!is_null($normalizer_club_id))
				{
					$current_normalizer->club_id = (int)$normalizer_club_id;
				}
				if (!is_null($normalizer_league_id))
				{
					$current_normalizer->league_id = (int)$normalizer_league_id;
				}
				$current_normalizer->versions = array();
				$normalizers[] = $current_normalizer;
			}
			
			$v = new stdClass();
			$v->version = (int)$normalizer_version;
			$v->rules = json_decode($normalizer);
			$current_normalizer->versions[] = $v;
		}
		$this->response['normalizers'] = $normalizers;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="normalizers.php?name_contains=wa">/api/get/normalizers.php?name_contains=wa</a> returns normalizer systems containing "wa" in their names.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="normalizers.php?name_starts=фи">/api/get/normalizers.php?name_starts=фи</a> returns normalizer systems with names starting with "фи".', '-');
		$help->request_param('normalizer_id', 'normalizer system id. For example: <a href="normalizers.php?normalizer_id=19">/api/get/normalizers.php?normalizer_id=19</a> returns information about FIIM normalizer system.', '-');
		$help->request_param('normalizer_version', 'Scoring normalizer version. For example: <a href="normalizers.php?normalizer_id=21&normalizer_version=1">/api/get/normalizers.php?normalizer_id=21&normalizer_version=1</a> returns information about VaWaCa scoring normalizer version 1 (current version is 2). When 0, the latest version is returned.', 'all versions are returned');
		$help->request_param('club_id', 'Club id. Returns all normalizer systems used in this club. For example: <a href="normalizers.php?club_id=1">/api/get/normalizers.php?club_id=1</a> returns all normalizer systems used in Vancouver Mafia Club.', '-');
		$help->request_param('league_id', 'League id. Returns all normalizer systems used in this league. For example: <a href="normalizers.php?league_id=2">/api/get/normalizers.php?league_id=2</a> returns all normalizer systems used in American Mafia League.', '-');
		
		$param = $help->response_param('normalizers', 'The array of normalizer systems.');
			$param->sub_param('id', 'Scoring normalizer id.');
			$param->sub_param('name', 'Scoring normalizer name.');
			$param->sub_param('club_id', 'Club id that this system belongs to. Missing for scoring normalizer that do not belong to a club.');
			$param->sub_param('league_id', 'League id that this system belongs to. Missing for scoring normalizers that do not belong to a league.');
			$versions_param = $param->sub_param('versions', 'List of versions of the scoring normalizer.');
				$versions_param->sub_param('version', 'Version number.');
				$versions_param->sub_param('rules', 'Normalizer rules. Todo: make a detailed description of the format.');
		$help->response_param('count', 'Total number of scoring systems satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Scoring Normalizers', CURRENT_VERSION);

?>