<?php
require_once 'include/session.php';
require_once 'include/figm_form.php';

// define('A4_MAX_X', 297);
// define('A4_MAX_Y', 210);

initiate_session();

try
{
	$tournament_id = 0;
	if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
	}
	
	if ($tournament_id <= 0)
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('tournament')));
	}
	
	$form = new FigmForm();
	$query = new DbQuery(
		'SELECT g.id, t.name, e.name, g.json, g.canceled, c.timezone, u.name FROM games g' .
		' JOIN events e ON e.id = g.event_id' .
		' JOIN addresses a ON a.id = e.address_id' .
		' JOIN cities c ON c.id = a.city_id' .
		' JOIN users u ON u.id = g.moderator_id' .
		' JOIN tournaments t ON t.id = e.tournament_id' .
		' WHERE t.id = ? AND g.result > 0 ORDER BY g.end_time', $tournament_id);
	while ($row = $query->next())
	{
		list ($game_id, $tournament_name, $event_name, $json, $is_canceled, $timezone, $moder_name) = $row;
		$game = new Game($json);
		$form->add($game, $event_name, $tournament_name, $moder_name, $timezone);
	}
	$form->output();
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<h1>' . get_label('Error') . '</h1><p>' . $e->getMessage() . '</p>';
}


?>
