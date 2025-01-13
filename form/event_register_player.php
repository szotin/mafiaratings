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
	
	$num = -1;
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
	
	dialog_title(get_label('Register player'));
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td><input id="form-name" onkeyup="nameChange()"></td></tr>';
	echo '<tr><td id="form-users"><table class="dialog_form" width="100%">';
	for ($i = 0; $i < MAX_ROW_COUNT; ++$i)
	{
		echo '<tr style="height: 130px;">';
		for ($j = 0; $j < COLUMN_COUNT; ++$j)
		{
			echo '<td width="' . COLUMN_WIDTH . '%"></td>';
		}
		echo '</tr>';
	}
	echo '</table></td></tr>';
	echo '</table>';
?>
	<script>
	function userSelected(userId)
	{
		json.post("api/ops/event.php", 
		{
			op: 'attend'
			, event_id: <?php echo $event_id; ?>
			, user_id: userId
		},
		function(data)
		{
			uiRegisterPlayer(<?php echo $num; ?>, data);
			dlg.close();
		});
	}
	
	function getContent()
	{
		var aaa = $("#form-name").val();
		html.post("form/event_register_player_content.php", {"club_id": <?php echo $club_id ?>, "city_id": <?php echo $city_id ?>, "area_id": <?php echo $area_id ?>, "name": $("#form-name").val()}, function(html, title)
		{
			$('#form-users').html(html);
		});
	}
	
	var timeoutSet = false;
	function nameChange()
	{
		if (!timeoutSet)
		{
			timeoutSet = true;
			setTimeout(function() 
			{
				timeoutSet = false;
				getContent();
			}, 1000);
		}
	}
	
	getContent();
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