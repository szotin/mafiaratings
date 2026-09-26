<?php

require_once '../include/session.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Gaining system'));

	$league_id = -1;
	if (isset($_REQUEST['league']))
	{
		$league_id = (int)$_REQUEST['league'];
	}

	$club_id = -1;
	if (isset($_REQUEST['club']))
	{
		$club_id = (int)$_REQUEST['club'];
	}

	if ($club_id > 0)
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	}
	else if ($league_id > 0)
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	}
	else
	{
		check_permissions(PERMISSION_ADMIN);
	}

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="200">'.get_label('Gaining system name').':</td><td><input id="form-name"></td></tr>';

	echo '<tr><td>'.get_label('Copy all rules from') . ':</td><td><select id="form-copy">';
	show_option(0, 0, '');
	$query = new DbQuery('SELECT id, name FROM gainings WHERE (league_id IS NULL AND club_id IS NULL) OR league_id = ? OR club_id = ? ORDER BY name', $league_id, $club_id);
	while ($row = $query->next())
	{
		list ($id, $name) = $row;
		show_option((int)$id, 0, $name);
	}
	echo '</td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		var params =
		{
			op: 'create'
			, name: $("#form-name").val()
			, copy_id: $("#form-copy").val()
			, league_id: <?php echo $league_id; ?>
			, club_id: <?php echo $club_id; ?>
		};
		json.post("api/ops/gaining.php", params, onSuccess);
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