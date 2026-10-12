<?php

require_once '../include/page_base.php';
require_once '../include/security.php';
require_once '../include/series.php';
require_once '../include/user.php';

initiate_session();

// Putting a user on a series or on a league, with the roles they are being given. Which of the two
// it is decides nothing but the title, the roles on offer and where the result is posted - the
// shape is the one form/league_add_manager.php had, with the roles it was missing.
try
{
	if (isset($_REQUEST['series_id']))
	{
		$series_id = (int)$_REQUEST['series_id'];
		list ($league_id, $club_id, $name) = Db::record(get_label('sеriеs'),
			'SELECT league_id, club_id, name FROM series WHERE id = ?', $series_id);
		check_series_permissions(new SeriesOwner($league_id, $club_id), $series_id);
	}
	else if (isset($_REQUEST['league_id']))
	{
		$league_id = (int)$_REQUEST['league_id'];
		list ($name) = Db::record(get_label('league'), 'SELECT name FROM leagues WHERE id = ?', $league_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	}
	else
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('sеriеs')));
	}

	dialog_title(get_label('Add [0] to [1]', get_label('user'), $name));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('User') . ':</td><td>';
	show_user_input('form-user', '', '', get_label('Select user.'), 'onSelect');
	echo '</td></tr>';
	echo '<tr><td></td><td>';
	echo '<input type="checkbox" id="form-manager"> ' . get_label('Manager');
	echo '<br><input type="checkbox" id="form-referee" checked> ' . get_label('Referee');
	// A league has no players and no competitions of its own - only the people running it.
	if (isset($series_id))
	{
		echo '<br><input type="checkbox" id="form-player"> ' . get_label('Player');
	}
	echo '</td></tr>';
	echo '</table>';

?>
	<script>
	var user = null;
	function onSelect(_user)
	{
		user = _user;
	}

	function commit(onSuccess)
	{
		if (user == null)
		{
			return;
		}
		var flags = 0;
		if ($("#form-manager").attr("checked")) flags |= <?php echo USER_PERM_MANAGER; ?>;
		if ($("#form-referee").attr("checked")) flags |= <?php echo USER_PERM_REFEREE; ?>;
<?php
	if (isset($series_id))
	{
?>
		if ($("#form-player").attr("checked")) flags |= <?php echo USER_PERM_PLAYER; ?>;
		json.post("api/ops/series.php",
		{
			op: "add_user"
			, series_id: <?php echo $series_id; ?>
			, user_id: user.id
			, access_flags: flags
		}, onSuccess);
<?php
	}
	else
	{
?>
		json.post("api/ops/league.php",
		{
			op: "add_user"
			, league_id: <?php echo $league_id; ?>
			, user_id: user.id
			, access_flags: flags
		}, onSuccess);
<?php
	}
?>
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
