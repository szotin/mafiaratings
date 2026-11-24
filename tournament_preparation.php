<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/seating.php';
require_once 'include/preparation.php';
require_once 'include/table_hiding.php';
require_once 'include/scoring.php';
require_once 'include/rules.php';
require_once 'include/tournament_schema.php';
require_once 'include/cards.php';

// The numbers are stored - tournaments.preparation_stage remembers the tab a tournament was left
// on - so renumbering the tabs means moving the stored numbers too, the way db/alter210.sql and
// db/alter211.sql did.
define('PREP_TAB_SCHEME', 0);
// The rules the games are played by, the scoring and the special awards.
define('PREP_TAB_RULES', 1);
// What the scoring table hides until the tournament ends. It is decided once the rounds are
// known, and long before any of them is played: the table hides itself when the moment comes.
define('PREP_TAB_TABLE', 2);
// The new-registration email links straight here, by number - see
// include/languages/*/email/tournament_new_reg.php. Renumbering the tabs means fixing that link.
define('PREP_TAB_REGISTRATIONS', 3);
// The broadcasts are set up before anybody is paired or seated: the addresses have to be in
// before the games are played, or there is nothing for a game to be tied to as it is written
// down - see include/broadcast.php.
define('PREP_TAB_STREAMS', 4);
define('PREP_TAB_PAIRS', 5);
define('PREP_TAB_SEATING', 6);

class Page extends TournamentPageBase
{
	use PreparationTabs;

	private $tab;
	private $tab_count;
	private $rounds;
	private $long_term;            // a long-term tournament has no rounds of its own, see showSchemeTab()
	private $scheme_players;
	private $scheme_tables;
	private $reg_players;
	private $reg_referees;
	private $regs_complete;
	private $prep_tournament_id;   // see the trait: these rounds all belong to this tournament
	private $scheme_valid;

	// Everything after the registrations waits for them to be complete. The streams tab is the
	// one exception - what is set up there does not depend on who is coming - but the step off
	// it does, because it leads to the pairs. See show_body(). The registrations themselves do not
	// wait for the scheme: people can sign up while it is being worked out.
	protected function nextDisabled()
	{
		if (!$this->regs_complete && $this->tab == PREP_TAB_REGISTRATIONS)
		{
			return true;
		}
		return false;
	}

	// The seating needs a scheme and the registrations: without them the broadcasts are as far as the
	// tournament goes for now.
	protected function nextUnavailable()
	{
		return (!$this->scheme_valid || !$this->regs_complete) && $this->tab == PREP_TAB_STREAMS;
	}

	protected function finishUrl()
	{
		return 'tournament.php?id=' . $this->id;
	}

	protected function prepare()
	{
		parent::prepare();

		if (isset($_REQUEST['tab']))
		{
			$this->tab = (int)$_REQUEST['tab'];
		}

		$this->prep_tournament_id = $this->id;
		// The scoring options are past what the trait reads: the weight of the points of the round.
		$query = new DbQuery('SELECT id, round, misc, players, tables, games, scoring_options FROM events WHERE tournament_id = ? ORDER BY round', $this->id);
		$main_rounds = array();
		$tmp_finals = array();
		while ($row = $query->next())
		{
			if ($row[1] == 0)
			{
				$main_rounds[] = $row;
			}
			else
			{
				$tmp_finals[] = $row;
			}
		}
		$this->rounds = $main_rounds;
		for ($i = count($tmp_finals) - 1; $i >= 0; --$i)
		{
			$this->rounds[] = $tmp_finals[$i];
		}

		list($preparation_stage) = Db::record(get_label('tournament'), 'SELECT preparation_stage FROM tournaments WHERE id = ?', $this->id);

		// A long-term tournament is played on the ordinary days of its club. It is not seated, and
		// its events are not rounds anybody lays out here, so it goes no further than the streams.
		$this->long_term = ($this->flags & TOURNAMENT_FLAG_LONG_TERM) != 0;
		if ($this->long_term)
		{
			$this->rounds = array();
			$this->tab_count = PREP_TAB_STREAMS + 1;
		}
		else
		{
			$this->tab_count = PREP_TAB_SEATING + count($this->rounds);
		}

		$this->scheme_players = 0;
		$this->scheme_tables = 0;
		foreach ($this->rounds as $row)
		{
			list($event_id, $round_num, $misc, $players, $tables, $games) = $row;
			if ($round_num == 0)
			{
				$this->scheme_players += (int)$players;
				$this->scheme_tables += (int)$tables;
			}
		}

		list($this->reg_players) = Db::record(get_label('registration'),
			'SELECT COUNT(*) FROM tournament_regs WHERE tournament_id = ? AND (flags & ?) <> 0 AND (flags & ?) = 0',
			$this->id, USER_PERM_PLAYER, USER_TOURNAMENT_FLAG_NOT_ACCEPTED);
		list($this->reg_referees) = Db::record(get_label('registration'),
			'SELECT COUNT(*) FROM tournament_regs WHERE tournament_id = ? AND (flags & ?) <> 0 AND (flags & ?) = 0',
			$this->id, USER_PERM_REFEREE, USER_TOURNAMENT_FLAG_NOT_ACCEPTED);
		$this->regs_complete = ($this->reg_players >= $this->scheme_players && $this->reg_referees >= $this->scheme_tables);

		$this->scheme_valid = $this->long_term || !empty($this->rounds);
		foreach ($this->rounds as $round_row)
		{
			$p = (int)$round_row[3];
			$t = (int)$round_row[4];
			$g = (int)$round_row[5];
			if ($p < 10 || $t < 1 || $g < 1 || $t > (int)floor($p / 10))
			{
				$this->scheme_valid = false;
				break;
			}
		}

		if (!isset($_REQUEST['tab']))
		{
			$this->tab = (int)$preparation_stage;
		}
		if (($this->tab == PREP_TAB_DETAILS || $this->tab == PREP_TAB_SERIES) && $this->canEditDetails())
		{
			// The fields of the tournament itself, and its series - see include/details_tab.php.
			// Never stored as the tab it was left on: coming back, a manager goes on with the
			// preparation.
		}
		else if ($this->tab < PREP_TAB_SCHEME || $this->tab >= $this->tab_count)
		{
			$this->tab = PREP_TAB_SCHEME;
		}
	}

	// The fields of the tournament are its managers', not its referees' - the ones who could edit
	// it in the dialog.
	protected function canEditDetails()
	{
		return is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id);
	}

	// The series the tournament plays in are a tab of their own after "Basics": the same form, the
	// other part of it.
	protected function lastDetailsTab()
	{
		return PREP_TAB_SERIES;
	}

	private function showRegistrationsTab()
	{
		global $_lang;

		list ($tournament_flags) = Db::record(get_label('tournament'), 'SELECT flags FROM tournaments WHERE id = ?', $this->id);
		$this->navBar();
		// Without it only a manager can register anybody, and what players ask for themselves
		// is not taken.
		echo '<p><input type="checkbox" class="toggle" id="prep-self-reg"' . (($tournament_flags & TOURNAMENT_FLAG_REGISTRATION_CLOSED) ? '' : ' checked') .
			' onchange="setTournamentFlag(' . TOURNAMENT_FLAG_REGISTRATION_CLOSED . ', !this.checked)"> ' . get_label('participants can register themselves') . '</p>';

		$total_tables = 0;
		$total_players = 0;
		foreach ($this->rounds as $row)
		{
			list($event_id, $round_num, $misc, $players, $tables, $games) = $row;
			if ($round_num == 0)
			{
				$total_tables += (int)$tables;
				$total_players += (int)$players;
			}
		}

		$sel =
			'SELECT tr.user_id, nu.name, u.email, u.flags, tr.flags, cr.flags, ni.name, u.club_id, nc.name, c.flags FROM tournament_regs tr' .
			' JOIN users u ON u.id = tr.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
			' LEFT JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0' .
			// The club holding the tournament can keep a photo of a player of its own, and that
			// is the row it is marked in. Without it the list falls back to the account picture
			// and so says something different from the photo dialog, which reads all three.
			' LEFT JOIN club_regs cr ON cr.user_id = u.id AND cr.club_id = ' . (int)$this->club_id .
			' WHERE tr.tournament_id = ?';
		$accepted = ' AND (tr.flags & ' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED . ') = 0';

		$managers = array();
		$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_PERM_MANAGER . ') <> 0' . $accepted . ' ORDER BY nu.name', $this->id);
		while ($row = $query->next()) { $managers[] = $row; }

		$referees = array();
		$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_PERM_REFEREE . ') <> 0' . $accepted . ' ORDER BY tr.reg_order ASC, nu.name', $this->id);
		while ($row = $query->next()) { $referees[] = $row; }

		$players = array();
		if ($this->team_size > 1)
		{
			$sel_team =
				'SELECT tr.user_id, nu.name, u.email, u.flags, tr.flags, cr.flags, ni.name, u.club_id, nc.name, c.flags, tr.team_id, t.name FROM tournament_regs tr' .
				' JOIN users u ON u.id = tr.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
				' JOIN cities i ON i.id = u.city_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
				' LEFT JOIN clubs c ON c.id = u.club_id' .
				' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0' .
				' LEFT JOIN club_regs cr ON cr.user_id = u.id AND cr.club_id = ' . (int)$this->club_id .
				' LEFT JOIN tournament_teams t ON t.id = tr.team_id' .
				' WHERE tr.tournament_id = ?';
			$query = new DbQuery($sel_team . ' AND (tr.flags & ' . USER_PERM_PLAYER . ') <> 0' . $accepted . ' ORDER BY tr.reg_order ASC, nu.name', $this->id);
		}
		else
		{
			$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_PERM_PLAYER . ') <> 0' . $accepted . ' ORDER BY tr.reg_order ASC, nu.name', $this->id);
		}
		while ($row = $query->next()) { $players[] = $row; }

		$unconfirmed = array();
		$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED . ') <> 0 ORDER BY nu.name', $this->id);
		while ($row = $query->next()) { $unconfirmed[] = $row; }

		if (!empty($managers))
		{
			$this->showRegTable(get_label('Managers'), $managers, 0, USER_PERM_MANAGER, get_label('Manager'));
		}
		$this->showRegTable(get_label('Referees'), $referees, $total_tables, USER_PERM_REFEREE, get_label('Referee'));
		if ($this->team_size > 1)
		{
			$this->showTeamPlayerTable($players, $total_players);
		}
		else
		{
			$this->showRegTable(get_label('Players'), $players, $total_players, USER_PERM_PLAYER, get_label('Player'), true);
		}

		if (!empty($unconfirmed))
		{
			$pic = new Picture(USER_TOURNAMENT_PICTURE,
				new Picture(USER_CLUB_PICTURE,
				new Picture(USER_PICTURE)));
			echo '<div class="toolbar reg-hd"><b>' . get_label('Not confirmed') . '</b></div>';
			echo '<div class="sheet"><table class="tbl">';
			echo '<thead><tr>';
			echo '<th scope="col"></th>';
			echo '<th class="rk" scope="col">#</th>';
			echo '<th scope="col">' . get_label('Player') . '</th>';
			echo '<th class="p3" scope="col">' . get_label('Club') . '</th>';
			echo '<th class="p2" scope="col">' . get_label('Email') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($unconfirmed as $i => $row)
			{
				list($user_id, $name, $email, $user_flags, $tr_flags, $club_reg_flags, $city, $club_id, $club_name, $club_flags) = $row;
				echo '<tr>';
				echo '<td class="tb-btns">';
				echo '<button onclick="mr.acceptTournamentReg(' . $this->id . ',' . $user_id . ')">' . get_label('Accept application') . '</button>';
				echo '</td>';
				echo $this->numberCell($i + 1);
				echo '<td>' . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city) . '</td>';
				echo '<td class="p3">' . $this->clubCell((int)$club_id, (string)$club_name, (int)$club_flags) . '</td>';
				echo '<td class="p2 dim">' . $email . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}
	}

	// A player in a row of the v3 tables (see ratings.php): the round picture, the name, the city
	// under it, and the mark of an exhibition player after the city.
	private function personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city)
	{
		// The tournament's own photo of the player first, then the club's, then the account one
		// - the same order as everywhere else the player is drawn, and as the photo dialog.
		$pic->set($user_id, $name, $tr_flags, 't' . $this->id)->
			set($user_id, $name, $club_reg_flags, 'c' . $this->club_id)->
			set($user_id, $name, $user_flags);
		return '<a class="who" href="user_info.php?id=' . $user_id . '&bck=1">' . card_avatar_of($pic, $user_id, $name, '') .
			'<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city . $this->exhibitionBadge($tr_flags) . '</span></span></a>';
	}

	private function exhibitionBadge($tr_flags)
	{
		if (($tr_flags & USER_FLAG_EXHIBITION_PLAYER) == 0)
		{
			return '';
		}
		return ' <span title="' . get_label('Excluded from tournament standings and series scoring.') . '" style="margin-left:6px;background:#a06060;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;">' . get_label('exhibition player') . '</span>';
	}

	private function clubCell($club_id, $club_name, $club_flags)
	{
		if ($club_id <= 0)
		{
			return '';
		}
		return card_club($this->club_pic, $club_id, $club_name, $club_flags);
	}

	// A place nobody has taken yet: an empty circle where the picture would be.
	private function emptyPersonCell()
	{
		return '<span class="who"><span class="av" aria-hidden="true"></span></span>';
	}

	// The number of a row: its place in the order of registration.
	private function numberCell($number)
	{
		return '<td class="rk"><span class="medal">' . sprintf('%02d', $number) . '</span></td>';
	}

	// The heading over a table of registrations, with the button that adds one at its end.
	private function regHeading($title, $default_flags)
	{
		echo '<div class="toolbar reg-hd"><b>' . $title . '</b>';
		echo '<a class="btn tb-create" href="#" onclick="dlg.form(\'form/registration_create.php?tournament_id=' . $this->id . '&flags=' . $default_flags . '\', refr, 400); return false;" title="' . get_label('Add registration to [0].', $this->name) . '">' . PageBase::icon('plus') . '<span>' . get_label('Add registration') . '</span></a>';
		echo '</div>';
	}

	private function showRegTable($title, $regs, $scheme_count, $default_flags, $person_label, $with_rollback = false)
	{
		$pic = new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));
		$reg_count = count($regs);
		$row_count = max($scheme_count, $reg_count);
		$this->regHeading($title, $default_flags);
		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr>';
		echo '<th scope="col"></th>';
		echo '<th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . $person_label . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Email') . '</th>';
		echo '<th scope="col"></th>';
		echo '</tr></thead><tbody>';

		for ($i = 0; $i < $row_count; ++$i)
		{
			// The places beyond what the scheme of the tournament holds are shaded.
			$overflow = ($scheme_count > 0 && $i >= $scheme_count);
			echo '<tr' . ($overflow ? ' class="darker"' : '') . '>';
			if ($i < $reg_count)
			{
				list($user_id, $name, $email, $user_flags, $tr_flags, $club_reg_flags, $city, $club_id, $club_name, $club_flags) = $regs[$i];
				// The actions of the row under "≡" - see card_row_menu().
				$new_flags = ($tr_flags & USER_PERM_MASK) & ~$default_flags;
				$items = array(
					array('mr.editTournamentReg(' . $this->id . ', ' . $user_id . ')', 'edit.png', get_label('Change registration')),
					array('mr.tournamentUserPhoto(' . $user_id . ', ' . $this->id . ')', 'photo.png', get_label('Separate photo')));
				if ($with_rollback)
				{
					$items[] = array('rollbackTournamentReg(' . $user_id . ')', 'undo.png', get_label('Back to not confirmed'));
				}
				$items[] = array('removeTournamentRegFlag(' . $user_id . ', ' . $new_flags . ', ' . json_encode(get_label('Are you sure you want to unregister [0]?', $name)) . ')', 'delete.png', get_label('Unregister'));
				echo '<td class="tb-btns">' . card_row_menu($items, $name) . '</td>';
				echo $this->numberCell($i + 1);
				echo '<td>' . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city) . '</td>';
				echo '<td class="p3">' . $this->clubCell((int)$club_id, (string)$club_name, (int)$club_flags) . '</td>';
				echo '<td class="p2 dim">' . $email . '</td>';
				$up_style = ($i == 0) ? ' style="visibility:hidden"' : '';
				echo '<td class="tb-btns"><button class="icon"' . $up_style . ' onclick="moveRegistration(' . $user_id . ', ' . $default_flags . ')" title="' . get_label('Move up') . '"><img src="images/up.png" border="0"></button></td>';
			}
			else
			{
				echo '<td></td>';
				echo $this->numberCell($i + 1);
				echo '<td>' . $this->emptyPersonCell() . '</td>';
				echo '<td class="p3"></td>';
				echo '<td class="p2"></td>';
				echo '<td></td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private function showTeamPlayerTable($regs, $scheme_count)
	{
		$pic = new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));
		$default_flags = USER_PERM_PLAYER;

		// Group players by team, preserving reg_order-based sequence
		$groups = array();
		$group_keys = array();
		foreach ($regs as $row)
		{
			$team_id = $row[10];
			$key = ($team_id !== null && (int)$team_id > 0) ? 'team_' . (int)$team_id : 'solo_' . $row[0];
			if (!array_key_exists($key, $groups))
			{
				$groups[$key] = array('team_id' => ($team_id !== null ? (int)$team_id : null), 'team_name' => $row[11], 'players' => array());
				$group_keys[] = $key;
			}
			$groups[$key]['players'][] = $row;
		}

		$this->regHeading(get_label('Players'), $default_flags);
		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr>';
		echo '<th scope="col"></th>';
		echo '<th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Team') . '</th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Email') . '</th>';
		echo '<th scope="col"></th>';
		echo '</tr></thead><tbody>';

		$row_num = 0;
		$is_first_group = true;
		foreach ($group_keys as $key)
		{
			$group = $groups[$key];
			$team_id = $group['team_id'];
			$team_name = $group['team_name'];
			$group_size = count($group['players']);
			$rowspan = $group_size > 1 ? ' rowspan="' . $group_size . '"' : '';
			$up_style = $is_first_group ? ' style="visibility:hidden"' : '';

			for ($j = 0; $j < $group_size; ++$j)
			{
				$row = $group['players'][$j];
				list($user_id, $name, $email, $user_flags, $tr_flags, $club_reg_flags, $city, $club_id, $club_name, $club_flags) = $row;

				$overflow = ($scheme_count > 0 && $row_num >= $scheme_count);
				echo '<tr' . ($overflow ? ' class="darker"' : '') . '>';

				// The actions of the row under "≡" - see card_row_menu().
				$new_flags = ($tr_flags & USER_PERM_MASK) & ~$default_flags;
				echo '<td class="tb-btns">' . card_row_menu(array(
					array('mr.editTournamentReg(' . $this->id . ', ' . $user_id . ')', 'edit.png', get_label('Change registration')),
					array('mr.tournamentUserPhoto(' . $user_id . ', ' . $this->id . ')', 'photo.png', get_label('Separate photo')),
					array('rollbackTournamentReg(' . $user_id . ')', 'undo.png', get_label('Back to not confirmed')),
					array('removeTournamentRegFlag(' . $user_id . ', ' . $new_flags . ', ' . json_encode(get_label('Are you sure you want to unregister [0]?', $name)) . ')', 'delete.png', get_label('Unregister'))),
					$name) . '</td>';

				echo $this->numberCell(++$row_num);

				if ($j == 0)
				{
					if ($team_id !== null)
					{
						echo '<td' . $rowspan . ' class="strong">' . htmlspecialchars((string)$team_name) . '</td>';
					}
					else
					{
						echo '<td' . $rowspan . ' class="dim">' . get_label('No team') . '</td>';
					}
				}

				echo '<td>' . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city) . '</td>';
				echo '<td class="p3">' . $this->clubCell((int)$club_id, (string)$club_name, (int)$club_flags) . '</td>';
				echo '<td class="p2 dim">' . $email . '</td>';

				if ($j == 0)
				{
					echo '<td' . $rowspan . ' class="tb-btns">';
					if ($team_id !== null)
					{
						echo '<button class="icon"' . $up_style . ' onclick="moveTeamUp(' . $team_id . ', ' . $default_flags . ')" title="' . get_label('Move up') . '"><img src="images/up.png" border="0"></button>';
					}
					else
					{
						echo '<button class="icon"' . $up_style . ' onclick="moveRegistration(' . $user_id . ', ' . $default_flags . ')" title="' . get_label('Move up') . '"><img src="images/up.png" border="0"></button>';
					}
					echo '</td>';
				}

				echo '</tr>';
			}

			$is_first_group = false;
		}

		for (; $row_num < $scheme_count; ++$row_num)
		{
			echo '<tr>';
			echo '<td></td>';
			echo $this->numberCell($row_num + 1);
			echo '<td></td>';
			echo '<td>' . $this->emptyPersonCell() . '</td>';
			echo '<td class="p3"></td>';
			echo '<td class="p2"></td>';
			echo '<td></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	// The leagues a tournament of this club takes a schema from, the one it is in first - the
	// system of the club, see clubs.system_league_id - and their schemas.
	// The leagues the tournament can take a schema from: only those that have schemas.
	private function schemaLeagues()
	{
		$leagues = array();
		foreach (get_tournament_schema_leagues($this->club_id) as $id => $l)
		{
			if (count($l->schemas) > 0)
			{
				$leagues[$id] = $l;
			}
		}
		return $leagues;
	}

	// The scheme: a schema of a league to set the whole tournament up by, whether it is a
	// long-term one, and the rounds. Taking a schema replaces the rounds, the rules, the scoring,
	// the awards and the hiding of the table; it is not remembered, so the list always starts
	// empty. The rounds are added, deleted and changed by hand as well. A long-term tournament
	// has none: its games are played on the ordinary days of its club.
	private function showSchemeTab()
	{
		$leagues = $this->schemaLeagues();

		// No league is picked to start with; the list of schemas is there once one is.
		$this->navBar();
		if (count($leagues) > 0)
		{
			echo '<p>' . get_label('Tournament schema') . ': <select id="schema-league" onchange="schemaLeagueChanged()">';
			show_option(0, 0, '');
			foreach ($leagues as $l)
			{
				show_option($l->id, 0, $l->name);
			}
			echo '</select> <select id="schema-sel" onchange="schemaChanged()" style="display:none"></select></p>';
		}

		echo '<p><table class="bordered light" width="100%"><tr><td>';
		echo '<input type="checkbox" class="toggle" id="prep-long-term"' . ($this->long_term ? ' checked' : '') . ' onchange="longTermChanged(this.checked)"> ' . get_label('long term tournament. Like a seasonal club championship.');
		if ($this->long_term)
		{
			echo '<div style="margin: 6px 0 0 28px;"><input type="checkbox" class="toggle" id="prep-single-game"' . (($this->flags & TOURNAMENT_FLAG_SINGLE_GAME) ? ' checked' : '') .
				' onchange="setTournamentFlag(' . TOURNAMENT_FLAG_SINGLE_GAME . ', this.checked)"> ' . get_label('single games from non-tournament events can be assigned to the tournament.') . '</div>';
		}
		echo '</td></tr></table></p>';

		if ($this->long_term)
		{
			return;
		}

		if (empty($this->rounds))
		{
			echo '<p>' . get_label('This tournament has no rounds yet. Add them, or take a tournament schema of a league.') . '</p>';
		}

		$num_rounds = count($this->rounds);
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="32" align="center"><button class="icon" onclick="addRound()" title="' . get_label('Add round') . '"><img src="images/create.png" border="0"></button></td>';
		echo '<td>' . get_label('Round') . '</td>';
		echo '<td>' . get_label('Players') . '</td>';
		echo '<td>' . get_label('Tables') . '</td>';
		echo '<td>' . get_label('Games per player') . '</td>';
		echo '<td>' . get_label('Points weight') . '</td>';
		echo '<td title="' . get_label('Tournament group') . '">' . get_label('Group') . '</td>';
		echo '<td>' . get_label('Team size') . '</td>';
		echo '</tr>';

		$first_row = true;
		foreach ($this->rounds as $row)
		{
			list($event_id, $round_num, $misc, $players, $tables, $games, $round_options) = $row;
			$round_options = json_decode($round_options);
			$weight = isset($round_options->weight) ? (float)$round_options->weight : 1;
			$group = isset($round_options->group) ? normalize_tournament_group($round_options->group, $round_num) : '';
			$round_name = get_round_name($round_num);

			$players_val = is_null($players) ? '' : $players;
			$tables_val  = is_null($tables)  ? '' : $tables;
			$games_val   = is_null($games)   ? '' : $games;

			$inp_base = ' type="number" style="width:60px;" oninput="schemeInputChanged(this)" onchange="saveScheme()"';
			$players_int = ($players_val !== '') ? (int)$players_val : 0;
			$max_tables = ($players_int >= 10) ? (int)floor($players_int / 10) : 0;
			$inp_tables_max = ($max_tables > 0) ? ' max="' . $max_tables . '"' : '';
			$is_main = ($round_num == 0) ? '1' : '0';
			echo '<tr data-event-id="' . $event_id . '" data-is-main="' . $is_main . '">';
			echo '<td align="center"><button class="icon" onclick="deleteRound(' . $event_id . ', \'' . addslashes(get_label('Are you sure you want to delete [0]?', $round_name)) . '\')" title="' . get_label('Delete [0]', $round_name) . '"><img src="images/delete.png" border="0"></button></td>';
			echo '<td><b>' . $round_name . '</b></td>';
			echo '<td><input' . $inp_base . ' min="10" data-field="players" value="' . $players_val . '"></td>';
			echo '<td><input' . $inp_base . ' min="1"' . $inp_tables_max . ' data-field="tables"  value="' . $tables_val  . '"></td>';
			echo '<td><input' . $inp_base . ' min="1" data-field="games"   value="' . $games_val   . '"></td>';
			echo '<td><input type="number" style="width:60px;" step="0.1" min="0.1" data-field="weight" value="' . $weight . '" onchange="saveScheme()"></td>';
			echo '<td><select data-field="group" onchange="saveScheme()">';
			foreach (tournament_group_names() as $value => $text)
			{
				show_option($value, $group, $text);
			}
			echo '</select></td>';
			if ($first_row)
			{
				$rowspan = $num_rounds > 1 ? ' rowspan="' . $num_rounds . '"' : '';
				echo '<td' . $rowspan . ' align="center"><input id="scheme-team-size" type="number" style="width:60px;" min="1" value="' . (int)$this->team_size . '" onchange="saveScheme()"></td>';
				$first_row = false;
			}
			echo '</tr>';
		}

		echo '</table></p>';

		// The number of players the tournament counts for is the players of its main round, which
		// the scheme sets. Once it is over it can be corrected by those who actually played.
		list ($tournament_flags) = Db::record(get_label('tournament'), 'SELECT flags FROM tournaments WHERE id = ?', $this->id);
		echo '<p><input type="checkbox" class="toggle" id="prep-correct-players"' . (($tournament_flags & TOURNAMENT_FLAG_FORCE_NUM_PLAYERS) ? '' : ' checked') .
			' onchange="setTournamentFlag(' . TOURNAMENT_FLAG_FORCE_NUM_PLAYERS . ', !this.checked)"> ' . get_label('correct number of players after the tournament is complete') . '</p>';
	}

	// The rules the games are played by, the scoring and its normalizer, and the special awards.
	// Each is saved the moment it is changed. Changing the rules or the scoring changes them in
	// every round as well - see the change op in api/ops/tournament.php.
	private function showRulesTab()
	{
		global $_lang;

		list ($flags, $rules_code, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $club_name, $club_rules) = Db::record(get_label('tournament'),
			'SELECT t.flags, t.rules, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, nc.name, c.rules FROM tournaments t JOIN clubs c ON c.id = t.club_id JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0 WHERE t.id = ?', $this->id);
		$flags = (int)$flags;
		if (is_null($normalizer_id))
		{
			$normalizer_id = 0;
			$normalizer_version = 0;
		}

		$this->navBar();

		echo '<p><table class="bordered light" width="100%">';
		$rules_code = upgrade_rules_code($rules_code);
		echo '<tr><td width="160">' . get_label('Rules') . ':</td><td><select id="prep-rules" onchange="rulesChanged()">';
		foreach (merge_same_rules(get_available_rules($this->club_id, $club_name, $club_rules, $rules_code)) as $r)
		{
			if (show_option($r->rules, $rules_code, $r->name))
			{
				$rules_code = '';
			}
		}
		if (!empty($rules_code))
		{
			show_option($rules_code, $rules_code, get_label('Custom...'));
		}
		echo '</select></td></tr>';

		// The options under the scoring system each stand on a line of their own, with room
		// between them, like the awards below.
		echo '<tr><td valign="top">' . get_label('Scoring system') . ':</td><td>';
		show_scoring_select($this->club_id, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, json_decode($scoring_options), '<div style="height:12px"></div>', 'scoringChanged', SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION, 'prep-scoring');
		echo '</td></tr>';

		// One award a line, all of them alike. Leaving the sheriff out of the best red, and the
		// don out of the best black, is a part of that award, so it stands on its line and is
		// there only while the award is on.
		$awards = array(
			array(TOURNAMENT_FLAG_AWARD_MVP, get_label('MVP'), 0, ''),
			array(TOURNAMENT_FLAG_AWARD_RED, get_label('best red player'), TOURNAMENT_FLAG_AWARD_NO_SHERIFF_IN_RED, get_label('exclude the sheriff games')),
			array(TOURNAMENT_FLAG_AWARD_BLACK, get_label('best black player'), TOURNAMENT_FLAG_AWARD_NO_DON_IN_BLACK, get_label('exclude the don games')),
			array(TOURNAMENT_FLAG_AWARD_SHERIFF, get_label('best sheriff'), 0, ''),
			array(TOURNAMENT_FLAG_AWARD_DON, get_label('best don'), 0, ''));
		echo '<tr><td valign="top">' . get_label('Special awards') . ':</td><td>';
		echo '<table class="transp">';
		foreach ($awards as $award)
		{
			list ($flag, $text, $sub_flag, $sub_text) = $award;
			echo '<tr><td style="padding: 6px 24px 6px 0;" nowrap><input type="checkbox" class="toggle"' . (($flags & $flag) ? ' checked' : '') .
				' onchange="awardChanged(this, ' . $flag . ', ' . $sub_flag . ')"> ' . $text . '</td>';
			echo '<td style="padding: 6px 0;">';
			if ($sub_flag != 0)
			{
				echo '<span id="award-sub-' . $sub_flag . '"' . (($flags & $flag) ? '' : ' style="display:none"') . '>' .
					'<input type="checkbox" class="toggle"' . (($flags & $sub_flag) ? ' checked' : '') .
					' onchange="setTournamentFlag(' . $sub_flag . ', this.checked)"> ' . $sub_text . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		echo '</td></tr>';
		echo '</table></p>';
	}

	// The table and the bonus points: where each is hidden from, and from which tour of that
	// round. See include/table_hiding.php. The rounds offered are the rounds the tournament has.
	private function showTableTab()
	{
		list ($flags, $table_tour, $bonus_tour) = Db::record(get_label('tournament'),
			'SELECT flags, hide_table_tour, hide_bonus_tour FROM tournaments WHERE id = ?', $this->id);
		$flags = (int)$flags;
		$table_mode = table_hiding_mode($flags);
		$bonus_mode = bonus_hiding_mode($flags);

		$has_round = array(0 => true);
		foreach ($this->rounds as $round_row)
		{
			$has_round[(int)$round_row[1]] = true;
		}
		foreach (array($table_mode, $bonus_mode) as $mode)
		{
			$r = hiding_mode_round($mode);
			if (!is_null($r))
			{
				$has_round[$r] = true; // even if the round has gone since
			}
		}

		// In the order the rounds are played.
		$table_options = array(
			TOURNAMENT_HIDE_NONE => get_label('Show the scoring table'),
			TOURNAMENT_HIDE_ALL => get_label('Hide the scoring table'),
			TOURNAMENT_HIDE_FROM_MAIN_ROUND => get_label('Hide the scoring table starting from the main round'),
			TOURNAMENT_HIDE_FROM_QUARTER_FINALS => get_label('Hide the scoring table starting from the quarter-finals'),
			TOURNAMENT_HIDE_FROM_SEMI_FINALS => get_label('Hide the scoring table starting from the semi-finals'),
			TOURNAMENT_HIDE_FROM_FINALS => get_label('Hide the scoring table in the finals'));
		$bonus_options = array(
			TOURNAMENT_HIDE_NONE => get_label('Show bonus points'),
			TOURNAMENT_HIDE_ALL => get_label('Hide bonus points'),
			TOURNAMENT_HIDE_FROM_MAIN_ROUND => get_label('Hide bonus points starting from the main round'),
			TOURNAMENT_HIDE_FROM_QUARTER_FINALS => get_label('Hide bonus points starting from the quarter-finals'),
			TOURNAMENT_HIDE_FROM_SEMI_FINALS => get_label('Hide bonus points starting from the semi-finals'),
			TOURNAMENT_HIDE_FROM_FINALS => get_label('Hide bonus points in the finals'));

		$this->navBar();

		echo '<p><table class="bordered light" width="100%">';
		echo '<tr><td width="160">' . get_label('Scoring table') . ':</td><td>';
		$this->hidingSelect('hide-table', $table_options, $table_mode, $has_round);
		echo ' <span id="hide-table-tour-span"></span></td></tr>';
		echo '<tr id="hide-bonus-row"><td>' . get_label('Bonus points') . ':</td><td>';
		$this->hidingSelect('hide-bonus', $bonus_options, $bonus_mode, $has_round);
		echo ' <span id="hide-bonus-tour-span"></span></td></tr>';
		echo '</table></p>';
	}

	private function hidingSelect($id, $options, $selected, $has_round)
	{
		echo '<select id="' . $id . '" onchange="hidingChanged()">';
		foreach ($options as $mode => $text)
		{
			$r = hiding_mode_round($mode);
			if (is_null($r) || isset($has_round[$r]))
			{
				show_option($mode, $selected, $text);
			}
		}
		echo '</select>';
	}

	private function showPairs()
	{
		global $_lang;

		$this->navBar();

		$pairs = get_tournament_pairs($this->id, $this->club_id, $_lang, true);
		$user_pic = new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));

		// The pairs as a list of the v3 design (see ratings.php): the button that makes one in a
		// toolbar, the table in a sheet.
		echo '<div class="toolbar"><a class="btn tb-create" href="#" onclick="createPair(); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Create new pair') . '</span></a></div>';
		echo '<div class="sheet"><table class="tbl"><thead><tr><th scope="col"></th>';
		echo '<th scope="col">' . get_label('Player [0]', 1) . '</th>';
		echo '<th scope="col">' . get_label('Player [0]', 2) . '</th>';
		echo '<th scope="col">' . get_label('Policy') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Where the policy is set') . '</th></tr></thead><tbody>';
		foreach ($pairs as $pair)
		{
			echo '<tr>';
			echo '<td class="tb-btns"><button class="icon" onclick="deletePair(' . $pair->user1_id . ',' . $pair->user2_id . ')" title="' . get_label('Delete pair') . '"><img src="images/delete.png"></button></td>';

			$user_pic->
				set($pair->user1_id, $pair->user1_name, $pair->user1_tournament_flags, 't' . $this->id)->
				set($pair->user1_id, $pair->user1_name, $pair->user1_club_flags, 'c' . $this->club_id)->
				set($pair->user1_id, $pair->user1_name, $pair->user1_flags);
			echo '<td><a class="who" href="user_info.php?id=' . $pair->user1_id . '&bck=1">' . card_avatar_of($user_pic, $pair->user1_id, $pair->user1_name, '') .
				'<span class="who-txt"><span class="who-name">' . $pair->user1_name . '</span></span></a></td>';

			$user_pic->
				set($pair->user2_id, $pair->user2_name, $pair->user2_tournament_flags, 't' . $this->id)->
				set($pair->user2_id, $pair->user2_name, $pair->user2_club_flags, 'c' . $this->club_id)->
				set($pair->user2_id, $pair->user2_name, $pair->user2_flags);
			echo '<td><a class="who" href="user_info.php?id=' . $pair->user2_id . '&bck=1">' . card_avatar_of($user_pic, $pair->user2_id, $pair->user2_name, '') .
				'<span class="who-txt"><span class="who-name">' . $pair->user2_name . '</span></span></a></td>';

			echo '<td>' . get_pair_policy_name($pair->policy) . '</td>';
			echo '<td class="p2 dim">' . $pair->source . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	protected function show_body()
	{
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id);

		// This page runs the optimizer too - from the offer made after a seating is generated,
		// and from the hash shown next to a round - so it needs the texts as much as the seating
		// pages do. Without them the dialogs fall back to the English built into mr.js.
		show_seating_optimizer_labels();

		$tabs = array();
		if ($this->canEditDetails())
		{
			$tabs[PREP_TAB_DETAILS] = array(details_tab_name(), true);
			$tabs[PREP_TAB_SERIES] = array(get_label('Series'), true);
		}
		$tabs[PREP_TAB_SCHEME] = array(get_label('Scheme'), true);
		$tabs[PREP_TAB_RULES] = array(get_label('Rules'), true);
		$tabs[PREP_TAB_TABLE] = array(get_label('Hiding the table'), true);
		$tabs[PREP_TAB_REGISTRATIONS] = array(get_label('Registrations'), true);
		// Not held back with the rest of them. Where the streams are has nothing to do with who
		// has registered, and this is the one tab a tournament played years ago still has a use
		// for: its games can be tied to its recordings from here.
		$tabs[PREP_TAB_STREAMS] = array(get_label('Streams'), true);
		// A long-term tournament is not seated, so it has neither the pairs nor the seatings; the
		// others are held back until the scheme holds and everybody it needs has registered.
		if (!$this->long_term)
		{
			$open = $this->scheme_valid && $this->regs_complete;
			$tabs[PREP_TAB_PAIRS] = array(get_label('Pairs'), $open);
			foreach ($this->rounds as $i => $round_row)
			{
				$tabs[PREP_TAB_SEATING + $i] = array(get_label('Seating for [0]', get_round_name($round_row[1])), $open);
			}
		}
		$this->wizardBegin($tabs);

		switch ($this->tab)
		{
			case PREP_TAB_DETAILS:
				$this->showDetailsTab('form/tournament_edit.php?id=' . $this->id, NULL, '#form-series');
				break;
			case PREP_TAB_SERIES:
				$this->showDetailsTab('form/tournament_edit.php?id=' . $this->id, '#form-series');
				break;
			case PREP_TAB_SCHEME:
				$this->showSchemeTab();
				break;
			case PREP_TAB_RULES:
				$this->showRulesTab();
				break;
			case PREP_TAB_TABLE:
				$this->showTableTab();
				break;
			case PREP_TAB_REGISTRATIONS:
				$this->showRegistrationsTab();
				break;
			case PREP_TAB_STREAMS:
				$this->showStreamsTab();
				break;
			case PREP_TAB_PAIRS:
				if (!$this->long_term)
				{
					$this->showPairs();
				}
				break;
			default:
				if ($this->tab >= PREP_TAB_SEATING && !$this->long_term)
				{
					$this->showSeatingTab($this->tab - PREP_TAB_SEATING);
				}
				break;
		}
		$this->wizardEnd($this->tab < 0 ? wizard_summary_live(get_label('Tournament'), $this->summaryLogo()) : $this->summary());
	}

	// The logo of the tournament at the top of the summary. A logo uploaded on the first tab is
	// changed here too: it is found by its code, like the one in the form.
	private function summaryLogo()
	{
		$pic = new Picture(TOURNAMENT_PICTURE);
		$pic->set($this->id, $this->name, $this->flags);
		return PageBase::capture(function() use ($pic) { $pic->show(TNAILS_DIR, false); });
	}

	// Beside the card: what the tournament is - its name, when and where, its series, its rounds and
	// the rules it is played by. Who has registered is on the tab of the registrations.
	private function summary()
	{
		global $_lang;

		$series = array();
		$series_pic = new SeriesPicture();
		foreach ($this->series as $s)
		{
			$series[] = array('', wizard_entity($series_pic->set($s->series_id, $s->series_name, $s->series_flags, $s->owner), $s->series_name,
				$s->stars > 0 ? tournament_stars_str($s->stars, false) : ''));
		}

		// The rules are named by the scoring system, which is what tells tournaments apart; the
		// rules of the game and the special awards are left to their tabs.
		list($scoring_name) = Db::record(get_label('tournament'),
			'SELECT ns.name FROM tournaments t' .
			' LEFT OUTER JOIN scorings s ON s.id = t.scoring_id LEFT OUTER JOIN names ns ON ns.id = s.name_id AND (ns.langs & ' . $_lang . ') <> 0' .
			' WHERE t.id = ?', $this->id);

		// Of the scheme, just how many play: the most any round has, which is the main round.
		$players_count = NULL;
		foreach ($this->rounds as $row)
		{
			$players = $row[3];
			if (!is_null($players) && $players > 0 && (is_null($players_count) || $players > $players_count))
			{
				$players_count = (int)$players;
			}
		}
		return wizard_summary(get_label('Tournament'), array(
			details_tab_name() => array(
				array(get_label('Tournament name'), $this->name),
				array(wizard_period_label($this->start_time, $this->duration, $this->timezone), wizard_period($this->start_time, $this->duration, $this->timezone)),
				array(get_label('Club'), $this->club_name),
				array(get_label('Address'), $this->address_name),
				array(get_label('Admission rate'), (!is_null($this->currency_pattern) && !is_null($this->fee)) ? format_currency($this->fee, $this->currency_pattern) : NULL),
				array(get_label('Players'), $players_count),
				array(get_label('Rules'), $scoring_name)),
			get_label('Series') => $series), $this->summaryLogo());
	}

	protected function js()
	{
		$this->prepScripts();
?>
		function moveRegistration(userId, flagsFilter)
		{
			json.post('api/ops/tournament.php',
			{
				op: 'move_registration_up',
				tournament_id: <?php echo $this->id; ?>,
				user_id: userId,
				flags_filter: flagsFilter,
			}, refr);
		}

		function moveTeamUp(teamId, flagsFilter)
		{
			json.post('api/ops/tournament.php',
			{
				op: 'move_registration_up',
				tournament_id: <?php echo $this->id; ?>,
				team_id: teamId,
				flags_filter: flagsFilter,
			}, refr);
		}

		function rollbackTournamentReg(userId)
		{
			json.post('api/ops/tournament.php',
			{
				op: 'rollback_registration',
				tournament_id: <?php echo $this->id; ?>,
				user_id: userId,
			}, refr);
		}

		function removeTournamentRegFlag(userId, newFlags, confirmMessage)
		{
			function _remove()
			{
				json.post('api/ops/tournament.php',
				{
					op: 'edit_registration',
					tournament_id: <?php echo $this->id; ?>,
					user_id: userId,
					access_flags: newFlags,
				}, refr);
			}
			dlg.yesNo(confirmMessage, null, null, _remove);
		}

		// One flag of the tournament on or off, the others left as they are.
		function setTournamentFlag(flag, on)
		{
			json.post("api/ops/tournament.php",
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				flags: on ? flag : 0,
				flags_mask: flag,
			});
		}

		// An award, and the part of it that is shown only while it is on.
		function awardChanged(box, flag, subFlag)
		{
			setTournamentFlag(flag, box.checked);
			if (subFlag)
			{
				$('#award-sub-' + subFlag).toggle(box.checked);
			}
		}

		function rulesChanged()
		{
			json.post("api/ops/tournament.php",
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				rules_code: $('#prep-rules').val(),
			});
		}

		// Called by the scoring select whenever anything in it is changed - the scoring, its
		// version, its options or the normalizer - with all of them.
		function scoringChanged(s)
		{
			json.post("api/ops/tournament.php",
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				scoring_id: s.sId,
				scoring_version: s.sVer,
				scoring_options: JSON.stringify(s.ops),
				normalizer_id: s.nId,
				normalizer_version: s.nId > 0 ? s.nVer : -1,
			});
		}

		// Long-term or not. A tournament stops being long-term with no single games assigned to it.
		function longTermChanged(on)
		{
			json.post("api/ops/tournament.php",
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				flags: on ? <?php echo TOURNAMENT_FLAG_LONG_TERM; ?> : 0,
				flags_mask: <?php echo TOURNAMENT_FLAG_LONG_TERM | TOURNAMENT_FLAG_SINGLE_GAME; ?>,
			}, refr);
		}

		// The rounds are added and deleted on the server, which reloads the page; what was typed
		// into the scheme is saved first, so that it is not lost with the reload.
		function addRound()
		{
			applyScheme(function()
			{
				json.post('api/ops/tournament.php', { op: 'add_round', tournament_id: <?php echo $this->id; ?> }, refr);
			});
		}

		function deleteRound(eventId, message)
		{
			dlg.yesNo(message, null, null, function()
			{
				applyScheme(function()
				{
					json.post('api/ops/tournament.php', { op: 'delete_round', event_id: eventId }, refr);
				});
			});
		}

		function createPair()
		{
			dlg.form("form/pair_create.php?tournament_id=<?php echo $this->id; ?>", refr, 600);
		}

		function deletePair(user1Id, user2Id)
		{
			var html = '<p><?php echo get_label('Are you sure you want to delete this pair?'); ?></p>';
			html += '<p><input type="checkbox" id="delete-pair-tournament-only"> <?php echo get_label('for this tournament only'); ?></p>';
			dlg.custom(html, "<?php echo get_label('Confirmation'); ?>", null,
			{
				yes: { id: "dlg-yes", text: "<?php echo get_label('Yes'); ?>", click: function()
				{
					var global = $('#delete-pair-tournament-only').is(':checked') ? 0 : 1;
					$(this).dialog('close');
					json.post("api/ops/seating.php",
					{
						op: "delete_pair"
						, tournament_id: <?php echo $this->id; ?>
						, user1_id: user1Id
						, user2_id: user2Id
						, global: global
					}, refr);
				}},
				no: { id: "dlg-no", text: "<?php echo get_label('No'); ?>", click: function() { $(this).dialog('close'); } }
			});
		}

		function applyScheme(onSuccess)
		{
			if ($('tr[data-event-id]').length == 0)
			{
				// Nothing to save, and no team size to save it with either.
				if (onSuccess)
				{
					onSuccess();
				}
				return;
			}
			var rounds = [];
			$('tr[data-event-id]').each(function()
			{
				var row = $(this);
				var round = { event_id: row.data('event-id'), is_main: row.data('is-main') == 1 };
				round.group = row.find('select[data-field="group"]').val();
				row.find('input[data-field]').each(function()
				{
					var val = $(this).val();
					var field = $(this).data('field');
					if (field == 'weight')
					{
						round.weight = val === '' ? 1 : parseFloat(val);
					}
					else
					{
						round[field] = val === '' ? 0 : parseInt(val);
					}
				});
				rounds.push(round);
			});
			json.post('api/ops/tournament.php',
			{
				op: 'set_scheme',
				tournament_id: <?php echo $this->id; ?>,
				rounds: JSON.stringify(rounds),
				team_size: Math.max(1, parseInt($('#scheme-team-size').val()) || 1),
			}, onSuccess || refr);
		}

		function goToTab(tabIdx)
		{
			if (tabIdx < 0)
			{
				goTo({tab: tabIdx});
				return;
			}
			json.post('api/ops/tournament.php',
			{
				op: 'change',
				tournament_id: <?php echo $this->id; ?>,
				preparation_stage: tabIdx,
			}, function() { goTo({tab: tabIdx}); });
		}

		function advancePreparationStage()
		{
			goToTab(<?php echo $this->tab + 1; ?>);
		}

		function schemeInputChanged(input)
		{
			var row = $(input).closest('tr');
			var players = parseInt(row.find('input[data-field="players"]').val()) || 0;
			var maxTables = Math.floor(players / 10);
			var tablesInput = row.find('input[data-field="tables"]');
			if (maxTables > 0)
			{
				tablesInput.attr('max', maxTables);
				if ((parseInt(tablesInput.val()) || 0) > maxTables)
				{
					tablesInput.val(maxTables);
				}
			}
			else
			{
				tablesInput.removeAttr('max');
			}
			validateSchemeNext();
		}

		var regsComplete = <?php echo $this->regs_complete ? 'true' : 'false'; ?>;

		function validateSchemeNext()
		{
			var valid = <?php echo $this->long_term ? 'true' : '$(\'tr[data-event-id]\').length > 0'; ?>;
			$('tr[data-event-id]').each(function()
			{
				var row = $(this);
				var players = parseInt(row.find('input[data-field="players"]').val()) || 0;
				var tables  = parseInt(row.find('input[data-field="tables"]').val())  || 0;
				var games   = parseInt(row.find('input[data-field="games"]').val())   || 0;
				if (players < 10 || tables < 1 || games < 1 || tables > Math.floor(players / 10))
				{
					valid = false;
				}
			});
			$('#prep-next-btn').prop('disabled', !valid);

			return valid;
		}

		// The scheme is saved as soon as a field of it is changed - there is no button to apply it -
		// but only while it holds: half typed in, it would only be refused.
		function saveScheme()
		{
			if (validateSchemeNext())
			{
				applyScheme(function() {});
			}
		}

		function nextStep()
		{
<?php if ($this->tab < 0): ?>
			detailsCommit(function() { goToTab(<?php echo $this->tab + 1; ?>); });
<?php elseif ($this->tab == PREP_TAB_SCHEME): ?>
			applyScheme(advancePreparationStage);
<?php else: ?>
			advancePreparationStage();
<?php endif; ?>
		}

<?php if ($this->tab == PREP_TAB_SCHEME): ?>
		validateSchemeNext();
<?php
			$leagues = $this->schemaLeagues();
			$schemas = array();
			foreach ($leagues as $l)
			{
				$schemas[$l->id] = $l->schemas;
			}
?>
		// The schemas of each league offered, by league id.
		var schemaLists = <?php echo json_encode((object)$schemas); ?>;

		function schemaLeagueChanged()
		{
			var sel = $('#schema-sel');
			sel.find('option').remove();
			sel.append($('<option>').val(0).text(''));
			var list = schemaLists[$('#schema-league').val()] || [];
			for (var s of list)
			{
				sel.append($('<option>').val(s.id).text(s.name));
			}
			sel.val(0);
			sel.toggle(list.length > 0);
		}
		schemaLeagueChanged();

		// The schema is taken the moment it is picked, once that is confirmed. The list goes
		// back to empty either way: nothing remembers which schema a tournament took.
		function schemaChanged()
		{
			var sel = $('#schema-sel');
			var schemaId = sel.val();
			var name = sel.find('option:selected').text();
			sel.val(0);
			if (schemaId <= 0)
			{
				return;
			}
			var apply = function()
			{
				json.post('api/ops/tournament.php', { op: 'apply_schema', tournament_id: <?php echo $this->id; ?>, schema_id: schemaId }, refr);
			};
<?php
		// Nothing is asked when there is nothing to lose: no rounds but the empty main round every
		// new tournament has.
		$has_rounds = count($this->rounds) > 1;
		foreach ($this->rounds as $round_row)
		{
			if ((int)$round_row[3] > 0 || (int)$round_row[4] > 0 || (int)$round_row[5] > 0)
			{
				$has_rounds = true;
			}
		}
		if (!$has_rounds)
		{
?>
			apply();
			return;
<?php
		}
?>
			dlg.yesNo('<?php echo addslashes(get_label('Are you sure you want to set the tournament up by [0]? Its rounds, rules, scoring, special awards and the hiding of the table will be replaced.', '#')); ?>'.replace('#', $('<div>').text(name).html()), null, null, apply);
		}
<?php endif; ?>
<?php
		if ($this->tab == PREP_TAB_TABLE)
		{
			list ($table_tour, $bonus_tour) = Db::record(get_label('tournament'), 'SELECT hide_table_tour, hide_bonus_tour FROM tournaments WHERE id = ?', $this->id);
			$mode_rounds = array();
			$tours = array();
			foreach (array(TOURNAMENT_HIDE_FROM_MAIN_ROUND, TOURNAMENT_HIDE_FROM_QUARTER_FINALS, TOURNAMENT_HIDE_FROM_SEMI_FINALS, TOURNAMENT_HIDE_FROM_FINALS) as $mode)
			{
				$r = hiding_mode_round($mode);
				$mode_rounds[$mode] = $r;
				$tours[$r] = hiding_round_tours($this->id, $r);
			}
?>
		// The tour a hiding starts from, for a hiding that starts with a round. The main round
		// cannot be hidden from its start: that is hiding the table altogether, which is the
		// entry above it.
		var hidingModeRounds = <?php echo json_encode((object)$mode_rounds); ?>;
		var hidingTours = <?php echo json_encode((object)$tours); ?>;
		var hidingTour = { 'hide-table': <?php echo (int)$table_tour; ?>, 'hide-bonus': <?php echo (int)$bonus_tour; ?> };

		function hidingTourSelect(id)
		{
			var mode = $('#' + id).val();
			if (!(mode in hidingModeRounds))
			{
				return '';
			}
			var round = hidingModeRounds[mode];
			var count = hidingTours[round] > 0 ? hidingTours[round] : 20;
			var tour = hidingTour[id];
			count = Math.max(count, tour);
			var html = '<select id="' + id + '-tour" onchange="hidingChanged()">';
			if (round != 0)
			{
				html += '<option value="0"' + (tour <= 1 ? ' selected' : '') + '><?php echo get_label('from the start of the round'); ?></option>';
			}
			for (var i = 2; i <= count; ++i)
			{
				html += '<option value="' + i + '"' + (tour == i || (round == 0 && i == 2 && tour <= 1) ? ' selected' : '') + '>' + '<?php echo get_label('starting from tour [0]', '#'); ?>'.replace('#', i) + '</option>';
			}
			return html + '</select>';
		}

		function showHidingTours()
		{
			$('#hide-table-tour-span').html(hidingTourSelect('hide-table'));
			$('#hide-bonus-tour-span').html(hidingTourSelect('hide-bonus'));
			$('#hide-bonus-row').toggle($('#hide-table').val() != <?php echo TOURNAMENT_HIDE_ALL; ?>);
		}
		showHidingTours();

		// A tour belongs to its round: another round starts from the start of it.
		var hidingMode = { 'hide-table': $('#hide-table').val(), 'hide-bonus': $('#hide-bonus').val() };
		function readHidingTour(id)
		{
			var t = $('#' + id + '-tour');
			if ($('#' + id).val() != hidingMode[id])
			{
				hidingMode[id] = $('#' + id).val();
				hidingTour[id] = 0;
			}
			else
			{
				hidingTour[id] = t.length > 0 ? parseInt(t.val()) : 0;
			}
		}

		function hidingChanged()
		{
			readHidingTour('hide-table');
			readHidingTour('hide-bonus');
			showHidingTours();
			hidingTour['hide-table'] = Math.max(hidingTour['hide-table'], $('#hide-table-tour').length > 0 ? parseInt($('#hide-table-tour').val()) : 0);
			hidingTour['hide-bonus'] = Math.max(hidingTour['hide-bonus'], $('#hide-bonus-tour').length > 0 ? parseInt($('#hide-bonus-tour').val()) : 0);
			json.post('api/ops/tournament.php',
			{
				op: 'set_hiding',
				tournament_id: <?php echo $this->id; ?>,
				table: $('#hide-table').val(),
				table_tour: hidingTour['hide-table'],
				bonus: $('#hide-bonus').val(),
				bonus_tour: hidingTour['hide-bonus'],
			});
		}
<?php
		}
?>
<?php
	}
}

$page = new Page();
$page->run(get_label('Preparation'));

?>
