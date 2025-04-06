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
	$tournament_id = (int)$_REQUEST['tournament_id'];
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	
	$user_id = 0;
	$total_points = $main_points = $bonus_points = $shot_points = $games_count = null;
	if (isset($_REQUEST['user_id']))
	{
		$user_id = (int)$_REQUEST['user_id'];
		list($user_name) = Db::record(get_label('user'), 'SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
		dialog_title(get_label('Edit tournament score for [0]', $user_name));
		list ($main_points, $bonus_points, $shot_points) = Db::record(get_label('score'), 'SELECT main_points, bonus_points, shot_points, games_count FROM tournament_places WHERE tournament_id = ? AND user_id = ?', $tournament_id, $user_id);
		$total_points = $main_points + $bonus_points + $shot_points;
	}
	else
	{
		dialog_title(get_label('Add tournament score'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	if ($user_id <= 0)
	{
		echo '<tr><td width="160">' . get_label('Player') . ':</td><td>';
		show_user_input('form-user', '', 'tournament=' . $tournament_id, get_label('Select user.'), 'onSelect');
		echo '</td></tr>';
	}
	echo '<tr><td width="160">' . get_label('Total points') . ':</td><td><input type="number" style="width: 45px;" step="0.5" id="form-total"' . (is_null($total_points) ? '' : ' value="'.$total_points.'"') . '></td></tr>';
	echo '<tr><td>' . get_label('Bonus points') . ':</td><td><input type="number" style="width: 45px;" step="0.5" id="form-bonus"' . (is_null($bonus_points) ? '' : ' value="'.$bonus_points.'"') . '></td></tr>';
	echo '<tr><td>' . get_label('Points for being shot first night') . ':</td><td><input type="number" style="width: 45px;" step="0.5" id="form-shot"' . (is_null($shot_points) ? '' : ' value="'.$shot_points.'"') . '></td></tr>';
	echo '<tr><td>' . get_label('Games played') . ':</td><td><input type="number" style="width: 45px;" step="1" min="0" id="form-games"' . (is_null($games_count) ? '' : ' value="'.$games_count.'"') . '></td></tr>';
	echo '</table>';
	
?>
	<script>
	var userId = <?php echo $user_id; ?>;
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
	
	function commit(onSuccess)
	{
		let params =
		{
			op: 'set_score'
			, user_id: userId
			, tournament_id: <?php echo $tournament_id; ?>
			, points: $("#form-total").val()
		};

		let val = $("#form-bonus").val();
		if (val.length > 0)
			params['bonus_points'] = val;
		
		val = $("#form-shot").val();
		if (val.length > 0)
			params['shot_points'] = val;

		val = $("#form-games").val();
		if (val.length > 0)
			params['games_count'] = val;
		
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