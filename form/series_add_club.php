<?php

require_once '../include/page_base.php';
require_once '../include/user.php';
require_once '../include/series.php';

initiate_session();

try
{
	if (!isset($_REQUEST['series_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('sеriеs')));
	}
	$series_id = (int)$_REQUEST['series_id'];

	list($series_name, $league_id, $club_id) = Db::record(get_label('sеriеs'), 'SELECT name, league_id, club_id FROM series WHERE id = ?', $series_id);
	$owner = new SeriesOwner($league_id, $club_id);
	check_series_permissions($owner, $series_id);

	dialog_title(get_label('Add club'));

	// Everything already on the series is left out: the clubs it invited, and the ones that are on
	// it by ownership and so cannot be invited at all.
	$exclude = array_keys(get_series_permanent_clubs($owner));
	$exclude[] = 0;

	$can_add = false;
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('Club') . ':</td><td><select id="form-club">';
	$query = new DbQuery(
		'SELECT c.id, c.name FROM clubs c' .
		' WHERE (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0' .
		' AND c.id NOT IN (' . implode(',', $exclude) . ')' .
		' AND c.id NOT IN (SELECT club_id FROM series_clubs WHERE series_id = ?)' .
		' ORDER BY c.name', $series_id);
	while ($row = $query->next())
	{
		list($id, $name) = $row;
		show_option((int)$id, 0, $name);
		$can_add = true;
	}
	echo '</select></td></tr>';
	echo '<tr><td colspan="2"><i>' . get_label('The club will be able to enter its tournaments into the series. It gets no rights to manage the series itself.') . '</i></td></tr>';
	echo '</table>';

	if (!$can_add)
	{
		throw new FatalExc(get_label('There is no clubs you can add to [0]', $series_name));
	}

?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/series.php",
		{
			op: "add_club"
			, series_id: <?php echo $series_id; ?>
			, club_id: $("#form-club").val()
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
