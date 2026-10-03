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

	dialog_title(get_label('Transfer [0]', $series_name));

	// Any open league or club can be given the series, including ones the person does not manage -
	// that is what giving it away means. Which of them they do manage decides whether the warning
	// below shows, so it is collected here and read by the script.
	$managed = array();

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('New owner') . ':</td><td><select id="form-owner" onchange="onOwnerChange()">';

	echo '<optgroup label="' . get_label('Leagues') . '">';
	$query = new DbQuery('SELECT id, name FROM leagues WHERE (flags & ' . LEAGUE_FLAG_CLOSED . ') = 0 ORDER BY name');
	while ($row = $query->next())
	{
		list($id, $name) = $row;
		$id = (int)$id;
		if ($owner->is_league() && $owner->id == $id)
		{
			continue;
		}
		echo '<option value="l' . $id . '">' . $name . '</option>';
		if (is_permitted(PERMISSION_LEAGUE_MANAGER, $id))
		{
			$managed[] = '"l' . $id . '": true';
		}
	}
	echo '</optgroup>';

	echo '<optgroup label="' . get_label('Clubs') . '">';
	$query = new DbQuery('SELECT id, name FROM clubs WHERE (flags & ' . CLUB_FLAG_CLOSED . ') = 0 ORDER BY name');
	while ($row = $query->next())
	{
		list($id, $name) = $row;
		$id = (int)$id;
		if (!$owner->is_league() && $owner->id == $id)
		{
			continue;
		}
		echo '<option value="c' . $id . '">' . $name . '</option>';
		if (is_permitted(PERMISSION_CLUB_MANAGER, $id))
		{
			$managed[] = '"c' . $id . '": true';
		}
	}
	echo '</optgroup>';

	echo '</select></td></tr>';
	echo '<tr><td colspan="2"><p id="form-warning" style="display: none;"><b>' .
		get_label('You do not manage the new owner, so you will lose the right to manage this series.') . '</b></p></td></tr>';
	echo '</table>';

?>
	<script>
	var managedOwners = { <?php echo implode(', ', $managed); ?> };

	function onOwnerChange()
	{
		$('#form-warning').css('display', managedOwners[$('#form-owner').val()] ? 'none' : '');
	}
	onOwnerChange();

	function commit(onSuccess)
	{
		var owner = $('#form-owner').val();
		var params =
		{
			op: "transfer"
			, series_id: <?php echo $series_id; ?>
		};
		if (owner.charAt(0) == 'l')
		{
			params['league_id'] = owner.substr(1);
		}
		else
		{
			params['club_id'] = owner.substr(1);
		}
		json.post("api/ops/series.php", params, onSuccess);
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
