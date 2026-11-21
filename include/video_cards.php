<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/video.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/localization.php';

// The cards of videos, in the look of the cards of tournaments and game days (.posters / .pcard in
// new_look.css): the picture YouTube makes of the video with a "play" over it and the language in
// the corner; under it the name, what it is - a game at a table, or the kind of video - and where it
// is from; the day it was played at the foot.
//
// $videos: stdClass each with id, video (the id at YouTube), name, lang, type, time, game_id,
// table_num, game_num, club_id, club_name, event_id, event_name, tournament_id, tournament_name.
// $link: the parameters added to the address of a video - the filters to go on with from it.
// $opts: 'lang' => false leaves the language out (a club that speaks one); 'club', 'event',
// 'tournament' => false leave out what the page itself is about. Any of the fields may be missing:
// what is not there is not shown.
function video_cards_show($videos, $link, $empty, $opts = array())
{
	$opts = array_merge(array('lang' => true, 'club' => true, 'event' => true, 'tournament' => true), $opts);
	if (count($videos) == 0)
	{
		echo '<p class="empty">' . $empty . '</p>';
		return;
	}
	echo '<div class="posters">';
	foreach ($videos as $v)
	{
		foreach (array('game_id', 'table_num', 'game_num', 'club_id', 'club_name', 'event_id', 'event_name', 'tournament_id', 'tournament_name', 'time') as $field)
		{
			if (!isset($v->$field))
			{
				$v->$field = NULL;
			}
		}
		$url = 'video.php?bck=1&id=' . $v->id . $link;
		if (is_null($v->game_id))
		{
			$what = get_video_title($v->type);
		}
		else if (is_null($v->game_num))
		{
			$what = get_label('Game #[0]', $v->game_id);
		}
		else if (is_null($v->table_num))
		{
			$what = get_label('Game [0]', $v->game_num);
		}
		else
		{
			$what = get_label('Table [0], Game [1]', $v->table_num, $v->game_num);
		}
		$name = trim($v->name) == '' ? $what : $v->name;

		echo '<article class="pcard vcard"><div class="pc-cover">';
		// mqdefault is 16:9 like the card; 0.jpg is 4:3 with black bars.
		echo '<img class="vc-thumb" src="https://img.youtube.com/vi/' . htmlspecialchars($v->video, ENT_QUOTES) . '/mqdefault.jpg" alt="" loading="lazy" decoding="async">';
		echo '<span class="vc-play" aria-hidden="true">' . PageBase::icon('play') . '</span>';
		if ($opts['lang'])
		{
			echo '<span class="vc-lang">' . get_short_lang_str($v->lang) . '</span>';
		}
		echo '</div><div class="pc-body"><h3><a href="' . $url . '">' . $name . '</a></h3>';
		echo '<div class="pc-meta">';
		if ($name != $what)
		{
			echo '<span>' . PageBase::icon('video') . $what . '</span>';
		}
		if (!is_null($v->tournament_id) && $opts['tournament'])
		{
			echo '<a href="tournament.php?bck=1&id=' . $v->tournament_id . '">' . PageBase::icon('trophy') . $v->tournament_name . '</a>';
		}
		else if (!is_null($v->event_id) && $opts['event'])
		{
			echo '<a href="event.php?bck=1&id=' . $v->event_id . '">' . PageBase::icon('cal') . $v->event_name . '</a>';
		}
		if (!is_null($v->club_id) && $opts['club'])
		{
			echo '<a href="club_main.php?bck=1&id=' . $v->club_id . '">' . PageBase::icon('users') . $v->club_name . '</a>';
		}
		echo '</div>';
		if ($v->time > 0)
		{
			echo '<div class="pc-foot"><span class="dim">' . format_date($v->time, get_timezone()) . '</span></div>';
		}
		echo '</div></article>';
	}
	echo '</div>';
}

?>