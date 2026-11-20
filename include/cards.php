<?php

require_once __DIR__ . '/picture.php';
require_once __DIR__ . '/localization.php';

// Pieces of the markup of the v3 design shared by the main page and the lists: the cards of
// tournaments and series, the tiles of clubs and leagues, small date stamps and the like.
// Everything here returns a string; nothing prints.

// Two letters standing in for a missing picture: "GG" for Golden Gate, "Cr" for Craig.
function card_initials($name, $upper = true)
{
	$name = trim(html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8'));
	$words = preg_split('/[\s\-_.]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
	if (count($words) >= 2)
	{
		$result = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8');
	}
	else
	{
		$result = mb_substr($name, 0, 2, 'UTF-8');
	}
	$result = $upper ? mb_strtoupper($result, 'UTF-8') : mb_convert_case($result, MB_CASE_TITLE, 'UTF-8');
	return htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
}

// The round picture of a player, or their initials on a colour of their own. $pic is a
// Picture(USER_PICTURE) to reuse. $size: 'sm' for the small one of a list, '' for the one of a table.
function card_avatar($pic, $id, $name, $flags, $size = 'sm')
{
	$pic->set($id, $name, $flags);
	return card_avatar_of($pic, $id, $name, $size);
}

// The same for a picture set already - one that falls back from a photo of a tournament to one of
// a club to the player's own, say.
function card_avatar_of($pic, $id, $name, $size = 'sm')
{
	$cls = 'av' . ($size == '' ? '' : ' ' . $size);
	if ($pic->has_image())
	{
		return '<span class="' . $cls . '"><img src="' . $pic->url(ICONS_DIR) . '" alt="" loading="lazy" decoding="async"></span>';
	}
	return '<span class="' . $cls . ' t' . (($id % 6) + 1) . '" aria-hidden="true">' . card_initials($name, false) . '</span>';
}

// A club in a row of a table: its small logo and its name.
function card_club($pic, $id, $name, $flags)
{
	if ($id == NULL)
	{
		return '';
	}
	$pic->set($id, $name, $flags);
	$logo = $pic->has_image() ?
		'<img src="' . $pic->url(ICONS_DIR) . '" alt="" loading="lazy" decoding="async">' :
		'<span class="club-ini" aria-hidden="true">' . card_initials($name) . '</span>';
	return '<a class="club" href="club_main.php?bck=1&id=' . $id . '" title="' . strip_tags($name) . '">' . $logo . '<span>' . $name . '</span></a>';
}

// The picture of a club, a league, a series in a tile: its own logo, or its initials. $pic is set
// already.
function card_logo($pic, $name)
{
	if ($pic->has_image())
	{
		return '<img class="logo" src="' . $pic->url(ICONS_DIR) . '" alt="" loading="lazy" decoding="async">';
	}
	return '<span class="logo is-empty" aria-hidden="true">' . card_initials($name) . '</span>';
}

// The short name of a month: "Nov", "Ноя".
function card_month_abbr($month)
{
	global $_lang;
	return mb_substr(get_month_name($month, $_lang, true), 0, 3, 'UTF-8');
}

// A date as a little calendar page: the month over the day.
function card_dstamp($time, $timezone)
{
	date_default_timezone_set($timezone);
	return '<span class="dstamp" aria-hidden="true"><span class="m">' . mb_strtoupper(card_month_abbr(date('n', $time)), 'UTF-8') . '</span><span class="d">' . date('j', $time) . '</span></span>';
}

// The days of a tournament, short: "Nov 7–8", "7–8 ноя", "Oct 30 – Nov 2". Another year than the
// current one is added at the end.
function card_short_period($start, $duration, $timezone)
{
	global $_lang;

	date_default_timezone_set($timezone);
	$end = $start + max($duration, 1) - 1;
	$sd = date('j', $start);
	$ed = date('j', $end);
	$sm = card_month_abbr(date('n', $start));
	$em = card_month_abbr(date('n', $end));
	$same_month = date('Y-n', $start) == date('Y-n', $end);
	$same_day = $same_month && $sd == $ed;
	$year = date('Y', $end) != date('Y') ? ' ' . date('Y', $end) : '';
	if ($_lang == LANG_RUSSIAN || $_lang == LANG_UKRAINIAN)
	{
		$sm = mb_strtolower($sm, 'UTF-8');
		$em = mb_strtolower($em, 'UTF-8');
		if ($same_day)
		{
			return $sd . ' ' . $sm . $year;
		}
		if ($same_month)
		{
			return $sd . '–' . $ed . ' ' . $sm . $year;
		}
		return $sd . ' ' . $sm . ' – ' . $ed . ' ' . $em . $year;
	}
	if ($same_day)
	{
		return $sm . ' ' . $sd . $year;
	}
	if ($same_month)
	{
		return $sm . ' ' . $sd . '–' . $ed . $year;
	}
	return $sm . ' ' . $sd . ' – ' . $em . ' ' . $ed . $year;
}

// The picture of a tournament filling a card: its logo over a blurred copy of itself; or, when it
// has none, its initials with the logo of the club in the corner. Both pictures are set already.
function card_cover($tournament_pic, $club_pic, $name)
{
	if ($tournament_pic->has_image(true))
	{
		$url = $tournament_pic->url(TNAILS_DIR);
		return '<span class="cv-blur" style="background-image: url(\'' . $url . '\');"></span><img class="cv-logo" src="' . $url . '" alt="" loading="lazy" decoding="async">';
	}
	$html = '<span class="pc-fallback"><span class="pc-mono">' . card_initials($name) . '</span>';
	if ($club_pic->has_image())
	{
		$html .= '<img class="pc-club" src="' . $club_pic->url(ICONS_DIR) . '" alt="">';
	}
	return $html . '</span>';
}

// The two choices of a list - past and future, say - as a segmented switch. $items is an array of
// array(url, text, is_current).
function card_seg($items, $label)
{
	$html = '<nav class="seg" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
	foreach ($items as $item)
	{
		list($url, $text, $current) = $item;
		$html .= '<a href="' . $url . '"' . ($current ? ' aria-current="page"' : '') . '>' . $text . '</a>';
	}
	return $html . '</nav>';
}

// The actions of a row of a table as one button, "≡", that opens a menu of them - rather than a
// row of icons nobody can tell apart. $items is an array of array(onclick, image, words); the
// image is one of images/*.png the buttons used to show. new_look.js closes the menu on a click
// anywhere else and on Escape.
function card_row_menu($items, $label = NULL)
{
	if (count($items) == 0)
	{
		return '';
	}
	if (is_null($label))
	{
		$label = get_label('Actions');
	}
	$html = '<details class="row-menu"><summary title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . PageBase::icon('menu') . '</summary><div class="rm-list" role="menu">';
	foreach ($items as $item)
	{
		list($onclick, $image, $words) = $item;
		$html .= '<button type="button" role="menuitem" onclick="this.closest(\'details\').open = false; ' . htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') . '"><img src="images/' . $image . '" alt="">' . $words . '</button>';
	}
	return $html . '</div></details>';
}

// The filter by name of a list, asked of the database (a list is a page out of many): the words
// searched for, from ?search=, and the condition on a name_id column - "t.name_id", "s.name_id" -
// added to $condition. The names of a thing are rows of the table names, one for each language,
// sharing an id; any of them matches.
function card_search_condition($condition, $name_id_column)
{
	$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
	if ($search != '')
	{
		$like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search) . '%';
		$condition->add(' AND ' . $name_id_column . ' IN (SELECT n.id FROM names n WHERE n.name LIKE ?)', $like);
	}
	return $search;
}

// Its field, first on the first line of the toolbar of a list (see the toolbar pattern in
// new_look.css). Its id is not "search": that is the search of the site in the header.
function card_search_field($search, $placeholder = NULL)
{
	if (is_null($placeholder))
	{
		$placeholder = get_label('Filter by name');
	}
	$html = '<div class="field grow">' . PageBase::icon('search');
	$html .= '<input type="text" id="list-search" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '" onkeydown="if (event.key == \'Enter\') listSearch()">';
	$html .= '<button class="small_icon" onclick="$(\'#list-search\').val(\'\'); listSearch()" title="' . get_label('Clear') . '"><img src="images/clear.png" width="12"></button>';
	$html .= '</div>';
	$html .= '<script>function listSearch() { var s = $.trim($("#list-search").val()); goTo({search: (s == "" ? undefined : s), page: undefined}); }</script>';
	return $html;
}

// The address of the current page with some of its parameters changed: NULL removes one.
function card_url($changes)
{
	$params = $_GET;
	foreach ($changes as $key => $value)
	{
		if (is_null($value))
		{
			unset($params[$key]);
		}
		else
		{
			$params[$key] = $value;
		}
	}
	$query = http_build_query($params);
	return basename($_SERVER['SCRIPT_NAME']) . ($query == '' ? '' : '?' . $query);
}

?>
