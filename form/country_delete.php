<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';

initiate_session();

try
{
	dialog_title(get_label('Delete country'));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('city')));
	}
	$id = $_REQUEST['id'];
	
	list($name) = Db::record(get_label('country'), 'SELECT name_' . $_lang_code . ' FROM countries WHERE id = ?', $id);

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="260">'.get_label('Replace [0] with: ', $name).'</td><td>';
	
	$query = new DbQuery('SELECT id, name_' . $_lang_code . ' FROM countries WHERE id <> ? ORDER BY name_' . $_lang_code, $id);
	
	echo '<select id="form-repl">';
	while ($row = $query->next())
	{
		show_option($row[0], -1, $row[1]);
	}
	echo '</select></td></tr>';
	echo '<tr><td colspan="2" align="right"><input type="checkbox" id="form-keep" checked> ' . get_label('keep the deleted city names as possible alternatives to the replacement country name') . '</td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/country.php",
			{
				op: 'delete'
				, country_id: <?php echo $id; ?>
				, repl_id: $("#form-repl").val()
				, keep_name: ($('#form-keep').attr('checked') ? 1 : 0)
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