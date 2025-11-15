<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/security.php';

initiate_session();

try
{
	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	if (!isset($_REQUEST['number']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('number')));
	}
	$number = (int)$_REQUEST['number'];
	
	$player_id = 0;
	if (isset($_REQUEST['player_id']))
	{
		$player_id = (int)$_REQUEST['player_id'];
	}
	
	list($club_id, $name, $flags, $tournament_id, $misc) = Db::record(get_label('event'), 'SELECT club_id, name, flags, tournament_id, misc FROM events WHERE id = ?', $event_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
	if (is_null($misc))
	{
		throw new Exc(get_label('Players mapping is not set for the tournament.'));
	}
	$misc = json_decode($misc);
	if (!isset($misc->seating) || !isset($misc->seating->mapping))
	{
		throw new Exc(get_label('Players mapping is not set for the tournament.'));
	}
	
	if ($number < 0 || $number >= count($misc->seating->mapping))
	{
		throw new Exc(get_label('Invalid player number [0]', $number));
	}

	$player = $misc->seating->mapping[$number];
	$player_name = '#' . ($number + 1);
	if (is_object($player) && isset($player->name))
	{
		$player_name = $player->name;
	}
	
	dialog_title(get_label('Map mafiaratings player to the seat [0]', $number + 1));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . $player_name . ':</td><td>';
	show_user_input('form-user', $player_name, '', get_label('Select player.'), 'onSelect');
	echo '</td></tr>';
	echo '</table>';

?>
	<script>
	var user = null;
	function onSelect(_user)
	{
		user = _user;
	}
	
	function commit(onSuccess)
	{
		if (user != null)
		{
			json.post("api/ops/event.php",
			{
				op: "map_player"
				, user_id: user.id
				, number: <?php echo $number; ?>
				, event_id: <?php echo $event_id; ?>
			},
			onSuccess);
		}
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>