<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/security.php';
require_once '../include/video.php';

initiate_session();

try
{
	if (!isset($_REQUEST['game']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$id = $_REQUEST['game'];

	list($club_id, $video, $vtime, $user_id, $event_id, $tournament_id) = Db::record(get_label('game'), 'SELECT g.club_id, v.video, v.vtime, g.user_id, g.event_id, g.tournament_id FROM games g LEFT OUTER JOIN videos v ON v.id = g.video_id WHERE g.id = ?', $id);
	check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $event_id, $tournament_id);

	// The whole address, time and all, because the time is the part most often changed: where
	// a game begins in a recording is worked out from how long the stream was off the air, and
	// that is measured rather than known. Whoever watches the recording can see where the game
	// really starts, and this is where they say so.
	$url = is_null($video) ? '' : get_video_url($video, $vtime);

	dialog_title(get_label('Set game [0] video', $id));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">'.get_label('Video URL').':</td><td><input id="form-video" size="65" value="' . htmlspecialchars($url) . '" title="' . get_label('Paste the youtube video URL here.') . '"></td></tr>';
	echo '</table>';
	if (!is_null($video))
	{
		echo '<p><i>' . get_label('The games of this stream that were timed by [0] itself, and that nobody has corrected, are moved by as much as you move this one.', PRODUCT_NAME) . '</i></p>';
	}

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
