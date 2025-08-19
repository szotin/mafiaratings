<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/security.php';
require_once '../include/tournament.php';

initiate_session();

try
{
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	
	if (!isset($_REQUEST['stage']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('round')));
	}
	$stage = (int)$_REQUEST['stage'];
	
	if (!isset($_REQUEST['table']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('round')));
	}
	$table = (int)$_REQUEST['table'];
	
	list($club_id, $name, $flags, $tournament_misc) = Db::record(get_label('tournament'), 'SELECT club_id, name, flags, misc FROM tournaments WHERE id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	if (is_null($tournament_misc))
	{
		throw new Exc(get_label('Players mapping is not set for the tournament.'));
	}
	$tournament_misc = json_decode($tournament_misc);
	if (!isset($tournament_misc->imafia) || !isset($tournament_misc->imafia->refs))
	{
		throw new Exc(get_label('Referee mapping is not set for the tournament.'));
	}
	
	if ($stage >= count($tournament_misc->imafia->refs))
	{
		throw new Exc(get_label('Invalid [0]', get_label('round')));
	}
	
	if ($table >= count($tournament_misc->imafia->refs[$stage]))
	{
		throw new Exc(get_label('Invalid [0]', get_label('table')));
	}
	$ref = $tournament_misc->imafia->refs[$stage][$table];
	$ref_name = is_null($ref) ? '' : $ref->name;
	
	if ($stage > 0)
	{
		$round_num = count($tournament_misc->imafia->refs) - $stage;
	}
	else
	{
		$round_num = $stage;
	}
	
	dialog_title(get_label('Set referee for [0], table [1]', get_round_name($round_num), $table + 1));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('Referee') . ':</td><td>';
	show_user_input('form-user', $ref_name, '', get_label('Select player.'), 'onSelect');
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
				op: "map_referee"
				, user_id: user.id
				, stage: <?php echo $stage; ?>
				, table: <?php echo $table; ?>
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