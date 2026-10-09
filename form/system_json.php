<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/systems.php';

initiate_session();

// The json behind a scoring system, a gaining system or a scoring normalizer, in one dialog.
// What the three have in common - including the check that a kind is one of them, because
// table and column names cannot be parameterized - is in include/systems.php.
try
{
	$kind = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
	$kind_name = check_system_kind($kind);

	dialog_title(get_label('Edit [0]', $kind_name));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', $kind_name));
	}
	$system_id = (int)$_REQUEST['id'];

	list ($name, $league_id, $club_id, $current_version) = Db::record($kind_name,
		'SELECT name, league_id, club_id, version FROM ' . $kind . 's WHERE id = ?', $system_id);

	if (!is_null($club_id))
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	}
	else if (!is_null($league_id))
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	}
	else
	{
		check_permissions(PERMISSION_ADMIN);
	}

	// Whichever version the editor has open. The json is read after the permission check.
	$versions = get_system_versions($kind, $system_id);
	$version = get_requested_system_version($versions, $current_version);
	list ($system) = Db::record($kind_name,
		'SELECT ' . $kind . ' FROM ' . $kind . '_versions WHERE ' . $kind . '_id = ? AND version = ?',
		$system_id, $version);

	$json = formatted_json(json_decode($system));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="200">' . get_label('Name') . '</td><td>' . $name . '</td></tr>';
	echo '<tr><td>' . get_label('Version') . '</td><td>' . $version . '</td></tr>';
	echo '<tr><td colspan="2"><textarea id="form-json" cols="163" rows="60">' . $json . '</textarea></td></tr>';
	echo '</table>';

?>
	<script>
	// Nothing is saved here. The json goes into the editor behind the dialog, where it can be
	// looked at and changed some more, and that page's Save button is what writes it.
	function commit(onSuccess)
	{
		var json;
		try
		{
			json = JSON.parse($('#form-json').val());
		}
		catch (e)
		{
			dlg.error("<?php echo get_label('This is not valid json.'); ?> " + e.message);
			return;
		}
		applyJson(json);
		onSuccess();
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
