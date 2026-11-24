<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/security.php';
require_once '../include/video.php';
require_once '../include/broadcast.php';
require_once '../include/game.php';
require_once '../include/cards.php';

initiate_session();

try
{
	global $_lang;

	if (!isset($_REQUEST['game']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$id = $_REQUEST['game'];

	list($video, $title, $vtime, $flags, $event_id, $tournament_id, $table_num, $game_num, $event_name, $tournament_name, $user_id, $club_id, $json, $feature_flags) = Db::record(get_label('game'),
		'SELECT v.video, v.name, v.vtime, v.flags, g.event_id, g.tournament_id, g.table_num, g.game_num, ne.name, nt.name, g.user_id, g.club_id, g.json, g.feature_flags FROM games g' .
		' JOIN videos v ON v.id = g.video_id' .
		' JOIN events e ON e.id = g.event_id' .
		' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
		' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
		' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
		' WHERE g.id = ?', $id);

	// Which game it is: where it was played, then its table and its number at the table. The number
	// of the game on the site only where the two are not known.
	$where = is_null($tournament_name) ? $event_name : $tournament_name . ': ' . $event_name;
	if (!is_null($table_num) && !is_null($game_num))
	{
		$what = get_label('Table [0], Game [1]', $table_num, $game_num);
	}
	else
	{
		$what = get_label('Game [0]', $id);
	}
	dialog_title($where . '. ' . $what);

	$url = get_video_url($video, $vtime);
	// The name of the video is made up when it is tied to the game ("Game 29428"): the title says
	// which game it is, the link only where to watch it.
	echo '<p><a href="' . $url . '" target="_blank">' . get_label('YouTube') . ' &#8599;</a></p>';
	$start = broadcast_vtime_seconds($vtime);
	echo '<p><iframe id="game-video-player" title="YouTube video player" style="display:block; width:100%; height:auto; aspect-ratio:16/9" src="https://www.youtube.com/embed/' . $video . '?start=' . $start . '&enablejsapi=1" frameborder="0" allowfullscreen></iframe></p>';

	// Who plays in it, by their numbers, in a row of chips under the video.
	$game = new Game($json, $feature_flags);
	$ids = array();
	foreach ($game->data->players as $player)
	{
		if (isset($player->id) && $player->id > 0)
		{
			$ids[] = (int)$player->id;
		}
	}
	$users = array();
	if (count($ids) > 0)
	{
		$query = new DbQuery('SELECT id, flags FROM users WHERE id IN (' . implode(',', $ids) . ')');
		while ($row = $query->next())
		{
			$users[(int)$row[0]] = (int)$row[1];
		}
	}
	$user_pic = new Picture(USER_PICTURE);
	echo '<ol class="video-players">';
	for ($i = 0; $i < 10; ++$i)
	{
		$player = $game->data->players[$i];
		$name = isset($player->name) ? $player->name : '';
		$player_id = isset($player->id) ? (int)$player->id : 0;
		if (isset($users[$player_id]))
		{
			echo '<li><a href="user_info.php?bck=1&id=' . $player_id . '" target="_blank" title="' . htmlspecialchars($name, ENT_QUOTES) . '">' . card_avatar($user_pic, $player_id, $name, $users[$player_id], 'lg') . '<span class="n">' . ($i + 1) . '</span></a></li>';
		}
		else
		{
			echo '<li title="' . htmlspecialchars($name, ENT_QUOTES) . '"><span class="av lg t5" aria-hidden="true">' . card_initials($name, false) . '</span><span class="n">' . ($i + 1) . '</span></li>';
		}
	}
	echo '</ol>';

	// The start the site worked out itself is a guess - see VIDEO_FLAG_AUTO_TIME. A manager or a
	// referee watching the game can put it right: rewind to where the game really starts and press
	// the button. From then on the start is a person's, and the button is not offered any more. The
	// games of the recording placed by the site move with it.
	if (((int)$flags & VIDEO_FLAG_AUTO_TIME) != 0 &&
		is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_MANAGER | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id))
	{
		echo '<div class="video-start" id="game-video-fix">';
		echo '<p>' . get_label('The start of the game was found automatically: [0]. If the game starts elsewhere, rewind the video to where it really starts and press the button.', '<b id="game-video-start"></b>') . '</p>';
		echo '<button type="button" class="btn" id="game-video-here" disabled onclick="gameStartsHere()">' . get_label('The game starts at this moment') . '</button>';
		echo '</div>';
		echo '<p class="dim" id="game-video-done" style="display:none"></p>';
?>
		<script>
		var gameVideoStart = <?php echo $start; ?>;
		var gameVideoPlayer = null;

		function gameVideoTime(s)
		{
			s = Math.max(0, Math.floor(s));
			var h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, sec = s % 60;
			return h + ':' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
		}

		function gameVideoReady()
		{
			gameVideoPlayer = new YT.Player('game-video-player', { events: { onReady: function() { $('#game-video-here').prop('disabled', false); } } });
		}

		function gameStartsHere()
		{
			if (gameVideoPlayer == null || !gameVideoPlayer.getCurrentTime)
			{
				return;
			}
			var t = Math.floor(gameVideoPlayer.getCurrentTime());
			json.post("api/ops/video.php", { op: "game_video_start", game_id: <?php echo $id; ?>, vtime: t }, function(obj)
			{
				var text = '<?php echo get_label('The game starts at [0].'); ?>'.replace('[0]', gameVideoTime(t));
				var moved = obj.moved_with_it ? obj.moved_with_it : 0;
				if (moved > 0)
				{
					text += ' ' + '<?php echo get_label('Other games of this recording moved with it: [0].'); ?>'.replace('[0]', moved);
				}
				$('#game-video-fix').remove();
				$('#game-video-done').text(text).show();
			});
		}

		$('#game-video-start').text(gameVideoTime(gameVideoStart));
		if (typeof YT != 'undefined' && YT.Player)
		{
			gameVideoReady();
		}
		else
		{
			window.onYouTubeIframeAPIReady = gameVideoReady;
			$.getScript('https://www.youtube.com/iframe_api');
		}
		</script>
<?php
	}

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
