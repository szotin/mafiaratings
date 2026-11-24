<?php

require_once 'include/general_page_base.php';
require_once 'include/user.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/user_location.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/stream.php';
require_once 'include/broadcast.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';
require_once 'include/tournament.php';
require_once 'include/series.php';
require_once 'include/cards.php';

define('HOME_NEXT_COUNT', 4);      // "Next up" on the band
define('HOME_SEASON_COUNT', 6);    // long-term tournaments running now, on the band
define('HOME_NOW_COUNT', 12);      // on, within a day or just finished
define('HOME_LATER_COUNT', 3);     // the next ones after that
define('HOME_AIR_COUNT', 8);       // tables on air
define('HOME_SERIES_COUNT', 3);    // series in progress
define('HOME_SERIES_TOP', 3);      // players shown of each series
define('HOME_BEST_COUNT', 10);     // best players
define('HOME_ADVERTS_COUNT', 3);

// The main page of the site, as in the v3 design: what is on now and next on the band; the recent
// tournaments and the series in progress; the best players, the latest changes in the rating and the
// latest game nights beside them.
class Page extends GeneralPageBase
{
	private $tournament_pic;
	private $event_pic;
	private $ccc_filter;
	private $condition;
	private $now;
	private $band_shown = false;

	protected function prepare()
	{
		global $_profile;

		parent::prepare();
		$this->_indexing_policy = INDEXING_POLICY_INDEX;
		$this->now = time();

		$this->tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$this->event_pic = new Picture(EVENT_PICTURE, $this->tournament_pic);

		// The club/city/country filter narrows everything that belongs to a club: tournaments, game
		// nights, adverts, best players. The series are left as they are.
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$this->condition = new SQL();
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$this->condition->add(' AND c.id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$this->condition->add(' AND c.id IN (SELECT club_id FROM club_regs WHERE user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$this->condition->add(' AND c.id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$this->condition->add(' AND c.id IN (SELECT l.id FROM clubs l JOIN cities i ON i.id = l.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
	}

	protected function content_class()
	{
		return 'home-page';
	}

	// ------------------------------------------------------------------ small pieces
	
	// When a tournament that has not started yet starts, relative to today: "Today", "Tomorrow" or the date.
	private function when($t)
	{
		if ($t->start_time <= $this->now)
		{
			return '<span class="rel live">' . get_label('Playing now') . '</span>';
		}
		date_default_timezone_set($t->timezone);
		$days = (int)round((strtotime(date('Y-m-d', $t->start_time)) - strtotime(date('Y-m-d', $this->now))) / 86400);
		if ($days <= 0)
		{
			return '<span class="rel soon">' . get_label('Today') . '</span>';
		}
		if ($days == 1)
		{
			return '<span class="rel soon">' . get_label('Tomorrow') . '</span>';
		}
		return '<span class="rel">' . card_short_period($t->start_time, 1, $t->timezone) . '</span>';
	}
	
	private function cover($t)
	{
		$this->tournament_pic->set($t->id, $t->name, $t->flags);
		$this->club_pic->set($t->club_id, $t->club_name, $t->club_flags);
		return card_cover($this->tournament_pic, $this->club_pic, $t->name);
	}

	// ------------------------------------------------------------------ data

	// Tournaments the main page shows: the ones a league has entered into one of its series, plus
	// whatever the visitor's own clubs are running. A club tournament belonging to nobody the visitor
	// knows stays off the page.
	//
	// Membership comes from club_regs rather than from the profile, whose club list is every club there
	// is when the visitor is an administrator.
	// $everyone - every tournament there is, whoever runs it: the band of the page shows what is on
	// anywhere, as the design has it.
	private function select_tournaments($where, $order, $limit, $everyone = false)
	{
		global $_profile, $_lang;

		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, nc.name, c.flags, nct.name, t.num_players FROM tournaments t' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON a.id = t.address_id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = t.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE (t.flags & ' . (TOURNAMENT_FLAG_HIDE_FROM_MAIN_PAGE | TOURNAMENT_FLAG_CANCELED) . ') = 0 AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0' . $where, $this->condition);
		if (!$everyone)
		{
			$query->add(
				' AND (EXISTS (SELECT st.series_id FROM series_tournaments st' .
				' JOIN series s ON s.id = st.series_id' .
				' WHERE st.tournament_id = t.id AND s.league_id IS NOT NULL)');
			if ($_profile != NULL)
			{
				$query->add(' OR t.club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?)', $_profile->user_id);
			}
			$query->add(')');
		}
		$query->add(' ORDER BY ' . $order . ' LIMIT ' . (int)$limit);

		$tournaments = array();
		while ($row = $query->next())
		{
			$t = new stdClass();
			list(
				$t->id, $t->name, $t->flags, $t->start_time, $t->duration, $t->timezone,
				$t->club_id, $t->club_name, $t->club_flags, $t->city, $t->num_players) = $row;
			$tournaments[] = $t;
		}
		return $tournaments;
	}

	// The winners and the stars of finished tournaments.
	private function add_results($tournaments)
	{
		global $_lang;

		if (count($tournaments) == 0)
		{
			return;
		}
		$by_id = array();
		foreach ($tournaments as $t)
		{
			$t->winner = NULL;
			$t->stars = 0;
			$by_id[(int)$t->id] = $t;
		}
		$ids = implode(', ', array_keys($by_id));

		$query = new DbQuery(
			'SELECT p.tournament_id, nu.name FROM tournament_places p' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
			' WHERE p.place = 1 AND p.tournament_id IN (' . $ids . ')');
		while ($row = $query->next())
		{
			list($tournament_id, $name) = $row;
			if (is_null($by_id[(int)$tournament_id]->winner))
			{
				$by_id[(int)$tournament_id]->winner = $name;
			}
		}

		$query = new DbQuery('SELECT tournament_id, MAX(stars) FROM series_tournaments WHERE tournament_id IN (' . $ids . ') GROUP BY tournament_id');
		while ($row = $query->next())
		{
			list($tournament_id, $stars) = $row;
			$by_id[(int)$tournament_id]->stars = (float)$stars;
		}
	}

	// ------------------------------------------------------------------ the band: on now and next

	protected function show_title()
	{
		$long = TOURNAMENT_FLAG_LONG_TERM;
		$now = $this->now;

		// The tournament of the day: one playing now, or else the next one to start.
		$featured = $this->select_tournaments(
			' AND t.start_time <= ' . $now . ' AND t.start_time + t.duration > ' . $now . ' AND (t.flags & ' . $long . ') = 0',
			't.flags & ' . TOURNAMENT_FLAG_PINNED . ' DESC, t.start_time + t.duration, t.id', 1, true);
		if (count($featured) == 0)
		{
			$featured = $this->select_tournaments(
				' AND t.start_time > ' . $now . ' AND (t.flags & ' . $long . ') = 0',
				't.flags & ' . TOURNAMENT_FLAG_PINNED . ' DESC, t.start_time, t.id', 1, true);
		}
		$featured = count($featured) > 0 ? $featured[0] : NULL;

		$next = $this->select_tournaments(
			' AND t.start_time > ' . $now . ' AND (t.flags & ' . $long . ') = 0' . (is_null($featured) ? '' : ' AND t.id <> ' . (int)$featured->id),
			't.start_time, t.id', HOME_NEXT_COUNT, true);
		$seasons = $this->select_tournaments(
			' AND t.start_time <= ' . $now . ' AND t.start_time + t.duration > ' . $now . ' AND (t.flags & ' . $long . ') <> 0',
			't.flags & ' . TOURNAMENT_FLAG_PINNED . ' DESC, t.start_time + t.duration, t.id', HOME_SEASON_COUNT, true);

		// Nothing on and nothing coming: no band at all. The filter then goes to the top of the page.
		if (is_null($featured) && count($next) == 0 && count($seasons) == 0)
		{
			$this->band_shown = false;
			return;
		}
		$this->band_shown = true;

		echo '<div class="home-top">';
		if (!is_null($featured))
		{
			echo '<p class="eyebrow"><span class="pulse" aria-hidden="true"></span>' . ($featured->start_time <= $now ? get_label('Playing now') : get_label('Coming up')) . '</p>';
		}
		else
		{
			echo '<span></span>';
		}
		echo '<div class="home-filter">';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', ''));
		// What an administrator manages, as on the band of every page of the whole site.
		echo PageBase::manage_menu('', PageBase::get_admin_menu());
		echo '</div></div>';

		echo '<div class="now-grid"><div class="now-main">';
		echo '<h1><span>' . get_label('On now') . '</span> <span class="accent">' . get_label('and next') . '</span></h1>';
		if (!is_null($featured))
		{
			$t = $featured;
			$url = 'tournament.php?bck=1&id=' . $t->id;
			echo '<article class="feat"><div class="feat-cover">' . $this->cover($t) . '<span class="feat-when">' . $this->when($t) . '</span></div>';
			echo '<div class="feat-body"><h2><a href="' . $url . '">' . $t->name . '</a></h2>';
			echo '<div class="feat-meta">';
			echo '<span>' . PageBase::icon('cal') . format_date_period($t->start_time, $t->duration, $t->timezone) . '</span>';
			echo '<span>' . PageBase::icon('pin') . $t->city . '</span>';
			echo '<span>' . PageBase::icon('users') . $t->club_name . '</span>';
			echo '</div>';
			echo '<nav class="feat-links" aria-label="' . htmlspecialchars(strip_tags($t->name), ENT_QUOTES, 'UTF-8') . '">';
			echo '<a class="ql" href="tournament_info.php?bck=1&id=' . $t->id . '">' . get_label('Tournament') . '</a>';
			echo '<a class="ql" href="tournament_standings.php?bck=1&id=' . $t->id . '">' . get_label('Standings') . '</a>';
			echo '<a class="ql" href="tournament_games.php?bck=1&id=' . $t->id . '">' . get_label('Games') . '</a>';
			echo '</nav></div></article>';
		}
		echo '</div>';

		if (count($next) > 0 || count($seasons) > 0)
		{
			echo '<aside class="now-side" aria-label="' . get_label('Next up') . '">';
			if (count($next) > 0)
			{
				echo '<section><h2 class="now-h">' . get_label('Next up') . '</h2><ul class="next-list">';
				foreach ($next as $t)
				{
					echo '<li>' . card_dstamp($t->start_time, $t->timezone) . '<div class="t"><a href="tournament.php?bck=1&id=' . $t->id . '">' . $t->name . '</a>';
					echo '<div class="meta"><span>' . $t->city . '</span><span>' . card_short_period($t->start_time, $t->duration, $t->timezone) . '</span></div></div></li>';
				}
				echo '</ul></section>';
			}
			if (count($seasons) > 0)
			{
				echo '<section><h2 class="now-h">' . get_label('Running all season') . '</h2><ul class="season-list">';
				foreach ($seasons as $t)
				{
					echo '<li><span class="dot-live" aria-hidden="true"></span><a href="tournament.php?bck=1&id=' . $t->id . '">' . $t->name . '</a><span class="dim">' . $t->city . '</span></li>';
				}
				echo '</ul></section>';
			}
			echo '<a class="now-more" href="tournaments.php?future=1&bck=1">' . get_label('All upcoming tournaments') . '</a>';
			echo '</aside>';
		}
		echo '</div>';
	}

	// ------------------------------------------------------------------ the page

	private function show_adverts()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, ct.timezone, n.timestamp, n.message FROM news n' .
			' JOIN clubs c ON c.id = n.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON ct.id = c.city_id WHERE n.expires >= UNIX_TIMESTAMP() AND n.timestamp <= UNIX_TIMESTAMP()', $this->condition);
		$query->add(' ORDER BY n.timestamp DESC LIMIT ' . HOME_ADVERTS_COUNT);

		$row = $query->next();
		if (!$row)
		{
			return;
		}
		echo '<section class="home-adverts" aria-labelledby="h-adverts"><div class="sec-hd"><h2 id="h-adverts">' . get_label('Adverts') . '</h2></div>';
		echo '<ul class="ads">';
		do
		{
			list ($club_id, $club_name, $club_flags, $timezone, $timestamp, $message) = $row;
			$this->club_pic->set($club_id, $club_name, $club_flags);
			echo '<li><a class="ad-club" href="club_main.php?bck=1&id=' . $club_id . '"><img src="' . $this->club_pic->url(ICONS_DIR) . '" alt=""><span><b>' . $club_name . '</b><span>' . format_date($timestamp, $timezone) . '</span></span></a>';
			echo '<div class="ad-msg">' . $message . '</div></li>';
		} while ($row = $query->next());
		echo '</ul></section>';
	}

	private function show_series()
	{
		global $_profile, $_lang;

		// Every league series, and of the club ones only those run by a club the visitor belongs to.
		// soc is the owning club from series_owner_join(), so a closed one is left out.
		//
		// Recurring tournaments never end, so they would hold these few places forever. They belong to
		// the main page of the club or the league running them, not to this one.
		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration, ' . series_owner_fields() . ',' .
			' (SELECT COUNT(*) FROM series_places sp WHERE sp.series_id = s.id) FROM series s' .
			' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
			series_owner_join() .
			' WHERE s.start_time + s.duration > UNIX_TIMESTAMP() AND s.start_time <> ' . SERIES_INFINITE_START .
			' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0' .
			' AND (s.league_id IS NOT NULL');
		if ($_profile != NULL)
		{
			$query->add(
				' OR (s.club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?)' .
				' AND (soc.flags & ' . CLUB_FLAG_CLOSED . ') = 0)', $_profile->user_id);
		}
		$query->add(')');
		$query->add(' ORDER BY s.flags & ' . SERIES_FLAG_PINNED .' DESC, s.start_time + s.duration, ns.name, s.id LIMIT ' . HOME_SERIES_COUNT);

		$series = array();
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $s->start_time, $s->duration, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $s->players) = $row;
			$s->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$series[] = $s;
		}
		if (count($series) == 0)
		{
			return;
		}

		$timezone = get_timezone();
		echo '<section class="home-seasons" aria-labelledby="h-seasons"><div class="sec-hd"><h2 id="h-seasons">' . get_label('Series in progress') . '</h2>';
		echo '<a class="more" href="series.php?bck=1">' . get_label('All series') . '</a></div>';
		echo '<div class="seasons">';
		foreach ($series as $s)
		{
			$url = 'series_standings.php?bck=1&id=' . $s->id;
			$started = $s->start_time <= $this->now;
			echo '<section class="season"><div class="season-hd"><p class="sub">' . $s->owner->name . '</p><h3><a href="' . $url . '">' . $s->name . '</a></h3>';
			echo '<p class="season-meta">' . format_date_period($s->start_time, $s->duration, $timezone) . ' ';
			echo $started ? '<span class="chip live">' . get_label('playing now') . '</span>' : '<span class="chip">' . get_label('not started yet') . '</span>';
			echo '</p></div>';

			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, nc.name, p.score FROM series_places p' .
				' JOIN users u ON u.id = p.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
				' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' WHERE p.series_id = ? ORDER BY p.place, p.user_id LIMIT ' . HOME_SERIES_TOP, $s->id);
			$number = 0;
			while ($row = $query->next())
			{
				list($user_id, $user_name, $user_flags, $user_club, $score) = $row;
				if ($number++ == 0)
				{
					echo '<ol class="rank-list">';
				}
				echo '<li><span class="n">' . sprintf('%02d', $number) . '</span><a class="who" href="series_player.php?bck=1&user_id=' . $user_id . '&id=' . $s->id . '">' . card_avatar($this->user_pic, $user_id, $user_name, $user_flags);
				echo '<span class="who-txt"><span class="who-name">' . $user_name . '</span><span class="who-sub">' . $user_club . '</span></span></a>';
				echo '<span class="v">' . format_score($score, false) . '</span></li>';
			}
			if ($number > 0)
			{
				echo '</ol>';
			}
			echo '<p class="season-foot"><a class="more" href="' . $url . '">' . get_label('Full standings') . '</a>';
			if ($s->players > 0)
			{
				echo '<span class="dim" title="' . get_label('Players') . '">' . PageBase::icon('users') . $s->players . '</span>';
			}
			echo '</p></section>';
		}
		echo '</div></section>';
	}

	private function show_best_players()
	{
		global $_profile, $_lang;

		$query = new DbQuery('SELECT u.id, nu.name, ni.name, u.rating, u.flags'.
			' FROM users u'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN cities i ON i.id = u.city_id'.
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
			' WHERE u.games > 0');
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM club_regs WHERE club_id = ?)', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM club_regs WHERE club_id IN (' . $_profile->get_comma_sep_clubs() . '))');
			}
			break;
		case CCCF_CITY:
			$query->add(' AND u.city_id = ?', $ccc_id);
			break;
		case CCCF_COUNTRY:
			$query->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		$query->add(' ORDER BY u.rating DESC, u.games, u.games_won, u.id LIMIT ' . HOME_BEST_COUNT);

		$row = $query->next();
		if (!$row)
		{
			return;
		}
		echo '<section class="rail-box home-best" aria-labelledby="h-best"><div class="sec-hd"><div><h2 id="h-best">' . get_label('Best players') . '</h2>' .
			($this->ccc_filter->get_id() == CCCF_ALL ? '<p class="sub">' . get_label('Overall rating, all clubs') . '</p>' : '') . '</div>';
		echo '<a class="more" href="ratings.php?bck=1">' . get_label('All ratings') . '</a></div><ol class="rank-list">';
		$number = 0;
		do
		{
			list ($id, $name, $city_name, $rating, $flags) = $row;
			++$number;
			echo '<li><span class="n">' . sprintf('%02d', $number) . '</span><a class="who" href="user_info.php?bck=1&id=' . $id . '">' . card_avatar($this->user_pic, $id, $name, $flags);
			echo '<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city_name . '</span></span></a>';
			echo '<span class="v">' . number_format(USER_INITIAL_RATING + $rating) . '</span></li>';
		} while ($row = $query->next());
		echo '</ol></section>';
	}

	// ------------------------------------------------------------------ happening now

	// The tournaments and the game days to show in "Happening now", read with $where: the ones that
	// are on, start within a day or have just finished, or - with $later - the next ones after
	// that. A tournament played all season is not one of them (the band has those, "Running all
	// season"), its evenings are; the rounds of a short tournament are the tournament itself.
	private function select_now($where, $order, $limit)
	{
		global $_lang;

		$items = array();
		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, nc.name, nct.name FROM tournaments t' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = t.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON a.id = t.address_id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' WHERE (t.flags & ' . (TOURNAMENT_FLAG_CANCELED | TOURNAMENT_FLAG_LONG_TERM) . ') = 0 AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0' .
			str_replace('{s}', 't.start_time', str_replace('{e}', 't.start_time + t.duration', $where)), $this->condition);
		$query->add(' ORDER BY ' . str_replace('{s}', 't.start_time', $order) . ' LIMIT ' . $limit);
		while ($row = $query->next())
		{
			$i = new stdClass();
			list ($i->id, $i->name, $flags, $i->start, $i->duration, $i->timezone, $i->club_id, $i->club, $i->city) = $row;
			$i->is_tournament = true;
			$i->finished = ($flags & TOURNAMENT_FLAG_FINISHED) != 0;
			$i->of = NULL;
			$items[] = $i;
		}

		$query = new DbQuery(
			'SELECT e.id, ne.name, e.flags, e.start_time, e.duration, ct.timezone, c.id, nc.name, nct.name, nt.name FROM events e' .
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = e.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON a.id = e.address_id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' WHERE (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_HIDDEN_BEFORE) . ') = 0 AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0' .
			' AND (e.tournament_id IS NULL OR (t.flags & ' . TOURNAMENT_FLAG_LONG_TERM . ') <> 0)' .
			str_replace('{s}', 'e.start_time', str_replace('{e}', 'e.start_time + e.duration', $where)), $this->condition);
		$query->add(' ORDER BY ' . str_replace('{s}', 'e.start_time', $order) . ' LIMIT ' . $limit);
		while ($row = $query->next())
		{
			$i = new stdClass();
			list ($i->id, $i->name, $flags, $i->start, $i->duration, $i->timezone, $i->club_id, $i->club, $i->city, $i->of) = $row;
			$i->is_tournament = false;
			$i->finished = ($flags & EVENT_FLAG_FINISHED) != 0;
			$items[] = $i;
		}

		usort($items, function($a, $b) { return $a->start - $b->start; });
		return array_slice($items, 0, $limit);
	}

	// When a row starts, on the stamp beside it: the time, and over it today, tomorrow or the date.
	private function now_stamp($i)
	{
		date_default_timezone_set($i->timezone);
		$days = (int)round((strtotime(date('Y-m-d', $i->start)) - strtotime(date('Y-m-d', $this->now))) / 86400);
		if ($days == 0)
		{
			$day = get_label('Today');
		}
		else if ($days == 1)
		{
			$day = get_label('Tomorrow');
		}
		else
		{
			$day = card_short_period($i->start, 1, $i->timezone);
		}
		return '<span class="nstamp" aria-hidden="true"><b>' . date('H:i', $i->start) . '</b><small>' . $day . '</small></span>';
	}

	// One row of "Happening now". $state: 'live', 'soon', 'done' or 'later'.
	private function now_row($i, $state)
	{
		if ($i->is_tournament)
		{
			$url = 'tournament.php?bck=1&id=' . $i->id;
			$kind = PageBase::icon('trophy') . get_label('Tournament');
		}
		else
		{
			$url = 'event.php?bck=1&id=' . $i->id;
			$kind = PageBase::icon('cal') . get_label('Game day');
		}
		echo '<li class="nrow is-' . $state . '">' . $this->now_stamp($i) . '<div class="t"><div class="nrow-hd"><span class="nkind">' . $kind . '</span>';
		switch ($state)
		{
		case 'live':
			echo '<span class="nst live">' . get_label('playing now') . '</span>';
			break;
		case 'soon':
		case 'later':
			$minutes = (int)ceil(($i->start - $this->now) / 60);
			if ($minutes < 60)
			{
				echo '<span class="nst soon">' . get_label('in [0] min', $minutes) . '</span>';
			}
			else if ($minutes < 48 * 60)
			{
				echo '<span class="nst soon">' . get_label('in [0] h', (int)round($minutes / 60)) . '</span>';
			}
			break;
		case 'done':
			echo '<span class="nst done">' . get_label('ended') . '</span>';
			break;
		}
		echo '</div><a class="nrow-name" href="' . $url . '">' . $i->name . '</a>';
		echo '<div class="meta"><span>' . PageBase::icon('users') . $i->club . '</span><span>' . PageBase::icon('pin') . $i->city . '</span>';
		if (!is_null($i->of))
		{
			echo '<span>' . PageBase::icon('trophy') . $i->of . '</span>';
		}
		echo '</div>';
		// What there is to look at while it is on and once it is over: the standings, the games, and
		// while it is on the text broadcast of its tables.
		if ($state == 'live' || $state == 'done')
		{
			$what = $i->is_tournament ? 'tournament' : 'event';
			echo '<div class="nrow-links">';
			echo '<a class="ql" href="' . $what . '_standings.php?bck=1&id=' . $i->id . '">' . get_label('Standings') . '</a>';
			echo '<a class="ql" href="' . $what . '_games.php?bck=1&id=' . $i->id . '">' . get_label('Games') . '</a>';
			if ($state == 'live')
			{
				echo '<a class="ql bc" href="text_broadcast.php?' . $what . '_id=' . $i->id . '" target="_blank">' . PageBase::icon('video') . get_label('Watch in text') . '</a>';
			}
			echo '</div>';
		}
		echo '</div></li>';
	}

	// "Happening now": what is on, what starts within a day and what has just finished, then the
	// next few after that - in place of the tournaments of the past the page used to show.
	private function show_now()
	{
		$day = 24 * 60 * 60;
		$items = $this->select_now(
			' AND {s} <= ' . ($this->now + $day) . ' AND {e} >= ' . ($this->now - $day), '{s}', HOME_NOW_COUNT);
		$groups = array('live' => array(), 'soon' => array(), 'done' => array());
		foreach ($items as $i)
		{
			if ($i->start > $this->now)
			{
				$groups['soon'][] = $i;
			}
			else if ($i->finished || $i->start + $i->duration <= $this->now)
			{
				$groups['done'][] = $i;
			}
			else
			{
				$groups['live'][] = $i;
			}
		}
		$later = $this->select_now(' AND {s} > ' . ($this->now + $day), '{s}', HOME_LATER_COUNT);

		echo '<section class="home-now" aria-labelledby="h-now"><div class="sec-hd"><div><h2 id="h-now">' . get_label('Happening now') . '</h2>';
		echo '<p class="sub">' . get_label('Tournaments and game days that are on, start within a day or have just finished') . '</p></div>';
		echo '<a class="more" href="events.php?bck=1&future=1">' . get_label('All events') . '</a></div>';

		$titles = array(
			'live' => get_label('Going on now'),
			'soon' => get_label('Starting within a day'),
			'done' => get_label('Just finished'));
		$any = false;
		foreach ($groups as $state => $list)
		{
			if (count($list) == 0)
			{
				continue;
			}
			$any = true;
			echo '<h3 class="ngroup g-' . $state . '">' . $titles[$state] . '</h3><ul class="nlist">';
			foreach ($list as $i)
			{
				$this->now_row($i, $state);
			}
			echo '</ul>';
		}
		if (!$any)
		{
			echo '<p class="nquiet">' . PageBase::icon('status') . get_label('Quiet now: nothing is on, nothing starts within a day and nothing has just finished.') . '</p>';
		}
		if (count($later) > 0)
		{
			echo '<h3 class="ngroup g-later">' . get_label('After that') . '</h3><ul class="nlist">';
			foreach ($later as $i)
			{
				$this->now_row($i, 'later');
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	// ------------------------------------------------------------------ on air

	// The tables that can be read in words right now, the whole site over, each with its light: a
	// game is on, the table is between games, or nothing has been played there yet - the same
	// three the page of the broadcasts says (see include/broadcast.php).
	private function show_on_air()
	{
		$scope = broadcast_site_scope();
		$live_keys = stream_live_keys($scope->condition);
		$played_keys = stream_played_keys($scope->condition, $this->now);
		$rows = array();
		foreach (broadcast_live_competitions($scope) as $competition)
		{
			foreach (stream_text_tables($competition->owner) as $table_num)
			{
				$key = stream_live_key($competition->tournament_id, $competition->event_id, $table_num);
				$r = new stdClass();
				$r->state = isset($live_keys[$key]) ? 0 : (isset($played_keys[$key]) ? 1 : 2);
				$r->url = 'text_broadcast.php?' . $competition->param . '&table_num=' . $table_num;
				$r->name = $competition->name;
				$r->club = $competition->club_name;
				$r->table = $table_num;
				$rows[] = $r;
			}
		}
		usort($rows, function($a, $b) { return $a->state - $b->state; });
		$rows = array_slice($rows, 0, HOME_AIR_COUNT);

		echo '<section class="rail-box home-live" aria-labelledby="h-live"><div class="sec-hd"><div><h2 id="h-live">' . get_label('On air') . '</h2>';
		echo '<p class="sub">' . get_label('Text broadcasts from the tables') . '</p></div>';
		echo '<a class="more" href="broadcasts.php?bck=1">' . get_label('Broadcasts') . '</a></div>';
		if (count($rows) == 0 || $rows[0]->state != 0)
		{
			echo '<p class="bcnone">' . get_label('No table is playing right now - a broadcast comes alive as soon as a game starts.') . '</p>';
		}
		if (count($rows) > 0)
		{
			$lights = array('on', 'idle', 'off');
			$words = array(get_label('Game on'), get_label('Between games'), get_label('No games yet'));
			echo '<ul class="bclist">';
			foreach ($rows as $r)
			{
				$where = ($r->club != '' ? $r->club . ' · ' : '') . get_label('Table [0]', $r->table) . ' · ' . $words[$r->state];
				echo '<li class="bcrow"><span class="bcl ' . $lights[$r->state] . '" aria-hidden="true"></span>';
				echo '<div class="bct"><a href="' . $r->url . '" target="_blank">' . $r->name . '</a><span class="dim">' . $where . '</span></div>';
				echo '<a class="bcgo" href="' . $r->url . '" target="_blank" title="' . get_label('Watch in text') . '" aria-label="' . htmlspecialchars(strip_tags($r->name), ENT_QUOTES, 'UTF-8') . '">' . PageBase::icon('chev-r') . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	protected function show_body()
	{
		if (!$this->band_shown)
		{
			echo '<div class="toolbar"><div class="tb-field">';
			$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', ''));
			echo '</div>' . PageBase::manage_menu('', PageBase::get_admin_menu()) . '</div>';
		}
		echo '<div class="cols wide-rail home"><div class="stack">';
		$this->show_adverts();
		$this->show_now();
		$this->show_series();
		echo '</div><aside class="rail">';
		$this->show_on_air();
		$this->show_best_players();
		echo '</aside></div>';
	}
}

$page = new Page();
$page->run(get_label('Home'));

?>
