<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';

initiate_session();

try
{
	if (!isset($_REQUEST['game_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$game_id = $_REQUEST['game_id'];
	
	if (!isset($_REQUEST['user_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = $_REQUEST['user_id'];
	
	list($user_name, $extra_points, $extra_points_reason) = Db::record(get_label('player'), 'SELECT u.name, p.extra_points, p.extra_points_reason FROM players p JOIN users u ON u.id = p.user_id WHERE u.id = ? AND p.game_id = ?', $user_id, $game_id);
	if ($extra_points_reason == NULL)
	{
		$extra_points_reason = '';
	}
	
	dialog_title(get_label('Extra points', $user_name, $game_id));
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr class="darker"><td colspan="2" align="center">' . get_label('Extra points for [0] in game [1]', $user_name, $game_id) . '</td></tr>';
	echo '<tr><td width="80">' . get_label('Points') . ':</td><td><input type="number" style="width: 45px;" step="0.1" id="form-points" value="' . $extra_points . '"></td></tr>';
	echo '<tr><td>' . get_label('Reason') . ':</td><td><textarea id="form-reason" cols="64" rows="8">' . $extra_points_reason . '</textarea></td></tr>';
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/game.php",
		{
			op: "extra_points"
			, game_id: <?php echo $game_id; ?>
			, user_id: <?php echo $user_id; ?>
			, reason: $('#form-reason').val()
			, points: $('#form-points').val()
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