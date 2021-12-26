<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';

initiate_session();

try
{
	if (!isset($_REQUEST['game']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$id = $_REQUEST['game'];
	
	list($club_id, $video) = Db::record(get_label('game'), 'SELECT g.club_id, v.video FROM games g LEFT OUTER JOIN videos v ON v.id = g.video_id WHERE g.id = ?', $id);
	if ($_profile == NULL || !isset($_profile->clubs[$club_id]) || ($_profile->clubs[$club_id]->flags & USER_PERM_MODER) == 0)
	{
		throw new FatalExc(get_label('No permissions'));
	}
		
	dialog_title(get_label('Set game [0] video', $id));
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">'.get_label('Video URL').':</td><td><input id="form-video" size="65" value="' . $video . '" title="' . get_label('Paste the youtube video URL here.') . '"></td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/video.php",
		{
			op: "game_video",
			game_id: <?php echo $id; ?>,
			video: $("#form-video").val()
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