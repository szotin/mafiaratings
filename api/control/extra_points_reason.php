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
		
		if (empty($term))
		{
			$query = new DbQuery('SELECT reason, count(*) c FROM event_extra_points GROUP BY reason ORDER BY c DESC, reason');
		}
		else
		{
			$query = new DbQuery('SELECT reason, count(*) c FROM event_extra_points WHERE reason LIKE ? GROUP BY reason ORDER BY c DESC, reason', '%' . $term . '%');
		}
		if ($num > 0)
		{
			$query->add(' LIMIT ' . $num);
		}
		
		while ($row = $query->next())
		{
			list ($reason) = $row;
			$this->response[] = $reason;
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
			<dd>Event id. Only the users who were participating in this event are returned. For example <a href="user.php?event=7927">/api/control/user.php?event=7927</a> users who played on VaWaCa-2017 tournament.</dd>
		<dt>club</dt>
			<dd>Club id. Only the members of this club are returned.  For example <a href="user.php?club=41">/api/control/user.php?club=41</a> returns only the members of The Black Cat club.</dd>
<?php
	}
}

$page = new ApiPage();
$page->run('User List');

?>