<?php

require_once 'include/general_page_base.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/tournament.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';

define('PAGE_SIZE', TOURNAMENTS_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_EMPTY', 0x0004);
define('FLAG_FILTER_NOT_EMPTY', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NOT_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->future = false;
		if (isset($_REQUEST['future']))
		{
			$this->future = ((int)$_REQUEST['future'] > 0);
		}
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar">';
		echo card_seg(array(
			array(card_url(array('future' => NULL, 'page' => NULL)), get_label('Past'), !$this->future),
			array(card_url(array('future' => 1, 'page' => NULL)), get_label('Future'), $this->future)), get_label('Tournaments'));
		echo '<div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('tournaments')));
		echo '</div><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		if (!$this->future)
		{
			show_checkbox_filter(array(get_label('with video'), get_label('unplayed tournaments'), get_label('canceled tournaments')), $this->filter);
		}
		echo '</div>';
		
		$condition = new SQL(
			' FROM tournaments t' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN cities i ON i.id = a.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON ct.id = a.city_id');
		if ($this->future)
		{
			$condition->add(' WHERE t.start_time + t.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' WHERE t.start_time < UNIX_TIMESTAMP()');
			
			if ($this->filter & FLAG_FILTER_VIDEOS)
			{
				$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($this->filter & FLAG_FILTER_NO_VIDEOS)
			{
				$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($this->filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) AND NOT EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id)');
			}
			if ($this->filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND (EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) OR EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id))');
			}
			if ($this->filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') <> 0');
			}
			if ($this->filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
			}
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND t.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND t.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND t.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND t.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		if ($this->future)
		{
			$order_by = ' ORDER BY t.start_time + t.duration, t.id';
		}
		else
		{
			$order_by = ' ORDER BY t.start_time DESC, t.id DESC';
		}
		
		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, nc.name, c.flags, t.langs, t.num_players, a.id, a.address, a.flags, ni.name,' .
			' (SELECT count(*) FROM games WHERE tournament_id = t.id AND (flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
			' (SELECT count(*) FROM events WHERE tournament_id = t.id AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0) as events,' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		$query->add($order_by);
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);

		$tournaments = array();
		$delim = '';
		$cs_tournaments = '';
		$first_month_tournament = NULL;
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list (
				$tournament->id, $tournament->name, $tournament->flags, $tournament->time, $tournament->duration, $tournament->timezone, 
				$tournament->club_id, $tournament->club_name, $tournament->club_flags, $tournament->languages, $tournament->num_players, 
				$tournament->addr_id, $tournament->addr, $tournament->addr_flags, $tournament->city,
				$tournament->games_count, $tournament->rounds_count, $tournament->videos_count) = $row;
			if ($this->future)
			{
				$m = format_month($tournament->time + $tournament->duration, $tournament->timezone);
			}
			else
			{
				$m = format_month($tournament->time, $tournament->timezone);
			}
			if ($first_month_tournament == NULL || $first_month_tournament->month != $m)
			{
				$tournament->month = $m;
				$tournament->month_count = 1;
				$first_month_tournament = $tournament;
			}
			else
			{
				++$first_month_tournament->month_count;
			}
			
			$tournament->series = array();
			$tournaments[] = $tournament;
			$cs_tournaments .= $delim . $tournament->id;
			$delim = ',';
		}
		
		// Get tournaments series
		if ($cs_tournaments != '')
		{
			$query = new DbQuery(
				'SELECT st.tournament_id, st.stars, s.id, ns.name, s.flags, ' . series_owner_fields() .
				' FROM series_tournaments st' .
				' JOIN series s ON s.id = st.series_id' .
				' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
				' JOIN tournaments t ON t.id = st.tournament_id' .
				series_owner_join() .
				' WHERE st.tournament_id IN (' . $cs_tournaments . ') ' . $order_by . ', s.id DESC');
			$current_tournament = 0;
			while ($row = $query->next())
			{
				list ($tournament_id, $stars, $series_id, $series_name, $series_flags, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags) = $row;
				while ($current_tournament < count($tournaments) && $tournaments[$current_tournament]->id != $tournament_id)
				{
					++$current_tournament;
				}
				if ($current_tournament < count($tournaments))
				{
					$series = new stdClass();
					$series->stars = $stars;
					$series->id = $series_id;
					$series->name = $series_name;
					$series->flags = $series_flags;
					$series->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
					$tournaments[$current_tournament]->series[] = $series;
				}
			}
		}

		// The winners of the tournaments on this page.
		if ($cs_tournaments != '' && !$this->future)
		{
			$winners = array();
			$query = new DbQuery(
				'SELECT p.tournament_id, nu.name FROM tournament_places p' .
				' JOIN users u ON u.id = p.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
				' WHERE p.place = 1 AND p.tournament_id IN (' . $cs_tournaments . ')');
			while ($row = $query->next())
			{
				list ($tournament_id, $winner) = $row;
				if (!isset($winners[$tournament_id]))
				{
					$winners[$tournament_id] = $winner;
				}
			}
			foreach ($tournaments as $tournament)
			{
				if (isset($winners[$tournament->id]))
				{
					$tournament->winner = $winners[$tournament->id];
				}
			}
		}
		
		$now = time();
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		if (count($tournaments) == 0)
		{
			echo '<p class="empty">' . get_label('No tournaments found.') . '</p>';
		}
		else
		{
			echo '<div class="posters">';
		}
		foreach ($tournaments as $tournament)
		{
			if (isset($tournament->month))
			{
				echo '<h3 class="grp">' . $tournament->month . '</h3>';
			}
			
			$playing = ($now >= $tournament->time && $now < $tournament->time + $tournament->duration);
			$canceled = ($tournament->flags & TOURNAMENT_FLAG_CANCELED) != 0;
			$url = 'tournament.php?bck=1&id=' . $tournament->id;
			
			$tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
			$club_pic->set($tournament->club_id, $tournament->club_name, $tournament->club_flags);
			echo '<article class="pcard' . ($canceled ? ' is-off' : '') . '"><div class="pc-cover">' . card_cover($tournament_pic, $club_pic, $tournament->name);
			echo '<span class="pc-date">' . card_short_period($tournament->time, $tournament->duration, $tournament->timezone) . '</span>';
			
			// The stars it has in each of its series, the most of them on the cover.
			$stars = 0;
			$series_names = array();
			foreach ($tournament->series as $series)
			{
				$stars = max($stars, (float)$series->stars);
				$series_names[] = strip_tags($series->name) . ': ' . tournament_stars_str($series->stars, false);
			}
			if ($stars > 0)
			{
				echo '<span class="pc-stars" title="' . htmlspecialchars(implode("\n", $series_names), ENT_QUOTES, 'UTF-8') . '"><span class="stars">' . tournament_stars_str($stars, false) . '</span></span>';
			}
			echo '</div><div class="pc-body"><h3><a href="' . $url . '">' . $tournament->name . '</a></h3>';
			echo '<div class="pc-meta"><span>' . PageBase::icon('pin') . $tournament->city . '</span><span>' . PageBase::icon('users') . $tournament->club_name . '</span></div>';
			
			echo '<div class="pc-foot">';
			if ($canceled)
			{
				echo '<span class="chip off">' . get_label('canceled') . '</span>';
			}
			else if ($playing)
			{
				echo '<span class="chip live">' . get_label('playing now') . '</span>';
			}
			else if (isset($tournament->winner))
			{
				echo '<span class="pc-win">' . PageBase::icon('trophy') . '<span class="dim">' . get_label('Winner') . '</span><b>' . $tournament->winner . '</b></span>';
			}
			echo '<span class="pc-nums">';
			if ($this->future)
			{
				if ($tournament->num_players > 0)
				{
					echo '<span title="' . get_label('Expected number of players') . '">' . PageBase::icon('users') . $tournament->num_players . '</span>';
				}
			}
			else
			{
				if ($tournament->num_players > 0)
				{
					echo '<span title="' . get_label('Players') . '">' . PageBase::icon('users') . $tournament->num_players . '</span>';
				}
				if ($tournament->games_count > 0)
				{
					echo '<a href="tournament_games.php?bck=1&id=' . $tournament->id . '" title="' . get_label('Games') . '">' . PageBase::icon('play') . $tournament->games_count . '</a>';
				}
				if ($tournament->videos_count > 0)
				{
					echo '<a href="tournament_videos.php?bck=1&id=' . $tournament->id . '" title="' . get_label('Videos from [0]', strip_tags($tournament->name)) . '">' . PageBase::icon('video') . $tournament->videos_count . '</a>';
				}
			}
			echo '</span></div></div></article>';
		}
		if (count($tournaments) > 0)
		{
			echo '</div>';
		}
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Tournaments'));

?>