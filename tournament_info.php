<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/utilities.php';
require_once 'include/scoring.php';
require_once 'include/cards.php';

define('USER_COLUMN_COUNT', 6);
define('USER_COLUMN_WIDTH', (100 / USER_COLUMN_COUNT));

class Page extends TournamentPageBase
{
	private $people_pic;

	private function show_details()
	{
		global $_page, $_lang, $_profile;
		
		$now = time();
		
		list($games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE tournament_id = ? AND (flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING, $this->id);
		
		$players = array();
		$referees = array();
		$applications = array();
		if ($games_count > 0)
		{
			$query = new DbQuery(
				'SELECT DISTINCT u.id, nu.name, u.flags, c.id, ncl.name, c.flags, tu.flags, cu.flags, nc.name, ct.lat, ct.lon, tu.rating' . 
				' FROM players p' . 
				' JOIN users u ON u.id = p.user_id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN games g ON p.game_id = g.id' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' . 
				' LEFT OUTER JOIN names ncl ON ncl.id = c.name_id AND (ncl.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = g.tournament_id' .
				' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = g.club_id' .
				' JOIN cities ct ON ct.id = tu.city_id' .
				' JOIN names nc ON nc.id = ct.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' WHERE g.tournament_id = ? ORDER BY nu.name', $this->id);
			while ($row = $query->next())
			{
				$players[] = $row;
			}
			
			$query = new DbQuery(
				'SELECT DISTINCT u.id, nu.name, u.flags, c.id, ncl.name, c.flags, tu.flags, cu.flags' . 
				' FROM games g' . 
				' JOIN users u ON u.id = g.moderator_id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' . 
				' LEFT OUTER JOIN names ncl ON ncl.id = c.name_id AND (ncl.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = g.tournament_id' .
				' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = g.club_id' .
				' WHERE g.tournament_id = ? ORDER BY nu.name', $this->id);
			while ($row = $query->next())
			{
				$referees[] = $row;
			}
		}
		else
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, c.id, ncl.name, c.flags, tu.flags, cu.flags, nc.name, ct.lat, ct.lon, tu.rating' . 
				' FROM tournament_regs tu' . 
				' JOIN users u ON tu.user_id = u.id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' . 
				' LEFT OUTER JOIN names ncl ON ncl.id = c.name_id AND (ncl.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = ?' .
				' JOIN cities ct ON ct.id = tu.city_id' .
				' JOIN names nc ON nc.id = ct.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' WHERE tu.tournament_id = ? ORDER BY nu.name', $this->club_id, $this->id);
			while ($row = $query->next())
			{
				$user_tournament_flags = (int)$row[6];
				if ($user_tournament_flags & USER_TOURNAMENT_FLAG_NOT_ACCEPTED)
				{
					$applications[] = $row;
				}
				else
				{
					if ($user_tournament_flags & USER_PERM_REFEREE)
					{
						$referees[] = $row;
					}
					
					if ($user_tournament_flags & USER_PERM_PLAYER)
					{
						$players[] = $row;
					}
				}
			}
		}
		
		$this->show_people(get_label('Participants'), $players, $games_count > 0, true);
		$this->show_people(get_label('Applicants'), $applications, $games_count > 0, true);
		$this->show_people(get_label('Referees'), $referees, $games_count > 0, false);
		
	}

	// The top of the standings, as the best players of the main page of the site. Whatever the
	// tournament keeps hidden until it ends, the scores keep hidden here as on its standings - and a
	// tournament hiding its tables whole has none to show.
	private function show_standings()
	{
		global $_lang;

		$table_hiding = table_hiding($this->id, $this->flags);
		if (!is_null($table_hiding) && $table_hiding->mode == TOURNAMENT_HIDE_ALL)
		{
			return;
		}

		$rows = array();
		if ($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE)
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, nc.name, p.main_points + IFNULL(p.bonus_points, 0) + IFNULL(p.shot_points, 0), tu.flags, cu.flags FROM tournament_places p' .
				' JOIN users u ON u.id = p.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
				' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN tournament_regs tu ON tu.tournament_id = p.tournament_id AND tu.user_id = p.user_id' .
				' LEFT OUTER JOIN club_regs cu ON cu.club_id = ? AND cu.user_id = p.user_id' .
				' WHERE p.tournament_id = ? ORDER BY p.place LIMIT 10', $this->club_id, $this->id);
			while ($row = $query->next())
			{
				$rows[] = $row;
			}
		}
		else
		{
			list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
			$scoring = json_decode($scoring);
			$scoring->id = (int)$this->scoring_id; // it is needed for caching
			$scoring->version = (int)$this->scoring_version;
			$normalizer = '{}';
			if (!is_null($this->normalizer_id) && $this->normalizer_id > 0)
			{
				list($normalizer) = Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
			}
			$normalizer = json_decode($normalizer);
			$normalizer->id = (int)$this->normalizer_id;
			$normalizer->version = (int)$this->normalizer_version;
			$players = tournament_scores($this->id, $this->flags, null, 0, $scoring, $normalizer, json_decode($this->scoring_options));
			$count = min(count($players), 10);
			for ($i = 0; $i < $count; ++$i)
			{
				$p = $players[$i];
				$rows[] = array($p->id, $p->name, $p->flags, $p->club_name, $p->points, $p->tournament_reg_flags, $p->club_reg_flags);
			}
		}
		if (count($rows) == 0)
		{
			return;
		}

		$pic = new Picture(USER_TOURNAMENT_PICTURE, new Picture(USER_CLUB_PICTURE, $this->user_pic));
		echo '<section class="rail-box" aria-labelledby="h-standings"><div class="sec-hd"><h2 id="h-standings">' . get_label('Standings') . '</h2>';
		echo '<a class="more" href="tournament_standings.php?bck=1&id=' . $this->id . '">' . get_label('Full standings') . '</a></div><ol class="rank-list">';
		$place = 0;
		foreach ($rows as $row)
		{
			list($user_id, $user_name, $user_flags, $club_name, $points, $tournament_reg_flags, $club_reg_flags) = $row;
			++$place;
			$pic->
				set($user_id, $user_name, $tournament_reg_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_reg_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			echo '<li><span class="n">' . sprintf('%02d', $place) . '</span><a class="who" href="tournament_player.php?bck=1&user_id=' . $user_id . '&id=' . $this->id . '">' . card_avatar_of($pic, $user_id, $user_name);
			echo '<span class="who-txt"><span class="who-name">' . $user_name . '</span><span class="who-sub">' . $club_name . '</span></span></a>';
			echo '<span class="v">' . format_score($points) . '</span></li>';
		}
		echo '</ol></section>';
	}

	// The figures of the field, once there is a field to speak of.
	private function show_properties()
	{
		if ($this->num_regs <= 0)
		{
			return;
		}
		$figures = array(
			array(number_format($this->num_regs), get_label('Players')),
			array(format_rating(1000 + $this->rating_sum / $this->num_regs), get_label('Average rating')),
			array(format_rating(1000 + $this->rating_sum_20 / ($this->num_regs > 20 ? 20 : $this->num_regs)), get_label('Average rating of top 20 players')),
			array(get_label('[0] km', format_rating(convert_distance($this->traveling_distance, GEO_MILES, GEO_KILOMETERS))), get_label('Total traveling distance')),
			array(format_rating($this->guest_coeff, 0), get_label('Guest coefficient')));
		echo '<section class="rail-box" aria-labelledby="h-properties"><div class="sec-hd"><h2 id="h-properties">' . get_label('Properties') . '</h2></div><dl class="figs two">';
		foreach ($figures as $figure)
		{
			echo '<div><dt>' . $figure[1] . '</dt><dd>' . $figure[0] . '</dd></div>';
		}
		echo '</dl></section>';
	}
	// A grid of people of the tournament - its participants, applicants or referees - each in a card
	// of its own: the picture, the name with the club, and for the players where they come from and
	// their rating. Once games are played the picture leads to the player's page of the tournament.
	private function show_people($title, $rows, $played, $details)
	{
		if (count($rows) == 0)
		{
			return;
		}
		if (!isset($this->people_pic))
		{
			$this->people_pic = new Picture(USER_TOURNAMENT_PICTURE, new Picture(USER_CLUB_PICTURE, $this->user_pic));
		}
		$pic = $this->people_pic;

		echo '<section class="people-sec"><div class="sec-hd"><h2>' . $title . '</h2><span class="dim">' . count($rows) . '</span></div><div class="people">';
		foreach ($rows as $row)
		{
			list ($user_id, $user_name, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $tournament_reg_flags, $club_reg_flags) = $row;
			$pic->
				set($user_id, $user_name, $tournament_reg_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_reg_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			$url = $played ? $pic->hyperlink() : 'user_info.php?bck=1&id=' . $user_id;

			echo '<a class="person" href="' . $url . '">';
			echo '<span class="person-pic">' . PageBase::capture(function() use ($pic) { $pic->show(ICONS_DIR, false, 64, 64); }) . '</span>';
			echo '<span class="person-name">';
			if (!is_null($user_club_id))
			{
				$this->club_pic->set($user_club_id, $user_club_name, $user_club_flags);
				echo PageBase::capture(function() { $this->club_pic->show(ICONS_DIR, false, 18, 18); });
			}
			echo '<b>' . $user_name . '</b></span>';
			if ($details)
			{
				list (, , , , , , , , $city, $lat, $lon, $rating) = $row;
				echo '<span class="person-sub">' . $city . '</span>';
				echo '<span class="person-sub">' . get_label('[0] km', format_rating(get_distance($this->lat, $this->lon, $lat, $lon, GEO_KILOMETERS))) . '</span>';
				echo '<span class="person-rating">' . get_label('Rating: [0]', '<b>' . format_rating($rating + 1000) . '</b>') . '</span>';
			}
			echo '</a>';
		}
		echo '</div></section>';
	}

	protected function show_body()
	{
		echo '<div class="cols wide-rail tournament-home"><div class="stack">';
		$this->show_details();
		echo '</div><aside class="rail">';
		$this->show_standings();
		$this->show_properties();
		echo '</aside></div>';
		echo '<section class="comments-sec"><div id="comments"></div></section>';
	}
	
	protected function js_on_load()
	{
		global $_profile;
		
		echo 'mr.showComments("tournament", ' . $this->id . ", 5, false, 'wide_comment');\n";
		if (isset($_REQUEST['approve']) && $_profile != NULL && (!isset($_REQUEST['_login_']) || $_REQUEST['_login_'] == $_profile->user_id))
		{
			$league_id = (int)$_REQUEST['approve'];
			if ($league_id > 0 && is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
			{
?>
				mr.approveTournament(<?php echo $this->id; ?>, <?php echo $league_id; ?>);
<?php
			}
		}
	}
	
	protected function js()
	{
		parent::js();
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
