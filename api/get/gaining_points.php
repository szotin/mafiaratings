<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$gaining_id = 0;
		$gaining_version = 0;
		if (isset($_REQUEST['gaining_id']))
		{
			$gaining_id = (int)$_REQUEST['gaining_id'];
			if (isset($_REQUEST['gaining_version']))
			{
				$gaining_version = (int)$_REQUEST['gaining_version'];
			}
			else
			{
				list($gaining_version) = Db::record('gaining', 'SELECT version FROM gainings WHERE id = ?', $gaining_id);
				$gaining_version = (int)$gaining_version;
			}
		}
		else
		{
			throw new Exc('Unknown gaining system. Please specify gaining_id.');
		}
		
		list($gaining) = Db::record('gaining', 'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?;', $gaining_id, $gaining_version);
		$gaining = json_decode($gaining);
		
		$players = 20;
		if (isset($_REQUEST['players']))
		{
			$players = (int)$_REQUEST['players'];
		}
		
		$stars = 1;
		if (isset($_REQUEST['stars']))
		{
			$stars = (int)$_REQUEST['stars'];
		}
		
		$stars = 1;
		if (isset($_REQUEST['stars']))
		{
			$stars = (double)$_REQUEST['stars'];
		}
		
		$players = 20;
		if (isset($_REQUEST['players']))
		{
			$players = (int)$_REQUEST['players'];
		}
		
		$this->response['points'] = get_gaining_points($gaining, $stars, $players);
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('gaining_id', 'Gaining system id.</i> For example: <a href="gaining_points.php?gaining_id=3">/api/get/gaining_points.php?gaining_id=3</a> returns gaining points for a 1 star tournament with 20 players using AML gaining system.');
		$help->request_param('gaining_version', 'Gaining system version.  For example: <a href="gaining_points.php?gaining_id=3&gaining_version=1">/api/get/gaining_points.php?gaining_id=3&gaining_version=1</a> returns gaining points for a 1 star tournament with 20 players using AML gaining system version 1.', '-');
		$help->request_param('stars', 'Number of stars. For example: <a href="gaining_points.php?gaining_id=3&stars=3">/api/get/gaining_points.php?gaining_id=3&stars=3</a> returns gaining points for a 3 star tournament with 20 players using AML gaining system.', '1 star.');
		$help->request_param('players', 'Number of players. For example: <a href="gaining_points.php?gaining_id=3&stars=2&players=40">/api/get/gaining_points.php?gaining_id=3&&stars=2&players=40</a> returns gaining points for a 2 star tournament with 40 players using AML gaining system.', '20 players.');

		$help->response_param('points', 'The array of numbers. Size of the array is number of players. Index 0 is num points for the first place; 1 - for the second; 2 - third; etc...');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Gaining Points', CURRENT_VERSION);

?>