<?php

require_once __DIR__ . '/club.php';
require_once __DIR__ . '/cards.php';

// The lists of clubs as tiles, the way clubs.php shows them: one for every list of clubs of the
// site - of a league, a series, a player.
//
// A page reads its clubs with its own query into objects with these fields:
//   id, name, flags, city, games
// and optionally:
//   mine    - whether the viewer is a member (the chip "My club");
//   buttons - the html of the buttons of a manager, drawn under the name;
//   extra   - the html of a line of the tile under the city (a player's result in the club, the
//             state of a club in a league);
//   num     - array(number, words, url) for the frame on the right instead of the games of the
//             club.
// Then it calls club_tiles_show() to print them.

// The menu "≡" of a club for whoever manages or referees it, as on its own page; and what the list
// adds of its own (buttons: taking it out of a league or a series).
function club_card_menu($club)
{
	global $_profile;

	$buttons = isset($club->buttons) ? $club->buttons : '';
	$prep = NULL;
	$items = array();
	if ($_profile != NULL && is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club->id))
	{
		$memb_flags = isset($_profile->clubs[$club->id]) ? $_profile->clubs[$club->id]->flags : 0;
		$buttons = PageBase::capture(function() use ($club, $memb_flags) { show_club_buttons($club->id, $club->name, $club->flags, $memb_flags); }) . $buttons;
		if (($club->flags & CLUB_FLAG_CLOSED) == 0 && is_permitted(PERMISSION_CLUB_MANAGER, $club->id))
		{
			$prep = new MenuItem('club_management.php?id=' . $club->id, get_label('Management'), get_label('Manage [0].', $club->name));
		}
		if (($club->flags & CLUB_FLAG_CLOSED) == 0)
		{
			$items = club_manage_items($club->id, $club->name, is_permitted(PERMISSION_CLUB_MANAGER, $club->id));
		}
	}
	return card_menu($buttons, $prep, $items);
}

// One tile.
function club_tile_show($club)
{
	static $club_pic = NULL;
	if (is_null($club_pic))
	{
		$club_pic = new Picture(CLUB_PICTURE);
	}

	$closed = ($club->flags & CLUB_FLAG_CLOSED) != 0;
	$mine = isset($club->mine) && $club->mine;
	$club_pic->set($club->id, $club->name, $club->flags);
	echo '<div class="tile' . ($closed ? ' is-off' : '') . '">' . club_card_menu($club) . card_logo($club_pic, $club->name);
	echo '<div class="who-txt"><a class="who-name" href="club_main.php?bck=1&id=' . $club->id . '">' . $club->name . '</a>';
	echo '<span class="who-sub">' . PageBase::icon('pin') . $club->city . '</span>';
	// The clubs of the viewer say so in words: a coloured edge alone said nothing anybody could read.
	if ($mine || $closed)
	{
		echo '<span class="tile-meta">';
		if ($mine)
		{
			echo '<span class="chip mine">' . get_label('My club') . '</span>';
		}
		if ($closed)
		{
			echo '<span class="chip off">' . get_label('closed') . '</span>';
		}
		echo '</span>';
	}
	if (isset($club->extra))
	{
		echo $club->extra;
	}
	echo '</div>';
	if (isset($club->num))
	{
		list($number, $words, $url) = $club->num;
	}
	else
	{
		$number = number_format($club->games);
		$words = get_label('Games');
		$url = 'club_games.php?bck=1&id=' . $club->id;
	}
	echo '<div class="tile-side"><a class="tile-num" href="' . $url . '" title="' . $words . '"><b>' . $number . '</b><span>' . $words . '</span></a></div>';
	echo '</div>';
}

// The tiles in their grid - as wide, all as tall - or $empty when there is none.
function club_tiles_show($clubs, $empty)
{
	if (count($clubs) == 0)
	{
		echo '<p class="empty">' . $empty . '</p>';
		return;
	}
	echo '<div class="tiles wide">';
	foreach ($clubs as $club)
	{
		club_tile_show($club);
	}
	echo '</div>';
}

?>
