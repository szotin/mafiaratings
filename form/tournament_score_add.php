<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/tournament.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Add tournament score'));
	
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	
	$tournament_id = (int)$_REQUEST['tournament_id'];
	list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Player') . ':</td><td>';
	show_user_input('form-user', '', '', get_label('Select user.'), 'onSelect');
	echo '</td></tr>';
	echo '<tr><td>' . get_label('Main points') . ':</td><td><input type="number" style="width: 45px;" step="0.5" id="form-main"></td></tr>';
	echo '<tr><td>' . get_label('Bonus points') . ':</td><td><input type="number" style="width: 45px;" step="0.5" id="form-bonus" onchange="pChange(0)"> <input id="form-bonus-u" onchange="uChange(0)" type="checkbox" checked> ' . get_label('unknown') . '</td></tr>';
	echo '<tr><td>' . get_label('Points for being shot first night') . ':</td><td><input type="number" style="width: 45px;" step="0.5" id="form-shot" onchange="pChange(1)"> <input id="form-shot-u" onchange="uChange(1)" type="checkbox" checked> ' . get_label('unknown') . '</td></tr>';
	echo '<tr><td>' . get_label('Games played') . ':</td><td><input type="number" style="width: 45px;" step="1" min="0" id="form-games" onchange="pChange(2)"> <input id="form-games-u" onchange="uChange(2)" type="checkbox" checked> ' . get_label('unknown') . '</td></tr>';
	echo '</table>';
	
?>
	<script>
	var userId = 0;
	function onSelect(_user)
	{
		if (typeof _user.id == "number")
		{
			userId = _user.id;
			$("#form-nick").val(_user.name);
		}
		else
		{
			userId = 0;
		}
	}
	
	function valueId(value)
	{
		switch (value)
		{
			case 0:
				return "form-bonus";
			case 1:
				return "form-shot";
			case 2:
				return "form-games";
		}
		return null;
	}
	
	function pChange(value)
	{
		$("#" + valueId(value) + "-u").prop("checked", false);
	}
	
	function uChange(value)
	{
		$("#" + valueId(value)).val("");
	}
	
	function commit(onSuccess)
	{
		var params =
		{
			op: 'add_score'
			, user_id: userId
			, tournament_id: <?php echo $tournament_id; ?>
			, main_points: $("#form-main").val()
		};
		
		if (!$("#form-bonus-u").attr('checked'))
			params['bonus_points'] = $("#form-bonus").val();
		if (!$("#form-shot-u").attr('checked'))
			params['shot_points'] = $("#form-shot").val();
		if (!$("#form-games-u").attr('checked'))
			params['games_count'] = $("#form-games").val();
		
		json.post("api/ops/tournament.php", params, onSuccess);
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