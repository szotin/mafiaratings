<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';

class ApiPage extends ControlApiPageBase
{
	protected function prepare_response()
	{
		// echo '<pre>';
		// print_r($_REQUEST);
		// echo '</pre>';
	
		$term = '';
		if (isset($_REQUEST['term']))
		{
			$term = $_REQUEST['term'];
		}
		
		$num = 16;
		if (isset($_REQUEST['num']) && is_numeric($_REQUEST['num']))
		{
			$num = $_REQUEST['num'];
		}
		
		if (!isset($_REQUEST['tournament_id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('tournament')));
		}
		$tournament_id = (int)$_REQUEST['tournament_id'];
		
		if ($term == '')
		{
			$query = new DbQuery('SELECT id, name FROM tournament_teams WHERE tournament_id = ? ORDER BY name', $tournament_id);
		}
		else
		{
			$query = new DbQuery('SELECT id, name FROM tournament_teams WHERE tournament_id = ? AND name LIKE ? ORDER BY name', $tournament_id, '%' . $term . '%');
		}
		if ($num > 0)
		{
			$query->add(' LIMIT ' . $num);
		}
		
		while ($row = $query->next())
		{
			$team = new stdClass();
			list ($team->id, $team->label) = $row;
			$team->id = (int)$team->id;
			$this->response[] = $team;
		}
	}
	
	protected function show_help()
	{
		$this->show_help_title();
		$this->show_help_request_params_head();
?>
		<dt>term</dt>
			<dd>Search string. Only the teams with the matching names are returned. For example <a href="team.php?tournament_id=1&term=al">/api/control/team.php?tournament_id=1&term=al</a> returns only teams with "al" in their name.</dd>
		<dt>num</dt>
			<dd>Number of teams to return. Default is 16. For example <a href="team.php?tournament_id=1&term=al">/api/control/team.php?tournament_id=1&num=4</a> returns only 4 teams.</dd>
		<dt>tournament_id (mandatory)</dt>
			<dd>Tournament id. Only the teams of this tournament are returned. For example <a href="team.php?tournament_id=26">/api/control/team.php?tournament_id=26</a> users who played on Police Academy tournament.</dd>
<?php
	}
}

$page = new ApiPage();
$page->run('User List');

?>