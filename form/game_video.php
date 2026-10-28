<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/video.php';
require_once '../include/broadcast.php';

initiate_session();

try
{
	if (!isset($_REQUEST['game']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$id = $_REQUEST['game'];

	list($video, $title, $vtime, $flags, $event_id, $tournament_id, $table_num) = Db::record(get_label('game'), 'SELECT v.video, v.name, v.vtime, v.flags, g.event_id, g.tournament_id, g.table_num FROM games g JOIN videos v ON v.id = g.video_id WHERE g.id = ?', $id);

	dialog_title(get_label('Game [0] video', $id));

	$url = get_video_url($video, $vtime);
	echo '<p><a href="' . $url . '" target="_blank">' . $title . '</a></p>';
	echo '<p><iframe title="YouTube video player" width="780" height="460" src="' . get_embed_video_url($video, $vtime) . '" frameborder="0" allowfullscreen></iframe></p>';

	// Where the site worked the time out itself, it is only as good as its account of how long
	// the stream was off the air, and the recording settles that afterwards. Where the two
	// disagree by more than the time it takes to notice, whoever opens the game is told so
	// rather than left to wonder why it starts in the wrong place.
	if (((int)$flags & VIDEO_FLAG_AUTO_TIME) != 0 && !is_null($event_id))
	{
		$owner = stream_owner(is_null($tournament_id) ? 0 : $tournament_id, $event_id);
		$difference = is_null($owner) ? NULL
			: broadcast_difference($owner, broadcast_of_video($event_id, $video, $table_num));
		if (!is_null($difference) && abs($difference) > BROADCAST_TOLERANCE_SECONDS)
		{
			echo '<p><i>' . get_label('[0] worked this moment out rather than being told it, and the recording says it may be out by as much as [1]. If it is, correct it from the games list and every later game of the same recording will move with it.',
				PRODUCT_NAME, format_lost_time(abs($difference))) . '</i></p>';
		}
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>
