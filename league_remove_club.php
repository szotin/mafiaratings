<?php

require_once 'include/page_base.php';
require_once 'include/user.php';

initiate_session();

try
{
	if (!isset($_REQUEST['league_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = $_REQUEST['league_id'];
	
	if (!isset($_REQUEST['club_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club_id'];
	
	list($league_name) = Db::record(get_label('league'), 'SELECT name FROM leagues WHERE id = ?', $league_id);
	list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
	
	dialog_title(get_label('Resign [0] from [1]', $club_name, $league_name));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td><p>' . get_label('You can send an explanation to [0] managers', $club_name) . ':</p><textarea id="form-message" cols="64" rows="8"></textarea>';
	echo '</td></tr>';
	echo '</table>';

?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/league.php",
		{
			op: "remove_club"
			, league_id: <?php echo $league_id; ?>
			, club_id: <?php echo $club_id; ?>
			, message: $("#form-message").val()
		},
		onSuccess);
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