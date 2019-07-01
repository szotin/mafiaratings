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
	
	list($user_name, $extra_points) = Db::record(get_label('player'), 'SELECT u.name, p.extra_points FROM players p JOIN users u ON u.id = p.user_id WHERE u.id = ? AND p.game_id = ?', $user_id, $game_id);
	
		
	dialog_title(get_label('Extra points', $user_name, $game_id));
		
	echo '<p>' . get_label('Extra points for [0] in game [1]', $user_name, $game_id) . ': <input id="form-extra-points" value="' . $extra_points . '"></p>';
	
?>	
	<script>
	$("#form-extra-points").spinner({ step:0.05, max:0.7, min:-0.4 }).width(32);
	function commit(onSuccess)
	{
		json.post("api/ops/game.php",
		{
			op: "extra_points"
			, game_id: <?php echo $game_id; ?>
			, user_id: <?php echo $user_id; ?>
			, points: $('#form-extra-points').val()
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