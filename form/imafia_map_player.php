<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/security.php';

initiate_session();

try
{
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	
	if (!isset($_REQUEST['imafia_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('player')));
	}
	$imafia_id = (int)$_REQUEST['imafia_id'];
	
	$player_id = 0;
	if (isset($_REQUEST['player_id']))
	{
		$player_id = (int)$_REQUEST['player_id'];
	}
	
	list($club_id, $name, $flags, $tournament_misc) = Db::record(get_label('tournament'), 'SELECT club_id, name, flags, misc FROM tournaments WHERE id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	if (is_null($tournament_misc))
	{
		throw new Exc(get_label('Players mapping is not set for the tournament.'));
	}
	$tournament_misc = json_decode($tournament_misc);
	if (!isset($tournament_misc->imafia) || !isset($tournament_misc->imafia->players))
	{
		throw new Exc(get_label('Players mapping is not set for the tournament.'));
	}
	
	$player = NULL;
	foreach ($tournament_misc->imafia->players as $p)
	{
		if ($p->imafia_id == $imafia_id)
		{
			$player = $p;
			break;
		}
	}
	if (is_null($player))
	{
		throw new Exc(get_label('Unknown [0]', get_label('player')));
	}
	
	dialog_title(get_label('Map mafiaratings player to [1] player [0]', $player->imafia_name, 'iMafia'));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . $player->imafia_name . ':</td><td>';
	show_user_input('form-user', $player->imafia_name, '', get_label('Select player.'), 'onSelect');
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
			json.post("api/ops/imafia.php",
			{
				op: "map_player"
				, user_id: user.id
				, imafia_id: <?php echo $imafia_id; ?>
				, tournament_id: <?php echo $tournament_id; ?>
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