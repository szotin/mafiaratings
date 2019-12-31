<?php
require_once 'include/session.php';
require_once 'include/figm_form.php';

// define('A4_MAX_X', 297);
// define('A4_MAX_Y', 210);

initiate_session();

try
{
	$game_id = 0;
	if (isset($_REQUEST['game_id']))
	{
		$game_id = (int)$_REQUEST['game_id'];
	}
	
	if ($game_id > 0)
	{
		list ($tournament_name, $event_name, $game_log, $is_canceled, $timezone, $moder_name) = Db::record(get_label('game'), 
			'SELECT t.name, e.name, g.log, g.canceled, c.timezone, u.name FROM games g' .
			' JOIN events e ON e.id = g.event_id' .
			' JOIN addresses a ON a.id = e.address_id' .
			' JOIN cities c ON c.id = a.city_id' .
			' JOIN users u ON u.id = g.moderator_id' .
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
			' WHERE g.id = ?', $game_id);
		
		$gs = new GameState();
		$gs->init_existing($game_id, $game_log, $is_canceled);
	}
	else
	{
		$gs = NULL;
		$tournament_name = NULL;
		$event_name = NULL;
		$moder_name = NULL;
		$timezone = NULL;
	}
	
	$form = new FigmForm();
	$form->add($gs, $event_name, $tournament_name, $moder_name, $timezone);
	$form->output();
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<h1>' . get_label('Error') . '</h1><p>' . $e->getMessage() . '</p>';
}


?>
