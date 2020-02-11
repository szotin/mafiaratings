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
		
		if ($term == '')
		{
			$query = new DbQuery('SELECT u.id, u.name, NULL FROM users u WHERE (u.flags & ' . USER_FLAG_BANNED . ') = 0');
			if (isset($_REQUEST['event']))
			{
				$query->add(' AND u.id IN (SELECT DISTINCT p.user_id FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = ?)', $_REQUEST['event']);
			}
			else if (isset($_REQUEST['tournament']))
			{
				$query->add(' AND u.id IN (SELECT DISTINCT p.user_id FROM players p JOIN games g ON g.id = p.game_id WHERE g.tournament_id = ?)', $_REQUEST['tournament']);
			}
			else if (isset($_REQUEST['club']))
			{
				$query->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE club_id = ?)', $_REQUEST['club']);
			}
			$query->add(' ORDER BY rating DESC');
		}
		else
		{
			$query = new DbQuery(
				'SELECT id, name, NULL FROM users ' .
					' WHERE name LIKE ? AND (flags & ' . USER_FLAG_BANNED . ') = 0' .
					' UNION' .
					' SELECT DISTINCT u.id, u.name, r.nick_name FROM users u' . 
					' JOIN registrations r ON r.user_id = u.id' .
					' WHERE r.nick_name <> u.name AND (u.flags & ' . USER_FLAG_BANNED . ') = 0 AND r.nick_name LIKE ? ORDER BY name',
				'%' . $term . '%',
				'%' . $term . '%');
		}
		if ($num > 0)
		{
			$query->add(' LIMIT ' . $num);
		}
		
		$player = new stdClass();
		$player->id = 0;
		$player->label = $player->name = $player->nickname = '-';
		$this->response[] = $player;
		
		while ($row = $query->next())
		{
			$player = new stdClass();
			list ($player->id, $player->name, $nickname) = $row;
			$player->id = (int)$player->id;
			if ($nickname != NULL)
			{
				$player->nickname = $nickname;
				$player->label = $player->name . '(' . $nickname . ')';
			}
			else
			{
				$player->label = $player->name;
			}
			$this->response[] = $player;
		}
	}
	
	protected function show_help()
	{
		$this->show_help_title();
		$this->show_help_request_params_head();
?>
		<dt>term</dt>
			<dd>Search string. Only the users with the matching names are returned. For example <a href="user.php?term=al">/api/control/user.php?term=al</a> returns only users with "al" in their name.</dd>
		<dt>num</dt>
			<dd>Number of users to return. Default is 16. For example <a href="user.php?term=al">/api/control/user.php?num=4</a> returns only 4 users.</dd>
		<dt>event</dt>
			<dd>Event id. Only the users who were participating in this event are returned. For example <a href="user.php?event=7927">/api/control/user.php?event=7927</a> users who played on VaWaCa-2017 main round.</dd>
		<dt>tournament</dt>
			<dd>Tournament id. Only the users who were participating in this tournament are returned. For example <a href="user.php?tournament=26">/api/control/user.php?tournament=26</a> users who played on Police Academy tournament.</dd>
		<dt>club</dt>
			<dd>Club id. Only the members of this club are returned.  For example <a href="user.php?club=41">/api/control/user.php?club=41</a> returns only the members of The Black Cat club.</dd>
<?php
	}
}

$page = new ApiPage();
$page->run('User List');

?>