<?php

require_once '../include/session.php';
require_once '../include/security.php';

initiate_session();

// Copying all the tournament schemas of another league - any league, not only a global one -
// into this one. See op copy of api/ops/tournament_schema.php.
try
{
	if (!isset($_REQUEST['league_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = (int)$_REQUEST['league_id'];
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	dialog_title(get_label('Copy from another league'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">' . get_label('League') . ':</td><td><select id="form-league">';
	$query = new DbQuery(
		'SELECT l.id, l.name, COUNT(s.id) FROM leagues l' .
		' LEFT OUTER JOIN league_tournament_schemas s ON s.league_id = l.id' .
		' WHERE l.id <> ? GROUP BY l.id, l.name ORDER BY l.name', $league_id);
	$selected = 0;
	while ($row = $query->next())
	{
		list($id, $name, $count) = $row;
		if ($selected == 0 && $count > 0)
		{
			$selected = (int)$id;
		}
		show_option((int)$id, $selected, $name . ' (' . (int)$count . ')');
	}
	echo '</select></td></tr>';
	echo '</table>';
	echo '<p><i>' . get_label('Every tournament schema of the league is copied. Where this league already has a schema with the same name, its own one is kept.') . '</i></p>';
?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/tournament_schema.php",
		{
			op: 'copy',
			league_id: <?php echo $league_id; ?>,
			source_league_id: $('#form-league').val(),
		}, onSuccess);
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
