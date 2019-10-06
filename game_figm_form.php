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
		list ($round_name, $event_name, $game_log, $is_canceled, $timezone, $moder_name) = Db::record(get_label('game'), 
			'SELECT r.name, e.name, g.log, g.canceled, c.timezone, u.name FROM games g' .
			' JOIN events e ON e.id = g.event_id' .
			' JOIN addresses a ON a.id = e.address_id' .
			' JOIN cities c ON c.id = a.city_id' .
			' JOIN users u ON u.id = g.moderator_id' .
			' LEFT OUTER JOIN rounds r ON r.event_id = g.event_id AND r.num = g.round_num' .
			' WHERE g.id = ?', $game_id);
		
		$gs = new GameState();
		$gs->init_existing($game_id, $game_log, $is_canceled);
	}
	else
	{
		$gs = NULL;
		$round_name = NULL;
		$event_name = NULL;
		$moder_name = NULL;
		$timezone = NULL;
	}
	
	$form = new FigmForm();
	$form->add($gs, $round_name, $event_name, $moder_name, $timezone);
	$form->output();
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<h1>' . get_label('Error') . '</h1><p>' . $e->getMessage() . '</p>';
}


?>
