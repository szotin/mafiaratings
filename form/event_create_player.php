<?php

require_once '../include/session.php';
require_once '../include/security.php';

define('COLUMN_COUNT', 6);
define('MAX_ROW_COUNT', 5);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

initiate_session();

try
{
	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	$num = 0;
	if (isset($_REQUEST['num']))
	{
		$num = (int)$_REQUEST['num'];
	}
	
	list($event_flags, $club_id, $club_flags, $tournament_id, $tournament_flags, $city_id, $area_id) = Db::record(get_label('event'), 
		'SELECT e.flags, c.id, c.flags, t.id, t.flags, ct.id, ct.area_id'.
		' FROM events e'.
		' JOIN clubs c ON c.id = e.club_id'.
		' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
		' JOIN cities ct ON ct.id = c.city_id'.
		' WHERE e.id = ?', $event_id);
	check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
	
	dialog_title(get_label('New player'));
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('User name') . ':</td><td><input id="form-name"></td></tr>';
	echo '<tr><td>' . get_label('Email') . ':</td><td><input type="email" id="form-email"></td></tr>';
	echo '<tr><td>' . get_label('Gender') . ':</td><td><input type="radio" name="form-gender" id="form-male" checked> ' . get_label('male');
	echo ' <input type="radio" name="form-gender" id="form-female"> ' . get_label('female');
	echo '</td></tr></table>';
?>
	<script>
	
	function commit(onSuccess)
	{
		json.post("api/ops/event.php", 
		{
			op: 'new_player_attend'
			, event_id: <?php echo $event_id; ?>
			, name: $("#form-name").val()
			, email: $("#form-email").val()
			, gender: $("#form-male").attr("checked") ? 1 : 0
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