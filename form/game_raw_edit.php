<?php

require_once '../include/session.php';
require_once '../include/game.php';

initiate_session();

try
{
	dialog_title(get_label('Edit game json'));

	if (!isset($_REQUEST['game_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('game')));
	}
	$game_id = $_REQUEST['game_id'];
	
	if (isset($_REQUEST['issue']))
	{
		if (isset($_REQUEST['features']))
		{
			$feature_flags = (int)$_REQUEST['features'];
			list ($json) = Db::record(get_label('game'), 'SELECT json FROM game_issues WHERE game_id = ? AND feature_flags = ?', $game_id, $feature_flags);
		}
		else
		{
			list ($json, $feature_flags) = Db::record(get_label('game'), 'SELECT json FROM game_issues WHERE game_id = ? AND new_feature_flags = (SELECT feature_flags FROM games WHERE id = ?)', $game_id, $game_id);
		}
		$data = json_decode($json);
	}
	else
	{
		if (isset($_REQUEST['features']))
		{
			$feature_flags = (int)$_REQUEST['features'];
			list ($json) = Db::record(get_label('game'), 'SELECT json FROM games WHERE id = ?', $game_id);
		}
		else
		{
			list ($json, $feature_flags) = Db::record(get_label('game'), 'SELECT json, feature_flags FROM games WHERE id = ?', $game_id);
		}
		
		$game = new Game($json, $feature_flags);
		$data = $game->data;
	}
	$json = formatted_json($data);
	echo '<textarea id="form-json" cols="165" rows="60">' . $json . '</textarea>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/game.php",
		{
			op: "change"
			, game_id: <?php echo $game_id; ?>
			, json: $('#form-json').val()
		},
		function(data)
		{
			if (data.rebuild_ratings)
			{
				console.log(data);
				dlg.info('<?php echo get_label('Ratings will be rebuilt as a result of this change.'); ?>', '<?php echo get_label('Game saved'); ?>', null, onSuccess);
			}
			else
			{
				onSuccess();
			}
		});
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