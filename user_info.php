<?php

require_once 'include/languages.php';
require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';
require_once 'include/tournament.php';
require_once 'include/cards.php';

define('MAX_POINTS_ON_GRAPH', 50);
define('MIN_PERIOD_ON_GRAPH', 10*24*60*60);

define('RATINGS_BEFORE', 5);
define('RATINGS_AFTER', 5);

define('USER_ACHIEVEMENTS_COUNT', 10); // the cards of the best places in tournaments

// The main page of a player, made of the pieces of the main page of the site: their figures, the
// chart of their rating and their best places in tournaments as cards; where they stand in the
// rating beside them, as the best players there.
class Page extends UserPageBase
{
	protected function add_headers()
	{
		parent::add_headers();
		add_chart_headers();
	}
	
	// The figures of the player: games, wins, games refereed. The rating is on the band.
	private function show_figures($games, $won, $rating)
	{
		if ($games <= 0 && $this->games_moderated <= 0)
		{
			return;
		}
		$figures = array();
		if ($games > 0)
		{
			$figures[] = array(number_format($games), get_label('Games played'));
			$figures[] = array(number_format($won * 100.0 / $games, 1) . '%', get_label('Wins'));
		}
		if ($this->games_moderated > 0)
		{
			$figures[] = array(number_format($this->games_moderated), get_label('Games refereed'));
		}
		echo '<dl class="figs user-figs">';
		foreach ($figures as $figure)
		{
			echo '<div><dt>' . $figure[1] . '</dt><dd>' . $figure[0] . '</dd></div>';
		}
		echo '</dl>';
	}

	// The places of the player in tournaments, the most important first: each a medal with the place
	// on it, the tournament small under it - it is the places that are looked at here.
	private function show_achievements()
	{
		global $_lang;

		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, nc.name, c.flags, tp.place,' .
			' (SELECT MAX(stars) FROM series_tournaments WHERE tournament_id = t.id) as stars' .
			' FROM tournament_places tp' .
			' JOIN tournaments t ON t.id = tp.tournament_id' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = t.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON a.id = t.address_id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' WHERE tp.user_id = ? ORDER BY tp.importance DESC, tp.place LIMIT ' . USER_ACHIEVEMENTS_COUNT, $this->id);
		$row = $query->next();
		if (!$row)
		{
			return;
		}
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		echo '<section aria-labelledby="h-achievements"><div class="sec-hd"><h2 id="h-achievements">' . get_label('Achievements') . '</h2>';
		echo '<a class="more" href="user_tournaments.php?bck=1&id=' . $this->id . '">' . get_label('All tournaments') . '</a></div><div class="achs">';
		do
		{
			list($id, $name, $flags, $start_time, $duration, $timezone, $club_id, $club_name, $club_flags, $place, $stars) = $row;
			$tournament_pic->set($id, $name, $flags);
			// The place first and big, as a medal; the tournament small under it.
			echo '<a class="ach' . ($place <= 3 ? ' p' . $place : '') . '" href="tournament.php?bck=1&id=' . $id . '" title="' . htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8') . '">';
			echo '<span class="ach-medal">' . $place . '</span>';
			echo '<span class="ach-place">' . ($place == 1 ? get_label('Winner') : get_label('[0] place', $place)) . '</span>';
			echo '<span class="ach-t">' . card_logo($tournament_pic, $name) . '<span>' . $name . '</span></span>';
			echo '<span class="ach-sub">';
			if ($stars > 0)
			{
				echo '<span class="stars">' . tournament_stars_str($stars, false) . '</span>';
			}
			echo format_month($start_time + $duration, $timezone) . '</span></a>';
		} while ($row = $query->next());
		echo '</div></section>';
	}

	// What there is to know about the player, and for a manager of their club how to reach them.
	private function show_info()
	{
		echo '<section class="rail-box" aria-labelledby="h-info"><div class="sec-hd"><h2 id="h-info">' . get_label('Information') . '</h2></div><dl class="kv">';
		echo '<dt>' . get_label('Languages') . '</dt><dd>' . get_langs_str($this->langs, ', ') . '</dd>';
		echo '<dt>' . get_label('Gender') . '</dt><dd>' . (($this->flags & USER_FLAG_MALE) != 0 ? get_label('male') : get_label('female')) . '</dd>';

		$nicks = array();
		$query = new DbQuery('SELECT DISTINCT nickname FROM event_regs WHERE user_id = ? ORDER BY nickname', $this->id);
		while ($row = $query->next())
		{
			if (!empty($row[0]))
			{
				$nicks[] = cut_long_name($row[0], 88);
			}
		}
		if (count($nicks) > 0)
		{
			echo '<dt>' . get_label('Nicks') . '</dt><dd>' . implode(', ', $nicks) . '</dd>';
		}

		if (is_permitted(PERMISSION_CLUB_MANAGER, $this->club_id))
		{
			echo '<dt>' . get_label('Email') . '</dt><dd><a href="mailto:' . $this->email . '">' . $this->email . '</a></dd>';
			$status = ($this->flags & USER_FLAG_NO_PASSWORD) ? get_label('not activated') : get_label('activated');
			$query = new DbQuery('SELECT flags FROM club_regs WHERE user_id = ? AND club_id = ?', $this->id, $this->club_id);
			if ($row = $query->next())
			{
				list($club_flags) = $row;
				$status .= '; ' . (($club_flags & USER_CLUB_FLAG_SUBSCRIBED) ? get_label('subscribed') : get_label('not subscribed'));
			}
			echo '<dt>' . get_label('Account status') . '</dt><dd>' . $status . '</dd>';
		}
		echo '</dl></section>';
	}

	// The players around this one in the rating, as the best players of the main page; this one
	// marked.
	private function show_rating_position($rating_pos)
	{
		global $_lang;

		$first = max(0, $rating_pos - RATINGS_BEFORE);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.rating, u.flags, ni.name' .
			' FROM users u' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' WHERE u.games > 0 ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $first . ',' . (RATINGS_BEFORE + RATINGS_AFTER + 1));
		echo '<section class="rail-box" aria-labelledby="h-position"><div class="sec-hd"><h2 id="h-position">' . get_label('Rating position') . '</h2>';
		echo '<a class="more" href="ratings.php?bck=1&page=-' . $this->id . '">' . get_label('All ratings') . '</a></div><ol class="rank-list">';
		$number = $first;
		while ($row = $query->next())
		{
			list ($id, $name, $rating, $flags, $city) = $row;
			++$number;
			echo '<li' . ($id == $this->id ? ' class="me"' : '') . '><span class="n">' . $number . '</span><a class="who" href="user_info.php?bck=1&id=' . $id . '">' . card_avatar($this->user_pic, $id, $name, $flags);
			echo '<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city . '</span></span></a>';
			echo '<span class="v">' . number_format(USER_INITIAL_RATING + $rating) . '</span></li>';
		}
		echo '</ol></section>';
	}

	protected function show_body()
	{
		$rating_pos = -1;
		$rating = $games = $won = 0;
		$query = new DbQuery('SELECT rating, games, games_won FROM users WHERE id = ?', $this->id);
		if ($row = $query->next())
		{
			list ($rating, $games, $won) = $row;
			if ($games > 0)
			{
				list ($rating_pos) = Db::record(get_label('rating'),
					'SELECT count(*) FROM users WHERE games > 0 AND (rating > ? OR (rating = ? AND (games < ? OR (games = ? AND (games_won > ? OR (games_won = ? AND id < ?))))))',
					$rating, $rating, $games, $games, $won, $won, $this->id);
			}
		}

		echo '<div class="cols wide-rail user-home"><div class="stack">';
		// The figures, and the chart of the rating under them, in one box.
		if ($games > 0 || $this->games_moderated > 0)
		{
			echo '<section class="rail-box chart-box">';
			$this->show_figures($games, $won, $rating);
			if ($games > 0)
			{
				show_chart(640, 396); // fibonacci golden ratio 1.618:1
			}
			echo '</section>';
		}
		$this->show_achievements();
		$this->show_info();
		echo '</div><aside class="rail">';
		if ($rating_pos >= 0)
		{
			$this->show_rating_position($rating_pos);
		}
		echo '</aside></div>';
	}

	protected function js_on_load()
	{
		parent::js();
		list ($games) = Db::record(get_label('user'), 'SELECT games FROM users WHERE id = ?', $this->id);
		if ($games <= 0)
		{
			return; // no games, no chart
		}
?>
		chartParams.type = "rating";
		chartParams.name = "<?php echo get_label('[0] rating chart', $this->name); ?>";
		chartParams.players = "<?php echo $this->id; ?>";
		chartParams.charts = 1;
		initChart("<?php echo get_label('Rating'); ?>");
<?php
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>