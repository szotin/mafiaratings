<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/event.php';
require_once 'include/currency.php';
require_once 'include/cards.php';
require_once 'include/series_cards.php';
require_once 'include/club_cards.php';

define('CLUB_NEXT_COUNT', 8);      // tournaments and game days to come
define('CLUB_SERIES_COUNT', 6);    // series the club runs now
define('CLUB_BEST_COUNT', 10);     // best players
define('CLUB_ADVERTS_COUNT', 5);

// The main page of a club, made of the pieces of the main page of the site: its adverts, what is to
// come, its series as the tiles of club_series.php, what there is to know about it, its managers as
// the people of a tournament and the clubs it begot; the best players and the figures beside them.
class Page extends ClubPageBase
{
	private function show_adverts()
	{
		$query = new DbQuery(
			'SELECT ct.timezone, n.timestamp, n.message FROM news n' .
				' JOIN clubs c ON c.id = n.club_id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE n.club_id = ? AND n.expires >= UNIX_TIMESTAMP() AND n.timestamp <= UNIX_TIMESTAMP()' .
				' ORDER BY n.timestamp DESC LIMIT ' . CLUB_ADVERTS_COUNT,
			$this->id);
		$row = $query->next();
		if (!$row)
		{
			return;
		}
		echo '<section aria-labelledby="h-adverts"><div class="sec-hd"><h2 id="h-adverts">' . get_label('Adverts') . '</h2></div><ul class="ads">';
		do
		{
			list ($timezone, $timestamp, $message) = $row;
			echo '<li><span class="dim">' . format_date($timestamp, $timezone) . '</span><div class="ad-msg">' . $message . '</div></li>';
		} while ($row = $query->next());
		echo '</ul></section>';
	}

	// The tournaments and game days to come, one list in the order they start, as "Next up" of the
	// main page of the site.
	private function show_next()
	{
		global $_lang;

		$items = array();
		$query = new DbQuery(
			'SELECT t.id, nt.name, t.start_time, t.duration, ct.timezone, na.name FROM tournaments t' .
				' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE t.start_time + t.duration > UNIX_TIMESTAMP() AND t.club_id = ? AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0' .
				' ORDER BY t.start_time, t.id LIMIT ' . CLUB_NEXT_COUNT,
			$this->id);
		while ($row = $query->next())
		{
			$item = new stdClass();
			list ($item->id, $item->name, $item->start_time, $item->duration, $item->timezone, $item->address) = $row;
			$item->url = 'tournament.php?bck=1&id=' . $item->id;
			$item->tournament = NULL;
			$item->is_tournament = true;
			$items[] = $item;
		}

		$query = new DbQuery(
			'SELECT e.id, ne.name, e.start_time, e.duration, ct.timezone, na.name, nt.name FROM events e' .
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . (EVENT_FLAG_HIDDEN_BEFORE | EVENT_FLAG_CANCELED) . ') = 0 AND e.club_id = ?' .
				' ORDER BY e.start_time, e.id LIMIT ' . CLUB_NEXT_COUNT,
			$this->id);
		while ($row = $query->next())
		{
			$item = new stdClass();
			list ($item->id, $item->name, $item->start_time, $item->duration, $item->timezone, $item->address, $item->tournament) = $row;
			$item->url = 'event.php?bck=1&id=' . $item->id;
			$item->is_tournament = false;
			$items[] = $item;
		}
		if (count($items) == 0)
		{
			return;
		}
		usort($items, function($a, $b) { return $a->start_time - $b->start_time; });
		$items = array_slice($items, 0, CLUB_NEXT_COUNT);

		$now = time();
		echo '<section aria-labelledby="h-next"><div class="sec-hd"><h2 id="h-next">' . get_label('Next up') . '</h2>';
		echo '<a class="more" href="club_tournaments.php?bck=1&future=1&id=' . $this->id . '">' . get_label('All upcoming tournaments') . '</a></div>';
		echo '<div class="rail-box"><ul class="elist">';
		foreach ($items as $item)
		{
			echo '<li>' . card_dstamp($item->start_time, $item->timezone) . '<div class="t"><a href="' . $item->url . '">' . $item->name . '</a>';
			if ($item->start_time <= $now)
			{
				echo ' <span class="chip live">' . get_label('playing now') . '</span>';
			}
			echo '<div class="meta">';
			if ($item->is_tournament)
			{
				echo '<span>' . PageBase::icon('cal') . format_date_period($item->start_time, $item->duration, $item->timezone) . '</span>';
			}
			else
			{
				echo '<span>' . PageBase::icon('status') . format_date($item->start_time, $item->timezone, true) . '</span>';
			}
			echo '<span>' . PageBase::icon('pin') . $item->address . '</span>';
			if (!is_null($item->tournament))
			{
				echo '<span>' . PageBase::icon('trophy') . $item->tournament . '</span>';
			}
			echo '</div></div></li>';
		}
		echo '</ul></div></section>';
	}

	// The series the club runs and that are not over, as the tiles of club_series.php.
	private function show_series()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration, s.max_stars,' .
				' (SELECT count(*) FROM series_tournaments _ts JOIN tournaments _t ON _t.id = _ts.tournament_id WHERE _ts.series_id = s.id AND (_t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0)' .
				' FROM series s' .
				' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
				' WHERE s.start_time + s.duration > UNIX_TIMESTAMP() AND s.club_id = ? AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0' .
				' ORDER BY s.flags & ' . SERIES_FLAG_PINNED . ' DESC, s.start_time + s.duration, ns.name, s.id LIMIT ' . CLUB_SERIES_COUNT,
			$this->id);
		$owner = new SeriesOwner(0, $this->id);
		$owner->name = $this->name;
		$owner->flags = $this->flags;
		$list = array();
		while ($row = $query->next())
		{
			$series = new stdClass();
			list ($series->id, $series->name, $series->flags, $series->start_time, $series->duration, $series->max_stars, $series->tournaments) = $row;
			$series->owner = $owner;
			$list[] = $series;
		}
		if (count($list) == 0)
		{
			return;
		}
		echo '<section aria-labelledby="h-series"><div class="sec-hd"><h2 id="h-series">' . get_label('Series') . '</h2>';
		echo '<a class="more" href="club_series.php?bck=1&id=' . $this->id . '">' . get_label('All series') . '</a></div>';
		series_tiles_show($list, '', $this->timezone);
		echo '</section>';
	}

	// What there is to know about the club, with the notes of its managers after it. A manager edits,
	// deletes and moves a note from its menu "≡".
	private function show_info()
	{
		echo '<section aria-labelledby="h-info"><div class="sec-hd"><h2 id="h-info">' . get_label('Information') . '</h2>';
		if ($this->is_manager)
		{
			echo '<button type="button" class="btn" onclick="mr.createNote(' . $this->id . ')">' . PageBase::icon('plus') . '<span>' . get_label('Create [0]', get_label('note')) . '</span></button>';
		}
		echo '</div><div class="rail-box"><dl class="kv">';
		echo '<dt>' . get_label('City') . '</dt><dd>' . $this->city . ', ' . $this->country . '</dd>';
		if ($this->url != '')
		{
			echo '<dt>' . get_label('Web site') . '</dt><dd><a href="' . $this->url . '" target="_blank">' . $this->url . '</a></dd>';
		}
		if ($this->email != '')
		{
			echo '<dt>' . get_label('Contact email') . '</dt><dd><a href="mailto:' . $this->email . '">' . $this->email . '</a></dd>';
		}
		if ($this->phone != '')
		{
			echo '<dt>' . get_label('Contact phone(s)') . '</dt><dd>' . $this->phone . '</dd>';
		}
		echo '<dt>' . get_label('Languages') . '</dt><dd>' . get_langs_str($this->langs, ', ') . '</dd>';
		if (!is_null($this->fee) && !is_null($this->currency_pattern))
		{
			echo '<dt>' . get_label('Admission rate') . '</dt><dd>' . format_currency($this->fee, $this->currency_pattern) . '</dd>';
		}

		$first_note = true;
		$query = new DbQuery('SELECT id, name, value FROM club_info WHERE club_id = ? ORDER BY pos', $this->id);
		while ($row = $query->next())
		{
			list ($note_id, $note_name, $note_value) = $row;
			$note_name = htmlspecialchars($note_name);
			echo '<dt>';
			if ($this->is_manager)
			{
				$items = array(
					array('mr.editNote(' . $note_id . ')', 'edit.png', get_label('Edit note [0]', $note_name)),
					array('mr.deleteNote(' . $note_id . ', ' . json_encode(get_label('Are you sure you want to delete the note?')) . ')', 'delete.png', get_label('Delete note [0]', $note_name)));
				if (!$first_note)
				{
					$items[] = array('mr.upNote(' . $note_id . ')', 'up.png', get_label('Move note [0] up', $note_name));
				}
				echo card_row_menu($items);
			}
			$first_note = false;
			echo $note_name . '</dt><dd>' . prepare_message($note_value) . '</dd>';
		}
		echo '</dl></div></section>';
	}

	// The managers of the club, each in a card as the people of a tournament.
	private function show_managers()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, c.flags'.
			' FROM club_regs c'.
			' JOIN users u ON u.id = c.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE c.club_id = ? AND (c.flags & ' . USER_PERM_MANAGER . ') <> 0 ORDER BY nu.name',
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
		$pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
		echo '<section class="people-sec" aria-labelledby="h-managers"><div class="sec-hd"><h2 id="h-managers">' . get_label('Managers') . '</h2><span class="dim">' . count($rows) . '</span></div><div class="people">';
		foreach ($rows as $row)
		{
			list ($user_id, $user_name, $user_flags, $club_reg_flags) = $row;
			$pic->set($user_id, $user_name, $club_reg_flags, 'c' . $this->id)->set($user_id, $user_name, $user_flags);
			echo '<a class="person" href="user_info.php?bck=1&id=' . $user_id . '">';
			echo '<span class="person-pic">' . PageBase::capture(function() use ($pic) { $pic->show(ICONS_DIR, false, 64, 64); }) . '</span>';
			echo '<span class="person-name"><b>' . $user_name . '</b></span></a>';
		}
		echo '</div></section>';
	}

	// The clubs this one begot, as the tiles of clubs.php.
	private function show_subclubs()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT c.id, n.name, c.flags, ni.name,' .
				' (SELECT count(*) FROM games g WHERE g.club_id = c.id AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0)' .
				' FROM clubs c' .
				' JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0' .
				' JOIN cities i ON i.id = c.city_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
				' WHERE c.parent_id = ? ORDER BY n.name',
			$this->id);
		$clubs = array();
		while ($row = $query->next())
		{
			$club = new stdClass();
			list ($club->id, $club->name, $club->flags, $club->city, $club->games) = $row;
			$clubs[] = $club;
		}
		if (count($clubs) == 0)
		{
			return;
		}
		echo '<section aria-labelledby="h-subclubs"><div class="sec-hd"><h2 id="h-subclubs">' . get_label('Descendant clubs') . '</h2></div>';
		club_tiles_show($clubs, '');
		echo '</section>';
	}

	// The best players of the club, as on the main page of the site.
	private function show_best_players()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT u.id, nu.name, ni.name, u.rating, u.flags, cu.flags' .
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
				' JOIN cities i ON i.id = u.city_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = u.club_id' .
				' WHERE u.club_id = ? AND u.games > 0' .
				' ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id' .
				' LIMIT ' . CLUB_BEST_COUNT,
			$this->id);
		$row = $query->next();
		if (!$row)
		{
			return;
		}
		$pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
		echo '<section class="rail-box" aria-labelledby="h-best"><div class="sec-hd"><h2 id="h-best">' . get_label('Best players') . '</h2>';
		echo '<a class="more" href="club_ratings.php?bck=1&id=' . $this->id . '">' . get_label('All ratings') . '</a></div><ol class="rank-list">';
		$number = 0;
		do
		{
			list ($id, $name, $city_name, $rating, $flags, $club_reg_flags) = $row;
			++$number;
			$pic->set($id, $name, $club_reg_flags, 'c' . $this->id)->set($id, $name, $flags);
			echo '<li><span class="n">' . sprintf('%02d', $number) . '</span><a class="who" href="user_info.php?bck=1&id=' . $id . '">' . card_avatar_of($pic, $id, $name);
			echo '<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city_name . '</span></span></a>';
			echo '<span class="v">' . number_format(USER_INITIAL_RATING + $rating) . '</span></li>';
		} while ($row = $query->next());
		echo '</ol></section>';
	}

	// The figures of the club: how many games, who wins them, who plays and referees them, how long.
	private function show_stats()
	{
		$town = $mafia = $tie = 0;
		$query = new DbQuery('SELECT result, count(*) FROM games WHERE club_id = ? AND (flags & '.GAME_FLAG_CANCELED.') = 0 GROUP BY result', $this->id);
		while ($row = $query->next())
		{
			switch ($row[0])
			{
				case GAME_RESULT_TOWN:
					$town = (int)$row[1];
					break;
				case GAME_RESULT_MAFIA:
					$mafia = (int)$row[1];
					break;
				case GAME_RESULT_TIE:
					$tie = (int)$row[1];
					break;
			}
		}
		$games = $town + $mafia + $tie;
		if ($games == 0)
		{
			return;
		}
		list ($players) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.club_id = ?', $this->id);
		list ($referees) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT moderator_id) FROM games WHERE club_id = ? AND (flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
		list ($average, $shortest, $longest) = Db::record(
			get_label('game'),
			'SELECT AVG(end_time - start_time), MIN(end_time - start_time), MAX(end_time - start_time) ' .
				'FROM games WHERE (flags & '.GAME_FLAG_CANCELED.') = 0 AND club_id = ? AND end_time > start_time + 900 AND end_time < start_time + 20000',
			$this->id);

		$percent = function($n) use ($games) { return number_format($n * 100.0 / $games, 1) . '%'; };
		$figures = array(
			array(number_format($games), get_label('Games played')),
			array(number_format($players), get_label('People played')),
			array(number_format($referees), get_label('Referees')),
			array($percent($mafia), get_label('Mafia wins')),
			array($percent($town), get_label('Town wins')),
			array($percent($tie), get_label('Ties')),
			array(format_time($average), get_label('Average game duration')),
			array(format_time($shortest), get_label('Shortest game')),
			array(format_time($longest), get_label('Longest game')));
		echo '<section class="rail-box" aria-labelledby="h-stats"><div class="sec-hd"><h2 id="h-stats">' . get_label('Stats') . '</h2>';
		echo '<a class="more" href="club_stats.php?bck=1&id=' . $this->id . '">' . get_label('General stats') . '</a></div><dl class="figs">';
		foreach ($figures as $figure)
		{
			echo '<div><dt>' . $figure[1] . '</dt><dd>' . $figure[0] . '</dd></div>';
		}
		echo '</dl></section>';
	}

	protected function show_body()
	{
		echo '<div class="cols wide-rail club-home"><div class="stack">';
		$this->show_adverts();
		$this->show_next();
		$this->show_series();
		$this->show_info();
		$this->show_managers();
		$this->show_subclubs();
		echo '</div><aside class="rail">';
		$this->show_best_players();
		$this->show_stats();
		echo '</aside></div>';
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
