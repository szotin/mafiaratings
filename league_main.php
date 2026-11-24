<?php

require_once 'include/league.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/event.php';
require_once 'include/tournament.php';
require_once 'include/series.php';
require_once 'include/cards.php';

define('LEAGUE_SERIES_COUNT', 4);   // series in progress
define('LEAGUE_SERIES_TOP', 3);     // players shown of each series
define('LEAGUE_NEXT_COUNT', 8);     // tournaments to come
define('LEAGUE_RECENT_COUNT', 6);   // finished tournaments
define('LEAGUE_CLUBS_COUNT', 15);   // clubs on the right

// The main page of a league, made of the pieces of the main page of the site: its series in progress
// with their leaders, the tournaments to come and the ones just played, its managers; the clubs that
// play its tournaments beside them, as the best players there.
class Page extends LeaguePageBase
{
	private $tournament_pic;

	// The series of the league that are on now, each with the top of its standings.
	private function show_series()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration,' .
			' (SELECT COUNT(*) FROM series_places sp WHERE sp.series_id = s.id AND sp.cut_stages = 0) FROM series s' .
			' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
			' WHERE s.league_id = ? AND s.start_time + s.duration > UNIX_TIMESTAMP() AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0' .
			' ORDER BY s.flags & ' . SERIES_FLAG_PINNED . ' DESC, s.start_time + s.duration, ns.name, s.id LIMIT ' . LEAGUE_SERIES_COUNT,
			$this->id);
		$series = array();
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $s->start_time, $s->duration, $s->players) = $row;
			$series[] = $s;
		}
		if (count($series) == 0)
		{
			return;
		}

		$now = time();
		$timezone = get_timezone();
		echo '<section aria-labelledby="h-series"><div class="sec-hd"><h2 id="h-series">' . get_label('Series in progress') . '</h2>';
		echo '<a class="more" href="league_series.php?bck=1&id=' . $this->id . '">' . get_label('All series') . '</a></div>';
		echo '<div class="seasons">';
		foreach ($series as $s)
		{
			$url = 'series_info.php?bck=1&id=' . $s->id;
			$infinite = series_is_infinite($s->start_time);
			echo '<section class="season"><div class="season-hd"><h3><a href="' . $url . '">' . $s->name . '</a></h3>';
			echo '<p class="season-meta">';
			if (!$infinite)
			{
				echo format_date_period($s->start_time, $s->duration, $timezone) . ' ';
			}
			echo $s->start_time <= $now ? '<span class="chip live">' . get_label('playing now') . '</span>' : '<span class="chip">' . get_label('not started yet') . '</span>';
			echo '</p></div>';

			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, nc.name, p.place, p.score FROM series_places p' .
				' JOIN users u ON u.id = p.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
				' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' WHERE p.series_id = ? AND p.cut_stages = 0 ORDER BY p.place, p.user_id LIMIT ' . LEAGUE_SERIES_TOP, $s->id);
			$number = 0;
			while ($row = $query->next())
			{
				list($user_id, $user_name, $user_flags, $user_club, $place, $score) = $row;
				if ($number++ == 0)
				{
					echo '<ol class="rank-list">';
				}
				echo '<li><span class="n">' . sprintf('%02d', $place) . '</span><a class="who" href="series_player.php?bck=1&user_id=' . $user_id . '&id=' . $s->id . '">' . card_avatar($this->user_pic, $user_id, $user_name, $user_flags);
				echo '<span class="who-txt"><span class="who-name">' . $user_name . '</span><span class="who-sub">' . $user_club . '</span></span></a>';
				echo '<span class="v">' . format_score($score, false) . '</span></li>';
			}
			if ($number > 0)
			{
				echo '</ol>';
			}
			echo '<p class="season-foot"><a class="more" href="series_standings.php?bck=1&id=' . $s->id . '">' . get_label('Full standings') . '</a>';
			if ($s->players > 0)
			{
				echo '<span class="dim" title="' . get_label('Players') . '">' . PageBase::icon('users') . $s->players . '</span>';
			}
			echo '</p></section>';
		}
		echo '</div></section>';
	}

	// The tournaments of the series of the league: $future - the ones to come and on now, the earliest
	// first; otherwise the finished ones, the latest first. A tournament is in a few series of the
	// league as often as not: it is one tournament, with the most stars it has in them.
	private function select_tournaments($future, $limit)
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, t.num_players, MAX(st.stars), nct.name, ct.timezone, a.address, na.name, c.id, nc.name, c.flags,' .
			' (SELECT nu.name FROM tournament_places p JOIN users u ON u.id = p.user_id JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
				' WHERE p.tournament_id = t.id AND p.place = 1 LIMIT 1)' .
			' FROM series_tournaments st' .
			' JOIN series s ON s.id = st.series_id' .
			' JOIN tournaments t ON t.id = st.tournament_id' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = t.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE s.league_id = ? AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0',
			$this->id);
		if ($future)
		{
			$query->add(' AND t.start_time + t.duration > UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_FINISHED . ') = 0 GROUP BY t.id ORDER BY t.start_time, t.id');
		}
		else
		{
			$query->add(' AND (t.start_time + t.duration <= UNIX_TIMESTAMP() OR (t.flags & ' . TOURNAMENT_FLAG_FINISHED . ') <> 0) GROUP BY t.id ORDER BY t.start_time DESC, t.id DESC');
		}
		$query->add(' LIMIT ' . $limit);

		$list = array();
		while ($row = $query->next())
		{
			$t = new stdClass();
			list ($t->id, $t->name, $t->flags, $t->start_time, $t->duration, $t->num_players, $t->stars, $t->city, $t->timezone, $addr, $addr_name, $t->club_id, $t->club_name, $t->club_flags, $t->winner) = $row;
			if ($t->name == $addr_name)
			{
				$t->name = $addr;
			}
			$list[] = $t;
		}
		return $list;
	}

	// The tournaments to come, as "Next up" of the main page of the site.
	private function show_next()
	{
		$list = $this->select_tournaments(true, LEAGUE_NEXT_COUNT);
		if (count($list) == 0)
		{
			return;
		}
		$now = time();
		echo '<section aria-labelledby="h-next"><div class="sec-hd"><h2 id="h-next">' . get_label('Next up') . '</h2>';
		echo '<a class="more" href="league_tournaments.php?bck=1&future=1&id=' . $this->id . '">' . get_label('All upcoming tournaments') . '</a></div>';
		echo '<div class="rail-box"><ul class="elist">';
		foreach ($list as $t)
		{
			echo '<li>' . card_dstamp($t->start_time, $t->timezone) . '<div class="t"><a href="tournament.php?bck=1&id=' . $t->id . '">' . $t->name . '</a>';
			if ($t->start_time <= $now)
			{
				echo ' <span class="chip live">' . get_label('playing now') . '</span>';
			}
			echo '<div class="meta"><span>' . PageBase::icon('cal') . format_date_period($t->start_time, $t->duration, $t->timezone) . '</span>';
			echo '<span>' . PageBase::icon('pin') . $t->city . '</span><span>' . PageBase::icon('users') . $t->club_name . '</span></div></div>';
			echo '<div class="end">';
			if ($t->stars > 0)
			{
				echo '<span class="stars">' . tournament_stars_str($t->stars, false) . '</span>';
			}
			echo '</div></li>';
		}
		echo '</ul></div></section>';
	}

	// The tournaments just played, as the recent tournaments of the main page of the site.
	private function show_recent()
	{
		$list = $this->select_tournaments(false, LEAGUE_RECENT_COUNT);
		if (count($list) == 0)
		{
			return;
		}
		echo '<section aria-labelledby="h-recent"><div class="sec-hd"><h2 id="h-recent">' . get_label('Recent tournaments') . '</h2>';
		echo '<a class="more" href="league_tournaments.php?bck=1&id=' . $this->id . '">' . get_label('All tournaments') . '</a></div>';
		echo '<div class="posters">';
		foreach ($list as $t)
		{
			$this->tournament_pic->set($t->id, $t->name, $t->flags);
			$this->club_pic->set($t->club_id, $t->club_name, $t->club_flags);
			echo '<article class="pcard"><div class="pc-cover">' . card_cover($this->tournament_pic, $this->club_pic, $t->name);
			echo '<span class="pc-date">' . card_short_period($t->start_time, $t->duration, $t->timezone) . '</span>';
			if ($t->stars > 0)
			{
				echo '<span class="pc-stars"><span class="stars">' . tournament_stars_str($t->stars, false) . '</span></span>';
			}
			echo '</div><div class="pc-body"><h3><a href="tournament.php?bck=1&id=' . $t->id . '">' . $t->name . '</a></h3>';
			echo '<div class="pc-meta"><span>' . PageBase::icon('pin') . $t->city . '</span><span>' . PageBase::icon('users') . $t->club_name . '</span></div>';
			echo '<div class="pc-foot">';
			if (!is_null($t->winner))
			{
				echo '<span class="pc-win" title="' . get_label('Winner') . ': ' . $t->winner . '">' . PageBase::icon('trophy') . '<b>' . $t->winner . '</b></span>';
			}
			else if ($t->num_players > 0)
			{
				echo '<span class="dim" title="' . get_label('Players') . '">' . PageBase::icon('users') . '<b>' . $t->num_players . '</b></span>';
			}
			echo '</div></div></article>';
		}
		echo '</div></section>';
	}

	// The managers of the league, each in a card as the people of a tournament.
	private function show_managers()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, nc.name' .
			' FROM league_managers m' .
			' JOIN users u ON m.user_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE m.league_id = ? ORDER BY nu.name',
			$this->id);
		$rows = array();
		while ($row = $query->next())
		{
			$rows[] = $row;
		}
		if (count($rows) == 0)
		{
			return;
		}
		echo '<section class="people-sec" aria-labelledby="h-managers"><div class="sec-hd"><h2 id="h-managers">' . get_label('Managers') . '</h2><span class="dim">' . count($rows) . '</span></div><div class="people">';
		foreach ($rows as $row)
		{
			list ($user_id, $user_name, $user_flags, $club_name) = $row;
			$pic = $this->user_pic;
			$pic->set($user_id, $user_name, $user_flags);
			echo '<a class="person" href="user_info.php?bck=1&id=' . $user_id . '">';
			echo '<span class="person-pic">' . PageBase::capture(function() use ($pic) { $pic->show(ICONS_DIR, false, 64, 64); }) . '</span>';
			echo '<span class="person-name"><b>' . $user_name . '</b></span>';
			if (!is_null($club_name))
			{
				echo '<span class="person-sub">' . $club_name . '</span>';
			}
			echo '</a>';
		}
		echo '</div></section>';
	}

	// The clubs that play the tournaments of the league, the most tournaments first, as the best
	// players of the main page of the site.
	private function show_clubs()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, COUNT(DISTINCT g.tournament_id) as _t, COUNT(DISTINCT p.user_id) as _p FROM players p' .
			' JOIN games g ON g.id = p.game_id' .
			' JOIN series_tournaments st ON st.tournament_id = g.tournament_id' .
			' JOIN series s ON s.id = st.series_id' .
			' JOIN clubs c ON c.id = g.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE s.league_id = ? GROUP BY c.id ORDER BY _t DESC, _p DESC LIMIT ' . LEAGUE_CLUBS_COUNT,
			$this->id);
		$row = $query->next();
		if (!$row)
		{
			return;
		}
		echo '<section class="rail-box" aria-labelledby="h-clubs"><div class="sec-hd"><h2 id="h-clubs">' . get_label('Clubs') . '</h2>';
		echo '<a class="more" href="league_clubs.php?bck=1&id=' . $this->id . '">' . get_label('All clubs') . '</a></div><ol class="rank-list">';
		$number = 0;
		do
		{
			list ($club_id, $club_name, $club_flags, $tournaments, $players) = $row;
			++$number;
			$this->club_pic->set($club_id, $club_name, $club_flags);
			echo '<li><span class="n">' . sprintf('%02d', $number) . '</span><a class="who" href="club_main.php?bck=1&id=' . $club_id . '">';
			echo '<span class="av logo"><img src="' . $this->club_pic->url(ICONS_DIR) . '" alt="" loading="lazy"></span>';
			echo '<span class="who-txt"><span class="who-name">' . $club_name . '</span><span class="who-sub">' . get_label('Players') . ': ' . $players . '</span></span></a>';
			echo '<span class="v" title="' . get_label('Tournaments played') . '">' . $tournaments . '</span></li>';
		} while ($row = $query->next());
		echo '</ol></section>';
	}

	protected function show_body()
	{
		$this->tournament_pic = new Picture(TOURNAMENT_PICTURE);

		echo '<div class="cols wide-rail league-home"><div class="stack">';
		$this->show_series();
		$this->show_next();
		$this->show_recent();
		$this->show_managers();
		echo '</div><aside class="rail">';
		$this->show_clubs();
		echo '</aside></div>';
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
