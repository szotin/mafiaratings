<?php
require_once 'include/session.php';
require_once 'include/fiim_form.php';

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
		list ($tournament_name, $event_name, $json, $is_canceled, $timezone, $moder_name) = Db::record(get_label('game'), 
			'SELECT t.name, e.name, g.json, g.is_canceled, c.timezone, nu.name FROM games g' .
			' JOIN events e ON e.id = g.event_id' .
			' JOIN addresses a ON a.id = e.address_id' .
			' JOIN cities c ON c.id = a.city_id' .
			' JOIN users u ON u.id = g.moderator_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
			' WHERE g.id = ?', $game_id);
		
		$game = new Game($json);
	}
	else
	{
		$game = NULL;
		$tournament_name = NULL;
		$event_name = NULL;
		$moder_name = NULL;
		$timezone = NULL;
	}
	
	$form = new FiimForm();
	$form->add($game, $event_name, $tournament_name, $moder_name, $timezone);
	$form->output();
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<h1>' . get_label('Error') . '</h1><p>' . $e->getMessage() . '</p>';
}


?>
