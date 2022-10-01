<?php

require_once '../include/session.php';
require_once '../include/gaining.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Edit gaining system'));
	if (!isset($_REQUEST['gaining_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('gaining system')));
	}
	$gaining_id = $_REQUEST['gaining_id'];

	list ($name, $league_id, $gaining, $version) = Db::record(get_label('gaining system'), 'SELECT g.name, g.league_id, v.gaining, g.version FROM gainings g JOIN gaining_versions v ON v.gaining_id = g.id AND v.version = g.version WHERE g.id = ?', $gaining_id);
	if (is_null($league_id))
	{
		check_permissions(PERMISSION_ADMIN);
	}
	else
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	}
	
	$json = formatted_json(json_decode($gaining));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="200">' . get_label('Name') . '</td><td>' . $name . '</td></tr>';
	echo '<tr><td>' . get_label('Current version') . '</td><td>' . $version . '</td></tr>';
	echo '<tr><td colspan="2"><textarea id="form-json" cols="163" rows="60">' . $json . '</textarea></td></tr>';
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/gaining.php",
		{
			op: "change"
			, gaining_id: <?php echo $gaining_id; ?>
			, gaining: $('#form-json').val()
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