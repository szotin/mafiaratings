<?php

require_once 'include/general_page_base.php';
require_once 'include/user.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/user_location.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/snapshot.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';
require_once 'include/tournament.php';
require_once 'include/series.php';
require_once 'include/cards.php';

define('HOME_NEXT_COUNT', 4);      // "Next up" on the band
define('HOME_SEASON_COUNT', 6);    // long-term tournaments running now, on the band
define('HOME_RECENT_COUNT', 6);    // finished tournaments
define('HOME_SERIES_COUNT', 3);    // series in progress
define('HOME_SERIES_TOP', 3);      // players shown of each series
define('HOME_BEST_COUNT', 10);     // best players
define('HOME_CHANGES_COUNT', 8);   // latest changes in the rating
define('HOME_EVENTS_COUNT', 5);    // latest game nights
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

	private function show_recent_tournaments()
	{
		$tournaments = $this->select_tournaments(
			' AND (t.start_time + t.duration <= ' . $this->now . ' OR (t.flags & ' . TOURNAMENT_FLAG_FINISHED . ') <> 0)',
			't.start_time + t.duration DESC, t.id DESC', HOME_RECENT_COUNT);
		if (count($tournaments) == 0)
		{
			return;
		}
		$this->add_results($tournaments);

		echo '<section class="home-results" aria-labelledby="h-recent"><div class="sec-hd"><h2 id="h-recent">' . get_label('Recent tournaments') . '</h2>';
		echo '<a class="more" href="tournaments.php?bck=1">' . get_label('All tournaments') . '</a></div>';
		echo '<div class="posters">';
		foreach ($tournaments as $t)
		{
			echo '<article class="pcard"><div class="pc-cover">' . $this->cover($t);
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
				echo '<span class="pc-win">' . PageBase::icon('trophy') . '<span class="dim">' . get_label('Winner') . '</span><b>' . $t->winner . '</b></span>';
			}
			else if ($t->num_players > 0)
			{
				echo '<span class="dim" title="' . get_label('Players') . '">' . PageBase::icon('users') . '<b>' . $t->num_players . '</b></span>';
			}
			echo '</div></div></article>';
		}
		echo '</div></section>';
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
		echo '<section class="rail-box home-best" aria-labelledby="h-best"><div class="sec-hd"><h2 id="h-best">' . get_label('Best players') . '</h2>';
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

	// Who moved in the top 100 since the last snapshot that differs from now.
	private function show_changes()
	{
		$snapshot = new Snapshot(time());
		$snapshot->shot();
		$query = new DbQuery('SELECT time, snapshot FROM snapshots ORDER BY time DESC LIMIT 10');
		while ($row = $query->next())
		{
			list ($prev_time, $json) = $row;
			$prev_snapshot = new Snapshot($prev_time, $json);
			$prev_snapshot->load_user_details();
			$diff = $snapshot->compare($prev_snapshot);
			if (count($diff) > 0)
			{
				break;
			}
		}
		if (!isset($diff) || count($diff) == 0)
		{
			return;
		}

		echo '<section class="rail-box home-changes" aria-labelledby="h-changes"><div class="sec-hd"><div><h2 id="h-changes">' . get_label('Rating changes') . '</h2>';
		echo '<p class="sub">' . get_label('since [0]', format_date($prev_time, get_timezone())) . '</p></div></div><ol class="rank-list">';
		$count = 0;
		foreach ($diff as $player)
		{
			if ($count++ >= HOME_CHANGES_COUNT)
			{
				break;
			}
			// The space at the end of a label means the female form, for the languages where it matters.
			$male = ($player->user_flags & USER_FLAG_MALE) != 0;
			$name = $player->user_name;
			if (!isset($player->src))
			{
				$mark = '<span class="mv new">' . get_label('new') . '</span>';
				$title = $male ?
					get_label('[0] entered top 100 and gained [1] place.', $name, $player->dst) :
					get_label('[0] entered top 100 and gained [1] place. ', $name, $player->dst);
			}
			else if (!isset($player->dst))
			{
				$mark = '<span class="mv dn">&#9660;</span>';
				$title = $male ?
					get_label('[0] left top 100.', $name, $player->src) :
					get_label('[0] left top 100. ', $name, $player->src);
			}
			else if ($player->src > $player->dst)
			{
				$mark = '<span class="mv up">&#9650;' . ($player->src - $player->dst) . '</span>';
				$title = $male ?
					get_label('[0] moved up from [1] to [2] place.', $name, $player->src, $player->dst) :
					get_label('[0] moved up from [1] to [2] place. ', $name, $player->src, $player->dst);
			}
			else
			{
				$mark = '<span class="mv dn">&#9660;' . ($player->dst - $player->src) . '</span>';
				$title = $male ?
					get_label('[0] moved down from [1] to [2] place.', $name, $player->src, $player->dst) :
					get_label('[0] moved down from [1] to [2] place. ', $name, $player->src, $player->dst);
			}
			echo '<li title="' . htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8') . '"><span class="n">' . (isset($player->dst) ? $player->dst : '&mdash;') . '</span>';
			echo '<a class="who" href="user_info.php?bck=1&id=' . $player->id . '">' . card_avatar($this->user_pic, $player->id, $player->user_name, $player->user_flags);
			echo '<span class="who-txt"><span class="who-name">' . $player->user_name . '</span><span class="who-sub">' . $player->club_name . '</span></span></a>';
			echo '<span class="v">' . $mark . '</span></li>';
		}
		echo '</ol></section>';
	}

	private function show_events()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT e.id, ne.name, e.flags, e.start_time, e.duration, ct.timezone, t.id, nt.name, c.id, nc.name,' .
			' (SELECT COUNT(*) FROM games g WHERE g.event_id = e.id AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0),' .
			' (SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0)' .
			' FROM events e' .
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON a.id = e.address_id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = e.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE e.start_time <= UNIX_TIMESTAMP() AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_HIDDEN_AFTER) . ') = 0 AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0' .
			' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0)', $this->condition);
		$query->add(' ORDER BY e.start_time DESC, e.id DESC LIMIT ' . HOME_EVENTS_COUNT);

		$row = $query->next();
		if (!$row)
		{
			return;
		}
		echo '<section class="home-events" aria-labelledby="h-events"><div class="sec-hd"><h2 id="h-events">' . get_label('Latest game nights') . '</h2>';
		echo '<a class="more" href="events.php?bck=1">' . get_label('All events') . '</a></div><ul class="elist">';
		do
		{
			list ($id, $name, $flags, $start_time, $duration, $timezone, $tournament_id, $tournament_name, $club_id, $club_name, $games, $players) = $row;
			echo '<li>' . card_dstamp($start_time, $timezone) . '<div class="t"><a href="event.php?bck=1&id=' . $id . '">' . $name . '</a>';
			if ($start_time + $duration > $this->now)
			{
				echo ' <span class="chip live">' . get_label('playing now') . '</span>';
			}
			echo '<div class="meta"><span>' . PageBase::icon('status') . format_date($start_time, $timezone, true) . '</span><span>' . PageBase::icon('users') . $club_name . '</span>';
			if (!is_null($tournament_id))
			{
				echo '<span>' . PageBase::icon('trophy') . $tournament_name . '</span>';
			}
			echo '</div></div><div class="end"><span title="' . get_label('Players') . '">' . PageBase::icon('users') . '<b>' . $players . '</b></span><span title="' . get_label('Games') . '">' . PageBase::icon('play') . $games . '</span></div></li>';
		} while ($row = $query->next());
		echo '</ul></section>';
	}

	protected function show_body()
	{
		if (!$this->band_shown)
		{
			echo '<div class="toolbar"><div class="tb-field">';
			$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', ''));
			echo '</div></div>';
		}
		echo '<div class="cols wide-rail home"><div class="stack">';
		$this->show_adverts();
		$this->show_recent_tournaments();
		$this->show_series();
		echo '</div><aside class="rail">';
		$this->show_best_players();
		$this->show_changes();
		$this->show_events();
		echo '</aside></div>';
	}
}

$page = new Page();
$page->run(get_label('Home'));

?>
