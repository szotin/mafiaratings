<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/cards.php';
require_once 'include/currency.php';
require_once 'include/scoring.php';

// The main page of a game day, made of the pieces of the main pages of a club and a tournament:
// whether one comes, its notes and who is coming as the people of a tournament; what there is to
// know about it and its figures beside them.
class Page extends EventPageBase
{
	private $reg_pic;

	// The card of one player who said whether they come.
	private function show_person($a, $coming)
	{
		list($user_id, $name, $nickname, $odds, $bringing, $user_flags, $late, $event_reg_flags, $tournament_id, $tournament_reg_flags, $club_id, $club_reg_flags) = $a;
		$this->reg_pic->
			set($user_id, $nickname, $event_reg_flags, 'e' . $this->id)->
			set($user_id, $name, $tournament_reg_flags, 't' . $tournament_id)->
			set($user_id, $name, $club_reg_flags, 'c' . $club_id)->
			set($user_id, $name, $user_flags);
		$pic = $this->reg_pic;
		$shown = empty($nickname) ? $name : $nickname;

		echo '<a class="person' . ($coming && $odds < 100 ? ' maybe' : '') . '" href="user_info.php?bck=1&id=' . $user_id . '">';
		echo '<span class="person-pic">' . PageBase::capture(function() use ($pic) { $pic->show(ICONS_DIR, false, 64, 64); }) . '</span>';
		echo '<span class="person-name"><b>' . $shown . '</b></span>';
		if (!empty($nickname) && !empty($name) && $name != $nickname)
		{
			echo '<span class="person-sub">' . $name . '</span>';
		}
		if ($coming)
		{
			$facts = array();
			if ($odds < 100)
			{
				$facts[] = get_label('[0]%', $odds);
			}
			if ($bringing > 0)
			{
				$facts[] = '+' . $bringing;
			}
			if ($late != 0)
			{
				$facts[] = event_late_str($late);
			}
			if (count($facts) > 0)
			{
				echo '<span class="person-rating">' . implode(' · ', $facts) . '</span>';
			}
		}
		echo '</a>';
	}

	// Who said they come, and who said they do not.
	private function show_attendance()
	{
		global $_lang;

		$attendance = array();
		$coming = 0;
		$min_coming = 0;
		$max_coming = 0;
		$declined = 0;
		$query = new DbQuery(
			'SELECT u.id, nu.name, eu.nickname, eu.coming_odds, eu.people_with_me, u.flags, eu.late, eu.flags, e.tournament_id, tu.flags, e.club_id, cu.flags' .
			' FROM event_regs eu' .
			' JOIN events e ON e.id = eu.event_id ' .
			' JOIN users u ON eu.user_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' .
			' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = e.club_id' .
			' WHERE e.id = ? ORDER BY eu.coming_odds DESC, eu.late, eu.people_with_me DESC, nu.name', $this->id);
		while ($row = $query->next())
		{
			$odds = is_null($row[3]) ? 100 : (int)$row[3];
			$bringing = is_null($row[4]) ? 0 : (int)$row[4];
			if ($odds >= 100)
			{
				$odds = 100;
				$min_coming += 1 + $bringing;
				$max_coming += 1 + $bringing;
				$coming += 1 + $bringing;
			}
			else if ($odds > 0)
			{
				$max_coming += 1 + $bringing;
				$coming += (1 + $bringing) * $odds / 100;
			}
			else
			{
				++$declined;
			}
			$row[3] = $odds;
			$row[4] = $bringing;
			$attendance[] = $row;
		}

		$this->reg_pic =
			new Picture(USER_EVENT_PICTURE,
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));

		if ($max_coming == 0)
		{
			$summary = get_label('No players attended yet.');
		}
		else if ($max_coming != $min_coming)
		{
			$summary = get_label('Players coming: [0]-[1]. Most likely: [2].', $min_coming, $max_coming, number_format($coming, 0));
		}
		else
		{
			$summary = get_label('Players coming: [0].', $min_coming);
		}
		echo '<section class="people-sec" aria-labelledby="h-coming"><div class="sec-hd"><div><h2 id="h-coming">' . get_label('Players') . '</h2><p class="sub">' . $summary . '</p></div></div>';
		if ($max_coming > 0)
		{
			echo '<div class="people">';
			foreach ($attendance as $a)
			{
				if ($a[3] > 0)
				{
					$this->show_person($a, true);
				}
			}
			echo '</div>';
		}
		echo '</section>';

		if ($declined > 0)
		{
			echo '<section class="people-sec off" aria-labelledby="h-declined"><div class="sec-hd"><h2 id="h-declined">' . get_label('Not coming') . '</h2><span class="dim">' . $declined . '</span></div><div class="people">';
			foreach ($attendance as $a)
			{
				if ($a[3] <= 0)
				{
					$this->show_person($a, false);
				}
			}
			echo '</div></section>';
		}
	}

	// The top of the standings of the day, as the best players of the main page of the site - unless
	// the tournament hides the tables of this round.
	private function show_standings()
	{
		if (is_round_hidden(table_hiding($this->tournament_id, $this->tournament_flags), $this->round_num))
		{
			return;
		}
		list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		$scoring = json_decode($scoring);
		$scoring->id = (int)$this->scoring_id; // it is needed for caching
		$scoring->version = (int)$this->scoring_version;
		$options = json_decode($this->scoring_options);
		$players = event_scores($this->id, null, 0, $scoring, $options, $this->tournament_flags, $this->round_num);
		if (count($players) == 0)
		{
			return;
		}

		$pic =
			new Picture(USER_EVENT_PICTURE,
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));
		echo '<section class="rail-box" aria-labelledby="h-standings"><div class="sec-hd"><h2 id="h-standings">' . get_label('Standings') . '</h2>';
		echo '<a class="more" href="event_standings.php?bck=1&id=' . $this->id . $this->show_all . '">' . get_label('Full standings') . '</a></div><ol class="rank-list">';
		$count = min(count($players), 10);
		for ($i = 0; $i < $count; ++$i)
		{
			$player = $players[$i];
			$pic->
				set($player->id, $player->nickname, $player->event_reg_flags, 'e' . $this->id)->
				set($player->id, $player->name, $player->tournament_reg_flags, 't' . $this->tournament_id)->
				set($player->id, $player->name, $player->club_reg_flags, 'c' . $this->club_id)->
				set($player->id, $player->name, $player->flags);
			echo '<li><span class="n">' . sprintf('%02d', $i + 1) . '</span><a class="who" href="event_player.php?bck=1&user_id=' . $player->id . '&id=' . $this->id . $this->show_all . '">' . card_avatar_of($pic, $player->id, $player->name);
			echo '<span class="who-txt"><span class="who-name">' . $player->name . '</span><span class="who-sub">' . $player->club_name . '</span></span></a>';
			echo '<span class="v">' . format_score($player->points) . '</span></li>';
		}
		echo '</ol></section>';
	}

	// What there is to know about the game day.
	private function show_info()
	{
		echo '<section class="rail-box" aria-labelledby="h-info"><div class="sec-hd"><h2 id="h-info">' . get_label('Information') . '</h2></div><dl class="kv">';
		echo '<dt>' . get_label('Time') . '</dt><dd>' . format_date($this->start_time, $this->timezone, true) . '</dd>';
		$address = addr_label($this->address, $this->city, $this->country);
		if ($this->address_url != '')
		{
			$address = '<a href="' . $this->address_url . '" target="_blank">' . $address . '</a>';
		}
		echo '<dt>' . get_label('Address') . '</dt><dd>' . $address . '</dd>';
		echo '<dt>' . get_label('Languages') . '</dt><dd>' . get_langs_str($this->langs, ', ') . '</dd>';
		if (!is_null($this->currency_pattern) && !is_null($this->fee))
		{
			echo '<dt>' . get_label('Admission rate') . '</dt><dd>' . format_currency($this->fee, $this->currency_pattern) . '</dd>';
		}
		echo '</dl></section>';
	}

	// The figures of the games played on the day, once there are any.
	private function show_stats()
	{
		$town = $mafia = $tie = 0;
		$query = new DbQuery('SELECT result, count(*) FROM games WHERE event_id = ? AND (flags & '.GAME_FLAG_CANCELED.') = 0 GROUP BY result', $this->id);
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
		list ($players) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
		$percent = function($n) use ($games) { return number_format($n * 100.0 / $games, 1) . '%'; };
		$figures = array(
			array(number_format($games), get_label('Games played')),
			array(number_format($players), get_label('People played')),
			array($percent($town), get_label('Town wins')),
			array($percent($mafia), get_label('Mafia wins')));
		echo '<section class="rail-box" aria-labelledby="h-stats"><div class="sec-hd"><h2 id="h-stats">' . get_label('Stats') . '</h2>';
		echo '<a class="more" href="event_games.php?bck=1&id=' . $this->id . '">' . get_label('Games') . '</a></div><dl class="figs two">';
		foreach ($figures as $figure)
		{
			echo '<div><dt>' . $figure[1] . '</dt><dd>' . $figure[0] . '</dd></div>';
		}
		echo '</dl></section>';
	}

	protected function show_body()
	{
		global $_profile;

		// Whether one comes, while one still can.
		if ($_profile != NULL && ($this->flags & EVENT_FLAG_CANCELED) == 0 && time() < $this->start_time + $this->duration)
		{
			echo '<div class="attend-bar">';
			echo '<button type="button" class="btn" onclick="attend()">' . get_label('Attend') . '</button>';
			echo '<button type="button" class="btn ghost" onclick="decline()">' . get_label('Pass') . '</button></div>';
		}

		echo '<div class="cols wide-rail event-home"><div class="stack">';
		if ($this->notes != '')
		{
			echo '<div class="game-comment">' . $this->notes . '</div>';
		}
		$this->show_attendance();
		$this->show_info();
		echo '</div><aside class="rail">';
		$this->show_standings();
		$this->show_stats();
		echo '</aside></div>';
		echo '<section class="comments-sec"><div id="comments"></div></section>';
	}

	protected function js_on_load()
	{
		echo 'mr.showComments("event", ' . $this->id . ", 5, false, 'wide_comment')\n";
		if (isset($_REQUEST['attend']))
		{
			echo 'attend();';
		}
		else if (isset($_REQUEST['decline']))
		{
			echo 'decline();';
		}
	}

	protected function js()
	{
		parent::js();
?>
		function attend()
		{
			mr.attendEvent(<?php echo $this->id; ?>, "event_info.php?id=<?php echo $this->id; ?>");
		}

		function decline()
		{
			mr.passEvent(<?php echo $this->id; ?>, "event_info.php?id=<?php echo $this->id; ?>", "<?php echo get_label('Thank you for letting us know. See you next time.'); ?>");
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
