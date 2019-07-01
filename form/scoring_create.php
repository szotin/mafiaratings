<?php

require_once '../include/session.php';
require_once '../include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Scoring system'));

	$club_id = -1;
	if (isset($_REQUEST['club']))
	{
		$club_id = $_REQUEST['club'];
	}
	
	if ($club_id > 0)
	{
		if ($_profile == NULL || !$_profile->is_club_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
	}
	else if (!$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="200">'.get_label('Scoring system name').':</td><td><input id="form-name"></td></tr>';
	
	echo '<tr><td>'.get_label('Copy all rules from') . ':</td><td><select id="form-copy">';
	show_option(0, 0, '');
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id IS NULL OR club_id = ? ORDER BY name', $club_id);
	while ($row = $query->next())
	{
		list ($id, $name) = $row;
		show_option($id, 0, $name);
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
			, club_id: <?php echo $club_id; ?>
		};
		json.post("api/ops/scoring.php", params, onSuccess);
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