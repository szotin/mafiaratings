<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/scoring.php';

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
	echo '<tr><td width="200" valign="top">'.get_label('Gaining system name').':</td><td>';
	Names::show_control(new Names(0, get_label('Gaining system name'), NULL, 0, NULL, 'name', false));
	Names::show_menu_over_dialog();
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Copy all rules from') . ':</td><td><select id="form-copy">';
	show_option(0, 0, '');
	// What the owner can use - see get_available_systems().
	show_system_options('gainings', $club_id > 0 ? $club_id : NULL, 0, $club_id <= 0 && $league_id > 0 ? $league_id : NULL);
	echo '</select></td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		var params =
		{
			op: 'create'
			, copy_id: $("#form-copy").val()
			, league_id: <?php echo $league_id; ?>
			, club_id: <?php echo $club_id; ?>
		};
		nameControl.fillRequest(params);
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