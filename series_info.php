<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/tournament.php';
require_once 'include/scoring.php';
require_once 'include/gaining.php';
require_once 'include/cards.php';

define('SERIES_TOURNAMENTS_COUNT', 12);  // tournaments shown, the latest first
define('SERIES_TOP_COUNT', 10);          // players of the standings beside them

// The main page of a series, made of the pieces of the main page of the site: its tournaments as the
// cards of the recent tournaments there; its standings beside them as the best players there, with
// the strength of its field under them.
class Page extends SeriesPageBase
{
	private function show_tournaments()
	{
		global $_lang;

		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*) FROM series_tournaments WHERE series_id = ?', $this->id);
		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, t.num_players, st.stars, nct.name, ct.timezone, a.address, na.name, c.id, nc.name, c.flags,' .
			' (SELECT nu.name FROM tournament_places p JOIN users u ON u.id = p.user_id JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
				' WHERE p.tournament_id = t.id AND p.place = 1 LIMIT 1)' .
			' FROM series_tournaments st' .
			' JOIN tournaments t ON t.id = st.tournament_id' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = t.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE st.series_id = ?' .
			' ORDER BY t.start_time DESC, t.id DESC LIMIT ' . SERIES_TOURNAMENTS_COUNT,
			$this->id);

		echo '<section aria-labelledby="h-tournaments"><div class="sec-hd"><h2 id="h-tournaments">' . get_label('Tournaments') . '</h2>';
		if ($count > 0)
		{
			echo '<a class="more" href="series_tournaments.php?bck=1&id=' . $this->id . '">' . get_label('All tournaments') . ' (' . $count . ')</a>';
		}
		echo '</div>';
		$row = $query->next();
		if (!$row)
		{
			echo '<p class="empty">' . get_label('No tournaments found.') . '</p></section>';
			return;
		}

		$now = time();
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		echo '<div class="posters">';
		do
		{
			list ($id, $name, $flags, $start_time, $duration, $num_players, $stars, $city, $timezone, $addr, $addr_name, $club_id, $club_name, $club_flags, $winner) = $row;
			if ($name == $addr_name)
			{
				$name = $addr;
			}
			$tournament_pic->set($id, $name, $flags);
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$canceled = ($flags & TOURNAMENT_FLAG_CANCELED) != 0;
			echo '<article class="pcard' . ($canceled ? ' is-off' : '') . '"><div class="pc-cover">' . card_cover($tournament_pic, $this->club_pic, $name);
			echo '<span class="pc-date">' . card_short_period($start_time, $duration, $timezone) . '</span>';
			if ($stars > 0)
			{
				echo '<span class="pc-stars"><span class="stars">' . tournament_stars_str($stars, false) . '</span></span>';
			}
			echo '</div><div class="pc-body"><h3><a href="tournament.php?bck=1&id=' . $id . '">' . $name . '</a></h3>';
			echo '<div class="pc-meta"><span>' . PageBase::icon('pin') . $city . '</span><span>' . PageBase::icon('users') . $club_name . '</span></div>';
			echo '<div class="pc-foot">';
			if ($canceled)
			{
				echo '<span class="chip off">' . get_label('Canceled') . '</span>';
			}
			else if (!is_null($winner))
			{
				echo '<span class="pc-win" title="' . get_label('Winner') . ': ' . $winner . '">' . PageBase::icon('trophy') . '<b>' . $winner . '</b></span>';
			}
			else if ($start_time <= $now && $start_time + $duration > $now)
			{
				echo '<span class="chip live">' . get_label('playing now') . '</span>';
			}
			else if ($num_players > 0)
			{
				echo '<span class="dim" title="' . get_label('Players') . '">' . PageBase::icon('users') . '<b>' . $num_players . '</b></span>';
			}
			echo '</div></div></article>';
		} while ($row = $query->next());
		echo '</div></section>';
	}

	// The top of the standings, as the best players of the main page of the site.
	private function show_standings()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, p.place, p.score, nc.name FROM series_places p' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE p.series_id = ? AND p.cut_stages = 0 ORDER BY p.place, p.user_id LIMIT ' . SERIES_TOP_COUNT,
			$this->id);
		$row = $query->next();
		if (!$row)
		{
			return;
		}
		echo '<section class="rail-box" aria-labelledby="h-standings"><div class="sec-hd"><h2 id="h-standings">' . get_label('Standings') . '</h2>';
		echo '<a class="more" href="series_standings.php?bck=1&id=' . $this->id . '">' . get_label('Full standings') . '</a></div><ol class="rank-list">';
		do
		{
			list ($user_id, $user_name, $user_flags, $place, $score, $club_name) = $row;
			echo '<li><span class="n">' . sprintf('%02d', $place) . '</span><a class="who" href="series_player.php?bck=1&user_id=' . $user_id . '&id=' . $this->id . '">' . card_avatar($this->user_pic, $user_id, $user_name, $user_flags);
			echo '<span class="who-txt"><span class="who-name">' . $user_name . '</span><span class="who-sub">' . $club_name . '</span></span></a>';
			echo '<span class="v">' . format_gain($score) . '</span></li>';
		} while ($row = $query->next());
		echo '</ol></section>';
	}

	// The strength of the field, the same four numbers a tournament shows about itself.
	//
	// They are divided by the number of players the series counts, which is how many stand in its
	// full table - and that is exactly the count a series above divides them by when it counts
	// this one as a competition of its own, so the page says what the number does.
	private function show_properties()
	{
		list ($players_count) = Db::record(get_label('sеriеs'),
			'SELECT count(*) FROM series_places WHERE series_id = ? AND cut_stages = 0', $this->id);
		if ($players_count <= 0)
		{
			return;
		}

		$figures = array(
			array(number_format($players_count), get_label('Players')),
			array(format_rating(1000 + $this->rating_sum / $players_count), get_label('Average rating')),
			array(format_rating(1000 + $this->rating_sum_20 / ($players_count > 20 ? 20 : $players_count)), get_label('Average rating of top 20 players')),
			array(get_label('[0] km', format_rating(convert_distance($this->traveling_distance, GEO_MILES, GEO_KILOMETERS))), get_label('Total traveling distance')),
			array(format_rating($this->guest_coeff), get_label('Guest coefficient')));
		echo '<section class="rail-box" aria-labelledby="h-properties"><div class="sec-hd"><h2 id="h-properties">' . get_label('Properties') . '</h2></div><dl class="figs two">';
		foreach ($figures as $figure)
		{
			echo '<div><dt>' . $figure[1] . '</dt><dd>' . $figure[0] . '</dd></div>';
		}
		echo '</dl></section>';
	}

	protected function show_body()
	{
		echo '<div class="cols wide-rail series-home"><div class="stack">';
		$this->show_tournaments();
		echo '</div><aside class="rail">';
		$this->show_standings();
		$this->show_properties();
		echo '</aside></div>';
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
