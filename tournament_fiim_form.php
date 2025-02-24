<?php
require_once 'include/session.php';
require_once 'include/fiim_form.php';

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
	
	$form = new FiimForm();
	$query = new DbQuery(
		'SELECT g.id, t.name, e.name, g.json, g.is_canceled, c.timezone, nu.name, g.feature_flags FROM games g' .
		' JOIN events e ON e.id = g.event_id' .
		' JOIN addresses a ON a.id = e.address_id' .
		' JOIN cities c ON c.id = a.city_id' .
		' JOIN users u ON u.id = g.moderator_id' .
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' JOIN tournaments t ON t.id = e.tournament_id' .
		' WHERE t.id = ? AND g.result > 0 ORDER BY g.end_time', $tournament_id);
	while ($row = $query->next())
	{
		list ($game_id, $tournament_name, $event_name, $json, $is_canceled, $timezone, $moder_name, $feature_flags) = $row;
		$game = new Game($json, $feature_flags);
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
