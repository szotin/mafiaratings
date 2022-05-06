<?php
require_once 'include/session.php';
require_once 'include/fiim_form.php';

// define('A4_MAX_X', 297);
// define('A4_MAX_Y', 210);

initiate_session();

try
{
	$event_id = 0;
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
	}
	
	if ($event_id <= 0)
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('event')));
	}
	
	$form = new FiimForm();
	$query = new DbQuery(
		'SELECT g.id, t.name, e.name, g.json, g.is_canceled, c.timezone, u.name FROM games g' .
		' JOIN events e ON e.id = g.event_id' .
		' JOIN addresses a ON a.id = e.address_id' .
		' JOIN cities c ON c.id = a.city_id' .
		' JOIN users u ON u.id = g.moderator_id' .
		' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
		' WHERE e.id = ? AND g.result > 0 ORDER BY g.end_time', $event_id);
	while ($row = $query->next())
	{
		list ($game_id, $tournament_name, $event_name, $json, $is_canceled, $timezone, $referee_name) = $row;
		$game = new Game($json);
		$form->add($game, $event_name, $tournament_name, $referee_name, $timezone);
	}
	$form->output();
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<h1>' . get_label('Error') . '</h1><p>' . $e->getMessage() . '</p>';
}


?>
