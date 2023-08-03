<?php

require_once '../include/session.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Delete tournament'));

	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	
	list($tournament_id, $name, $club_id, $flags, $games_count) = Db::record(get_label('tournament'), 'SELECT t.id, t.name, t.club_id, t.flags, count(g.id) FROM tournaments t LEFT OUTER JOIN games g ON g.tournament_id = t.id WHERE t.id = ? GROUP BY t.id', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	$canceled = (($flags & TOURNAMENT_FLAG_CANCELED) != 0);
	if ($games_count > 0)
	{
		echo '<p><b>' . get_label('WARNING!') . '</b> ' . get_label('[0] games were played in this tournament. They will be deleted if you choose to delete this tournament. Are you sure you want to do it?', $games_count) . '</p>';
	}
	else if ($flags & TOURNAMENT_FLAG_CANCELED)
	{
		echo '<p>' . get_label('Are you sure you want to delete the tournament?') . '</p>';
	}
	else
	{
		echo '<p>'.get_label('Do you want to?').'</p>';
		echo '<p><input type="radio" name="option" checked> ' . get_label('cancel the tournament.');
		echo '<br><input type="radio" name="option" id="form-delete"> ' . get_label('delete the tournament completely.');
		echo '</p>';
	}
	
?>
	<script>
	function deleteTournament(onSuccess)
	{
		json.post("api/ops/tournament.php",
		{
			op: 'delete',
			tournament_id: <?php echo $tournament_id; ?>
		},
		onSuccess);
	}
	
	function cancelTournament(onSuccess)
	{
		json.post("api/ops/tournament.php",
		{
			op: 'cancel',
			tournament_id: <?php echo $tournament_id; ?>
		},
		onSuccess);
	}
	
	function commit(onSuccess)
	{
		if ($('#form-delete').length <= 0)
		{
			deleteTournament(onSuccess);
		}
		else if ($("#form-delete").attr("checked"))
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete the tournament?'); ?>", null, null, function() { deleteTournament(onSuccess); } );
		}
		else
		{
			cancelTournament(onSuccess);
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