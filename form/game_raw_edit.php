<?php

require_once '../include/session.php';
require_once '../include/game_state.php';

initiate_session();

try
{
	dialog_title(get_label('Edit game json'));

	if (!isset($_REQUEST['game_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('game')));
	}
	$game_id = $_REQUEST['game_id'];
	
	list ($game_log, $is_canceled) = Db::record(get_label('game'), 'SELECT log, canceled FROM games WHERE id = ?', $game_id);
	$gs = new GameState();
	$gs->init_existing($game_id, $game_log, $is_canceled);
	echo '<textarea id="form-json" cols="165" rows="60">' . formatted_json($gs) . '</textarea>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/game.php",
		{
			op: "raw_change"
			, game_id: <?php echo $game_id; ?>
			, json: $('#form-json').val()
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