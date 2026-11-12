<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/seating.php';
require_once 'include/preparation.php';
require_once 'include/table_hiding.php';
require_once 'include/scoring.php';
require_once 'include/rules.php';

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
	private $tournament_type;
	private $scheme_players;
	private $scheme_tables;
	private $reg_players;
	private $reg_referees;
	private $regs_complete;
	private $prep_tournament_id;   // see the trait: these rounds all belong to this tournament
	private $scheme_valid;

	// Everything after the registrations waits for them to be complete. The streams tab is the
	// one exception - what is set up there does not depend on who is coming - but the step off
	// it does, because it leads to the pairs. See show_body().
	protected function nextDisabled()
	{
		if (!$this->regs_complete && $this->tab == PREP_TAB_REGISTRATIONS)
		{
			return true;
		}
		if (!$this->scheme_valid && $this->tab == PREP_TAB_TABLE)
		{
			return true; // the registrations it leads to wait for the scheme
		}
		return (!$this->scheme_valid || !$this->regs_complete) && $this->tab == PREP_TAB_STREAMS;
	}

	protected function prepare()
	{
		parent::prepare();

		if (isset($_REQUEST['tab']))
		{
			$this->tab = (int)$_REQUEST['tab'];
		}

		$this->prep_tournament_id = $this->id;
		$query = new DbQuery('SELECT id, round, misc, players, tables, games FROM events WHERE tournament_id = ? ORDER BY round', $this->id);
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

		list($this->tournament_type, $preparation_stage) = Db::record(get_label('tournament'), 'SELECT type, preparation_stage FROM tournaments WHERE id = ?', $this->id);

		$this->tab_count = PREP_TAB_SEATING + count($this->rounds);

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

		$this->scheme_valid = !empty($this->rounds);
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
		if ($this->tab < PREP_TAB_SCHEME || $this->tab >= $this->tab_count)
		{
			$this->tab = PREP_TAB_SCHEME;
		}
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
			'SELECT tr.user_id, nu.name, u.email, u.flags, tr.flags, cr.flags, ni.name, u.club_id, c.name, c.flags FROM tournament_regs tr' .
			' JOIN users u ON u.id = tr.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
			' LEFT JOIN clubs c ON c.id = u.club_id' .
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
				'SELECT tr.user_id, nu.name, u.email, u.flags, tr.flags, cr.flags, ni.name, u.club_id, c.name, c.flags, tr.team_id, t.name FROM tournament_regs tr' .
				' JOIN users u ON u.id = tr.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
				' JOIN cities i ON i.id = u.city_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
				' LEFT JOIN clubs c ON c.id = u.club_id' .
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
			echo '<p><b>' . get_label('Not confirmed') . '</b></p>';
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th darker">';
			echo '<td width="160" align="center"></td>';
			echo '<td width="40"></td>';
			echo '<td>' . get_label('Player') . '</td>';
			echo '<td width="60" align="center">' . get_label('Club') . '</td>';
			echo '<td width="200">' . get_label('Email') . '</td>';
			echo '</tr>';
			foreach ($unconfirmed as $i => $row)
			{
				list($user_id, $name, $email, $user_flags, $tr_flags, $club_reg_flags, $city, $club_id, $club_name, $club_flags) = $row;
				echo '<tr class="light">';
				echo '<td class="dark" align="center" nowrap>';
				echo '<button onclick="mr.acceptTournamentReg(' . $this->id . ',' . $user_id . ')">' . get_label('Accept application') . '</button>';
				echo '</td>';
				echo '<td align="center">' . ($i + 1) . '</td>';
				echo '<td style="position:relative;">' . $this->exhibitionBadge($tr_flags) . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city) . '</td>';
				echo '<td align="center">' . $this->clubCell((int)$club_id, (string)$club_name, (int)$club_flags) . '</td>';
				echo '<td>' . $email . '</td>';
				echo '</tr>';
			}
			echo '</table></p>';
		}
	}

	private function personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city)
	{
		ob_start();
		echo '<table class="transp"><tr>';
		echo '<td width="54" align="center" valign="middle">';
		// The tournament's own photo of the player first, then the club's, then the account one
		// - the same order as everywhere else the player is drawn, and as the photo dialog.
		$pic->set($user_id, $name, $tr_flags, 't' . $this->id)->
			set($user_id, $name, $club_reg_flags, 'c' . $this->club_id)->
			set($user_id, $name, $user_flags);
		$pic->show(ICONS_DIR, true, 50);
		echo '</td>';
		echo '<td valign="middle"><a href="user_info.php?id=' . $user_id . '&bck=1"><b>' . $name . '</b></a><br>' . $city . '</td>';
		echo '</tr></table>';
		return ob_get_clean();
	}

	private function exhibitionBadge($tr_flags)
	{
		if (($tr_flags & USER_FLAG_EXHIBITION_PLAYER) == 0)
		{
			return '';
		}
		return '<span title="' . get_label('Excluded from tournament standings and series scoring.') . '" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#a06060;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;">' . get_label('exhibition player') . '</span>';
	}

	private function clubCell($club_id, $club_name, $club_flags)
	{
		if ($club_id <= 0) { return ''; }
		ob_start();
		echo '<a href="club_main.php?id=' . $club_id . '&bck=1">';
		$this->club_pic->set($club_id, $club_name, $club_flags);
		$this->club_pic->show(ICONS_DIR, true, 50);
		echo '</a>';
		return ob_get_clean();
	}

	private function emptyPersonCell()
	{
		return '<table class="transp"><tr><td width="54"><img src="images/transp.png" width="50" height="50" border="0"></td><td></td></tr></table>';
	}

	private function showRegTable($title, $regs, $scheme_count, $default_flags, $person_label, $with_rollback = false)
	{
		$pic = new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));
		$reg_count = count($regs);
		$row_count = max($scheme_count, $reg_count);
		$btn_width = $with_rollback ? 120 : 90;

		echo '<p><b>' . $title . '</b></p>';
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="' . $btn_width . '" align="center"><button class="icon" onclick="dlg.form(\'form/registration_create.php?tournament_id=' . $this->id . '&flags=' . $default_flags . '\', refr, 400)" title="' . get_label('Add registration to [0].', $this->name) . '"><img src="images/create.png" border="0"></button></td>';
		echo '<td width="40"></td>';
		echo '<td>' . $person_label . '</td>';
		echo '<td width="60" align="center">' . get_label('Club') . '</td>';
		echo '<td width="200">' . get_label('Email') . '</td>';
		echo '<td width="40"></td>';
		echo '</tr>';

		for ($i = 0; $i < $row_count; ++$i)
		{
			$overflow = ($scheme_count > 0 && $i >= $scheme_count);
			echo '<tr class="' . ($overflow ? 'darker' : 'light') . '">';
			if ($i < $reg_count)
			{
				list($user_id, $name, $email, $user_flags, $tr_flags, $club_reg_flags, $city, $club_id, $club_name, $club_flags) = $regs[$i];
				echo '<td align="center" nowrap>';
				echo '<button class="icon" onclick="mr.editTournamentReg(' . $this->id . ', ' . $user_id . ')" title="' . get_label('Change [0] registration.', $name) . '"><img src="images/edit.png" border="0"></button>';
				$new_flags = ($tr_flags & USER_PERM_MASK) & ~$default_flags;
				echo '<button class="icon" onclick="removeTournamentRegFlag(' . $user_id . ', ' . $new_flags . ', \'' . get_label('Are you sure you want to unregister [0]?', $name) . '\')" title="' . get_label('Unregister [0].', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.tournamentUserPhoto(' . $user_id . ', ' . $this->id . ')" title="' . get_label('Set [0] photo for [1].', $name, $this->name) . '"><img src="images/photo.png" border="0"></button>';
				if ($with_rollback)
				{
					echo '<button class="icon" onclick="rollbackTournamentReg(' . $user_id . ')" title="' . get_label('Rollback [0] to not confirmed state.', $name) . '"><img src="images/undo.png" border="0"></button>';
				}
				echo '</td>';
				echo '<td align="center">' . ($i + 1) . '</td>';
				echo '<td style="position:relative;">' . $this->exhibitionBadge($tr_flags) . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city) . '</td>';
				echo '<td align="center">' . $this->clubCell((int)$club_id, (string)$club_name, (int)$club_flags) . '</td>';
				echo '<td>' . $email . '</td>';
				$up_style = ($i == 0) ? ' style="visibility:hidden"' : '';
				echo '<td align="center"><button class="icon"' . $up_style . ' onclick="moveRegistration(' . $user_id . ', ' . $default_flags . ')" title="' . get_label('Move up') . '"><img src="images/up.png" border="0"></button></td>';
			}
			else
			{
				echo '<td></td>';
				echo '<td align="center">' . ($i + 1) . '</td>';
				echo '<td>' . $this->emptyPersonCell() . '</td>';
				echo '<td></td>';
				echo '<td></td>';
				echo '<td></td>';
			}
			echo '</tr>';
		}

		echo '</table></p>';
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

		echo '<p><b>' . get_label('Players') . '</b></p>';
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="120" align="center"><button class="icon" onclick="dlg.form(\'form/registration_create.php?tournament_id=' . $this->id . '&flags=' . $default_flags . '\', refr, 400)" title="' . get_label('Add registration to [0].', $this->name) . '"><img src="images/create.png" border="0"></button></td>';
		echo '<td width="40"></td>';
		echo '<td width="100">' . get_label('Team') . '</td>';
		echo '<td>' . get_label('Player') . '</td>';
		echo '<td width="60" align="center">' . get_label('Club') . '</td>';
		echo '<td width="200">' . get_label('Email') . '</td>';
		echo '<td width="40"></td>';
		echo '</tr>';

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
				echo '<tr class="' . ($overflow ? 'darker' : 'light') . '">';

				echo '<td align="center" nowrap>';
				echo '<button class="icon" onclick="mr.editTournamentReg(' . $this->id . ', ' . $user_id . ')" title="' . get_label('Change [0] registration.', $name) . '"><img src="images/edit.png" border="0"></button>';
				$new_flags = ($tr_flags & USER_PERM_MASK) & ~$default_flags;
				echo '<button class="icon" onclick="removeTournamentRegFlag(' . $user_id . ', ' . $new_flags . ', \'' . get_label('Are you sure you want to unregister [0]?', $name) . '\')" title="' . get_label('Unregister [0].', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.tournamentUserPhoto(' . $user_id . ', ' . $this->id . ')" title="' . get_label('Set [0] photo for [1].', $name, $this->name) . '"><img src="images/photo.png" border="0"></button>';
				echo '<button class="icon" onclick="rollbackTournamentReg(' . $user_id . ')" title="' . get_label('Rollback [0] to not confirmed state.', $name) . '"><img src="images/undo.png" border="0"></button>';
				echo '</td>';

				echo '<td align="center">' . (++$row_num) . '</td>';

				if ($j == 0)
				{
					if ($team_id !== null)
					{
						echo '<td' . $rowspan . ' align="center"><b>' . htmlspecialchars((string)$team_name) . '</b></td>';
					}
					else
					{
						echo '<td' . $rowspan . ' class="dark" align="center">' . get_label('No team') . '</td>';
					}
				}

				echo '<td style="position:relative;">' . $this->exhibitionBadge($tr_flags) . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $club_reg_flags, $city) . '</td>';
				echo '<td align="center">' . $this->clubCell((int)$club_id, (string)$club_name, (int)$club_flags) . '</td>';
				echo '<td>' . $email . '</td>';

				if ($j == 0)
				{
					echo '<td' . $rowspan . ' align="center">';
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
			echo '<tr class="light">';
			echo '<td></td>';
			echo '<td align="center">' . ($row_num + 1) . '</td>';
			echo '<td></td>';
			echo '<td>' . $this->emptyPersonCell() . '</td>';
			echo '<td></td>';
			echo '<td></td>';
			echo '<td></td>';
			echo '</tr>';
		}

		echo '</table></p>';
	}

	private function showSchemeTab()
	{
		if (empty($this->rounds))
		{
			echo '<p>' . get_label('This tournament has no rounds. Select a tournament type to create rounds automatically.') . '</p>';
		}

		echo '<p><table width="100%"><tr>' . $this->backCell() . '<td>';
		show_tournament_type_select($this->tournament_type, 'tournament-type', 'changeTournamentType()');
		echo '</td><td align="right">';
		$this->nextButton();
		echo '</td></tr></table></p>';

		if (empty($this->rounds))
		{
			return;
		}

		$num_rounds = count($this->rounds);
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td>' . get_label('Round') . '</td>';
		echo '<td>' . get_label('Players') . '</td>';
		echo '<td>' . get_label('Tables') . '</td>';
		echo '<td>' . get_label('Games per player') . '</td>';
		echo '<td>' . get_label('Team size') . '</td>';
		echo '</tr>';

		$first_row = true;
		foreach ($this->rounds as $row)
		{
			list($event_id, $round_num, $misc, $players, $tables, $games) = $row;

			$players_val = is_null($players) ? '' : $players;
			$tables_val  = is_null($tables)  ? '' : $tables;
			$games_val   = is_null($games)   ? '' : $games;

			$inp_base = ' type="number" style="width:60px;" oninput="schemeInputChanged(this)"';
			$players_int = ($players_val !== '') ? (int)$players_val : 0;
			$max_tables = ($players_int >= 10) ? (int)floor($players_int / 10) : 0;
			$inp_tables_max = ($max_tables > 0) ? ' max="' . $max_tables . '"' : '';
			$is_main = ($round_num == 0) ? '1' : '0';
			echo '<tr data-event-id="' . $event_id . '" data-is-main="' . $is_main . '">';
			echo '<td><b>' . get_round_name($round_num) . '</b></td>';
			echo '<td><input' . $inp_base . ' min="10" data-field="players" value="' . $players_val . '"></td>';
			echo '<td><input' . $inp_base . ' min="1"' . $inp_tables_max . ' data-field="tables"  value="' . $tables_val  . '"></td>';
			echo '<td><input' . $inp_base . ' min="1" data-field="games"   value="' . $games_val   . '"></td>';
			if ($first_row)
			{
				$rowspan = $num_rounds > 1 ? ' rowspan="' . $num_rounds . '"' : '';
				echo '<td' . $rowspan . ' align="center"><input id="scheme-team-size" type="number" style="width:60px;" min="1" value="' . (int)$this->team_size . '"></td>';
				$first_row = false;
			}
			echo '</tr>';
		}

		echo '</table></p>';

		// The number of players the tournament counts for is the players of its main round, which
		// the scheme sets. Once it is over it can be corrected by those who actually played.
		list ($tournament_flags) = Db::record(get_label('tournament'), 'SELECT flags FROM tournaments WHERE id = ?', $this->id);
		echo '<p><table width="100%"><tr><td>';
		echo '<input type="checkbox" class="toggle" id="prep-correct-players"' . (($tournament_flags & TOURNAMENT_FLAG_FORCE_NUM_PLAYERS) ? '' : ' checked') .
			' onchange="setTournamentFlag(' . TOURNAMENT_FLAG_FORCE_NUM_PLAYERS . ', !this.checked)"> ' . get_label('correct number of players after the tournament is complete');
		echo '</td><td align="right"><button id="prep-apply-btn" onclick="applyScheme()"' . (!$this->scheme_valid ? ' disabled' : '') . '>' . get_label('Apply') . '</button></td></tr></table></p>';
	}

	// The rules the games are played by, the scoring and its normalizer, and the special awards.
	// Each is saved the moment it is changed. Changing the rules or the scoring changes them in
	// every round as well - see the change op in api/ops/tournament.php.
	private function showRulesTab()
	{
		list ($flags, $rules_code, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $club_name, $club_rules) = Db::record(get_label('tournament'),
			'SELECT t.flags, t.rules, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, c.name, c.rules FROM tournaments t JOIN clubs c ON c.id = t.club_id WHERE t.id = ?', $this->id);
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
		foreach (get_available_rules($this->club_id, $club_name, $club_rules) as $r)
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

		echo '<p><table width="100%"><tr>' . $this->backCell() . '<td align="right">';
		$this->nextButton();
		echo '</td></tr></table></p>';

		echo '<p><table class="bordered light" width="100%">';
		echo '<tr><td width="160">' . get_label('Scoring table') . ':</td><td>';
		$this->hidingSelect('hide-table', $table_options, $table_mode, $has_round);
		echo ' <span id="hide-table-tour-span"></span></td></tr>';
		echo '<tr id="hide-bonus-row"><td>' . get_label('Bonus points') . ':</td><td>';
		$this->hidingSelect('hide-bonus', $bonus_options, $bonus_mode, $has_round);
		echo ' <span id="hide-bonus-tour-span"></span></td></tr>';
		echo '</table></p>';
		echo '<p><i>' . get_label('The games played before the moment the table is hidden from stay in it, and it says which moment it shows. Managers and referees can still see the actual table.') . '</i></p>';
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

		echo '<p><table class="bordered light" width="100%"><tr class="darker"><th width="32"><button class="icon" onclick="createPair()" title="' . get_label('Create new pair') . '"><img src="images/create.png"></button></th>';
		echo '<th width="200">' . get_label('Player [0]', 1) . '</th>';
		echo '<th width="200">' . get_label('Player [0]', 2) . '</th>';
		echo '<th>' . get_label('Policy') . '</th>';
		echo '<th width="200">' . get_label('Where the policy is set') . '</th></tr>';
		foreach ($pairs as $pair)
		{
			echo '<tr>';
			echo '<td><button class="icon" onclick="deletePair(' . $pair->user1_id . ',' . $pair->user2_id . ')" title="' . get_label('Delete pair') . '"><img src="images/delete.png"></button></td>';

			echo '<td><table class="transp" width="100%"><tr><td width="52">';
			$user_pic->
				set($pair->user1_id, $pair->user1_name, $pair->user1_tournament_flags, 't' . $this->id)->
				set($pair->user1_id, $pair->user1_name, $pair->user1_club_flags, 'c' . $this->club_id)->
				set($pair->user1_id, $pair->user1_name, $pair->user1_flags);
			$user_pic->show(ICONS_DIR, false, 48);
			echo '</td><td><a href="user_info.php?id=' . $pair->user1_id . '&bck=1">' . $pair->user1_name . '</a></td></tr></table></td>';

			echo '<td><table class="transp" width="100%"><tr><td width="52">';
			$user_pic->
				set($pair->user2_id, $pair->user2_name, $pair->user2_tournament_flags, 't' . $this->id)->
				set($pair->user2_id, $pair->user2_name, $pair->user2_club_flags, 'c' . $this->club_id)->
				set($pair->user2_id, $pair->user2_name, $pair->user2_flags);
			$user_pic->show(ICONS_DIR, false, 48);
			echo '</td><td><a href="user_info.php?id=' . $pair->user2_id . '&bck=1">' . $pair->user2_name . '</a></td></tr></table></td>';

			echo '<td align="center">' . get_pair_policy_name($pair->policy) . '</td>';
			echo '<td align="center">' . $pair->source . '</td>';
			echo '</tr>';
		}
		echo '</table></p>';
	}

	protected function show_body()
	{
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id);

		// This page runs the optimizer too - from the offer made after a seating is generated,
		// and from the hash shown next to a round - so it needs the texts as much as the seating
		// pages do. Without them the dialogs fall back to the English built into mr.js.
		show_seating_optimizer_labels();

		echo '<div class="tab">';
		echo '<button' . ($this->tab == PREP_TAB_SCHEME ? ' class="active"' : '') . ' onclick="goToTab(' . PREP_TAB_SCHEME . ')">' . get_label('Scheme') . '</button>';
		echo '<button' . ($this->tab == PREP_TAB_RULES ? ' class="active"' : '') . ' onclick="goToTab(' . PREP_TAB_RULES . ')">' . get_label('Rules') . '</button>';
		echo '<button' . ($this->tab == PREP_TAB_TABLE ? ' class="active"' : '') . ' onclick="goToTab(' . PREP_TAB_TABLE . ')">' . get_label('Hiding the table') . '</button>';
		$reg_disabled = !$this->scheme_valid ? ' disabled' : '';
		echo '<button id="prep-tab-registrations"' . ($this->tab == PREP_TAB_REGISTRATIONS ? ' class="active"' : '') . $reg_disabled . ' onclick="goToTab(' . PREP_TAB_REGISTRATIONS . ')">' . get_label('Registrations') . '</button>';
		$locked = (!$this->scheme_valid || !$this->regs_complete) ? ' disabled' : '';
		// Not held back with the rest of them. Where the streams are has nothing to do with who
		// has registered, and this is the one tab a tournament played years ago still has a use
		// for: its games can be tied to its recordings from here.
		echo '<button' . ($this->tab == PREP_TAB_STREAMS ? ' class="active"' : '') . ' onclick="goToTab(' . PREP_TAB_STREAMS . ')">' . get_label('Streams') . '</button>';
		echo '<button class="prep-post-reg-tab' . ($this->tab == PREP_TAB_PAIRS ? ' active' : '') . '"' . $locked . ' onclick="goToTab(' . PREP_TAB_PAIRS . ')">' . get_label('Pairs') . '</button>';
		foreach ($this->rounds as $i => $round_row)
		{
			$tab_idx = PREP_TAB_SEATING + $i;
			$round_name = get_round_name($round_row[1]);
			echo '<button class="prep-post-reg-tab' . ($this->tab == $tab_idx ? ' active' : '') . '"' . $locked . ' onclick="goToTab(' . $tab_idx . ')">' . get_label('Seating for [0]', $round_name) . '</button>';
		}
		echo '</div>';

		switch ($this->tab)
		{
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
				$this->showPairs();
				break;
			default:
				if ($this->tab >= PREP_TAB_SEATING)
				{
					$this->showSeatingTab($this->tab - PREP_TAB_SEATING);
				}
				break;
		}
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

		function changeTournamentType()
		{
			json.post("api/ops/tournament.php",
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				type: $('#tournament-type').val(),
			}, refr);
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
			var rounds = [];
			$('tr[data-event-id]').each(function()
			{
				var row = $(this);
				var round = { event_id: row.data('event-id'), is_main: row.data('is-main') == 1 };
				row.find('input[data-field]').each(function()
				{
					var val = $(this).val();
					round[$(this).data('field')] = val === '' ? 0 : parseInt(val);
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
			var valid = true;
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
			$('#prep-apply-btn').prop('disabled', !valid);
			$('#prep-tab-registrations').prop('disabled', !valid);
			$('.prep-post-reg-tab').prop('disabled', !valid || !regsComplete);
		}

		function nextStep()
		{
<?php if ($this->tab == PREP_TAB_SCHEME): ?>
			applyScheme(advancePreparationStage);
<?php else: ?>
			advancePreparationStage();
<?php endif; ?>
		}

<?php if ($this->tab == PREP_TAB_SCHEME): ?>
		validateSchemeNext();
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
