<?php

require_once 'include/languages.php';
require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/scoring.php';
require_once 'include/club_cards.php';

// The highest role a member has in a club, as the chip of the tile: a manager is a referee and a
// player as well, and the chip names the most of it. The rest is in the hint.
function club_role_chip($user_flags)
{
	$roles = array();
	$chip = NULL;
	if (($user_flags & USER_PERM_PLAYER) != 0)
	{
		$roles[] = get_label('player');
		$chip = get_label('Player');
	}
	if (($user_flags & USER_PERM_REFEREE) != 0)
	{
		$roles[] = get_label('Referee');
		$chip = get_label('Referee');
	}
	if (($user_flags & USER_PERM_MANAGER) != 0)
	{
		$roles[] = get_label('manager');
		$chip = get_label('Manager');
	}
	if (is_null($chip))
	{
		$chip = get_label('Member');
	}
	return '<span class="chip mine" title="' . implode('; ', $roles) . '">' . $chip . '</span>';
}

// A line of numbers: the games, the wins and the rating earned, with every role in the hint.
function club_stats_html($stats, $role_titles)
{
	if ($stats->games <= 0)
	{
		return '';
	}
	$hint = array();
	foreach ($stats->roles as $role => $r)
	{
		$hint[] = $role_titles[$role] . ': ' . $r->games . ', ' . number_format($r->won * 100 / $r->games) . '%, ' . format_rating($r->rating);
	}
	return '<span class="tile-meta pc-res" title="' . htmlspecialchars(implode("\n", $hint), ENT_QUOTES, 'UTF-8') . '">' .
		'<span title="' . get_label('Wins') . '">' . PageBase::icon('trophy') . $stats->won . ' (' . number_format($stats->won * 100 / $stats->games) . '%)</span>' .
		'<span title="' . get_label('Rating earned') . '">' . get_label('[0] ([1] per game)', format_rating($stats->rating), format_rating($stats->rating / $stats->games, 1)) . '</span></span>';
}

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile, $_lang;

		$role_titles = array(
			get_label('As a civilian'),
			get_label('As a sheriff'),
			get_label('As a mafiosi'),
			get_label('As a don'));
		
		// The clubs: the ones the player is a member of first, then the ones they only played in.
		// Each is a tile of clubs.php (include/club_cards.php) with the player's role as its chip,
		// their wins and rating under the city, and their games in the frame on the right.
		$clubs = array();
		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, ni.name, u.flags, p.role, SUM(p.rating_earned), COUNT(p.game_id), SUM(p.won) FROM clubs c' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN cities i ON i.id = c.city_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
				' JOIN games g ON g.club_id = c.id' .
				' JOIN players p ON p.game_id = g.id' .
				' LEFT OUTER JOIN club_regs u ON u.club_id = c.id AND u.user_id = p.user_id' .
				' WHERE p.user_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0 GROUP BY c.id, p.role',
			$this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $city_name, $user_flags, $role, $rating, $games, $won) = $row;
			if (!isset($clubs[$club_id]))
			{
				$club = new stdClass();
				$club->id = $club_id;
				$club->name = $club_name;
				$club->flags = $club_flags;
				$club->city = $city_name;
				$club->member = !is_null($user_flags);
				$club->user_flags = (int)$user_flags;
				$club->stats = new stdClass();
				$club->stats->rating = 0;
				$club->stats->games = 0;
				$club->stats->won = 0;
				$club->stats->roles = array();
				$clubs[$club_id] = $club;
			}
			$stats = $clubs[$club_id]->stats;
			$r = new stdClass();
			$r->rating = $rating;
			$r->games = $games;
			$r->won = $won;
			$stats->roles[$role] = $r;
			$stats->rating += $rating;
			$stats->games += $games;
			$stats->won += $won;
		}
		
		// The clubs the player is a member of but never played in.
		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, ni.name, u.flags FROM clubs c' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities i ON i.id = c.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN club_regs u ON u.club_id = c.id' .
			' WHERE u.user_id = ? AND u.club_id NOT IN (SELECT g.club_id FROM players p, games g WHERE p.game_id = g.id AND p.user_id = u.user_id)', $this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $city_name, $user_flags) = $row;
			$club = new stdClass();
			$club->id = $club_id;
			$club->name = $club_name;
			$club->flags = $club_flags;
			$club->city = $city_name;
			$club->member = true;
			$club->user_flags = (int)$user_flags;
			$club->stats = new stdClass();
			$club->stats->rating = 0;
			$club->stats->games = 0;
			$club->stats->won = 0;
			$club->stats->roles = array();
			$clubs[$club_id] = $club;
		}
		
		// Members first, the higher role first; then the most games.
		uasort($clubs, function($a, $b)
		{
			if ($a->member != $b->member)
			{
				return $a->member ? -1 : 1;
			}
			if ($a->user_flags != $b->user_flags)
			{
				return $b->user_flags - $a->user_flags;
			}
			return $b->stats->games - $a->stats->games;
		});
		
		$tiles = array();
		foreach ($clubs as $club)
		{
			$club->extra = '';
			if ($club->member)
			{
				$club->extra .= '<span class="tile-meta">' . club_role_chip($club->user_flags) . '</span>';
			}
			$club->extra .= club_stats_html($club->stats, $role_titles);
			$club->num = array($club->stats->games, get_label('Games'), 'user_games.php?bck=1&id=' . $this->id . '&ccc=' . CCCF_CLUB . $club->id);
			$tiles[] = $club;
		}
		club_tiles_show($tiles, get_label('No clubs found.'));
	}
}

$page = new Page();
$page->run(get_label('Clubs'));

?>