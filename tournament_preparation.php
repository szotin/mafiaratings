<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/seating.php';

define('PREP_TAB_SCHEME', 0);
define('PREP_TAB_REGISTRATIONS', 1);
define('PREP_TAB_PAIRS', 2);
define('PREP_TAB_SEATING', 3);

class Page extends TournamentPageBase
{
	private $tab;
	private $tab_count;
	private $rounds;
	private $tournament_type;
	private $scheme_players;
	private $scheme_tables;
	private $reg_players;
	private $reg_referees;
	private $regs_complete;
	private $scheme_valid;

	protected function prepare()
	{
		parent::prepare();

		if (isset($_REQUEST['tab']))
		{
			$this->tab = (int)$_REQUEST['tab'];
		}

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

		echo '<p align="right">';
		$this->nextButton();
		echo '</p>';

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
			'SELECT tr.user_id, nu.name, u.email, u.flags, tr.flags, ni.name FROM tournament_regs tr' .
			' JOIN users u ON u.id = tr.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
			' WHERE tr.tournament_id = ?';
		$accepted = ' AND (tr.flags & ' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED . ') = 0';

		$managers = array();
		$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_PERM_MANAGER . ') <> 0' . $accepted . ' ORDER BY nu.name', $this->id);
		while ($row = $query->next()) { $managers[] = $row; }

		$referees = array();
		$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_PERM_REFEREE . ') <> 0' . $accepted . ' ORDER BY tr.reg_order ASC, nu.name', $this->id);
		while ($row = $query->next()) { $referees[] = $row; }

		$players = array();
		$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_PERM_PLAYER . ') <> 0' . $accepted . ' ORDER BY tr.reg_order ASC, nu.name', $this->id);
		while ($row = $query->next()) { $players[] = $row; }

		$unconfirmed = array();
		$query = new DbQuery($sel . ' AND (tr.flags & ' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED . ') <> 0 ORDER BY nu.name', $this->id);
		while ($row = $query->next()) { $unconfirmed[] = $row; }

		if (!empty($managers))
		{
			$this->showRegTable(get_label('Managers'), $managers, 0, USER_PERM_MANAGER, get_label('Manager'));
		}
		$this->showRegTable(get_label('Referees'), $referees, $total_tables, USER_PERM_REFEREE, get_label('Referee'));
		$this->showRegTable(get_label('Players'), $players, $total_players, USER_PERM_PLAYER, get_label('Player'), true);

		if (!empty($unconfirmed))
		{
			$pic = new Picture(USER_TOURNAMENT_PICTURE, $this->user_pic);
			echo '<p><b>' . get_label('Not confirmed') . '</b></p>';
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th darker">';
			echo '<td width="160" align="center"></td>';
			echo '<td width="40"></td>';
			echo '<td>' . get_label('Player') . '</td>';
			echo '<td width="200">' . get_label('Email') . '</td>';
			echo '</tr>';
			foreach ($unconfirmed as $i => $row)
			{
				list($user_id, $name, $email, $user_flags, $tr_flags, $city) = $row;
				echo '<tr class="light">';
				echo '<td class="dark" align="center" nowrap>';
				echo '<button onclick="mr.acceptTournamentReg(' . $this->id . ',' . $user_id . ')">' . get_label('Accept application') . '</button>';
				echo '</td>';
				echo '<td align="center">' . ($i + 1) . '</td>';
				echo '<td>' . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $city) . '</td>';
				echo '<td>' . $email . '</td>';
				echo '</tr>';
			}
			echo '</table></p>';
		}
	}

	private function personCell($pic, $user_id, $name, $user_flags, $tr_flags, $city)
	{
		ob_start();
		echo '<table class="transp"><tr>';
		echo '<td width="54" align="center" valign="middle">';
		$pic->set($user_id, $name, $tr_flags, 't' . $this->id)->set($user_id, $name, $user_flags);
		$pic->show(ICONS_DIR, true, 50);
		echo '</td>';
		echo '<td valign="middle"><a href="user_info.php?id=' . $user_id . '&bck=1"><b>' . $name . '</b></a><br>' . $city . '</td>';
		echo '</tr></table>';
		return ob_get_clean();
	}

	private function emptyPersonCell()
	{
		return '<table class="transp"><tr><td width="54"><img src="images/transp.png" width="50" height="50" border="0"></td><td></td></tr></table>';
	}

	private function showRegTable($title, $regs, $scheme_count, $default_flags, $person_label, $with_rollback = false)
	{
		$pic = new Picture(USER_TOURNAMENT_PICTURE, $this->user_pic);
		$reg_count = count($regs);
		$row_count = max($scheme_count, $reg_count);
		$btn_width = $with_rollback ? 120 : 90;

		echo '<p><b>' . $title . '</b></p>';
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="' . $btn_width . '" align="center"><button class="icon" onclick="dlg.form(\'form/registration_create.php?tournament_id=' . $this->id . '&flags=' . $default_flags . '\', refr, 400)" title="' . get_label('Add registration to [0].', $this->name) . '"><img src="images/create.png" border="0"></button></td>';
		echo '<td width="40"></td>';
		echo '<td>' . $person_label . '</td>';
		echo '<td width="200">' . get_label('Email') . '</td>';
		echo '<td width="40"></td>';
		echo '</tr>';

		for ($i = 0; $i < $row_count; ++$i)
		{
			$overflow = ($scheme_count > 0 && $i >= $scheme_count);
			echo '<tr class="' . ($overflow ? 'darker' : 'light') . '">';
			if ($i < $reg_count)
			{
				list($user_id, $name, $email, $user_flags, $tr_flags, $city) = $regs[$i];
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
				echo '<td>' . $this->personCell($pic, $user_id, $name, $user_flags, $tr_flags, $city) . '</td>';
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
			}
			echo '</tr>';
		}

		echo '</table></p>';
	}

	private function nextButton()
	{
		if ($this->tab < $this->tab_count - 1)
		{
			$disabled = (!$this->regs_complete && $this->tab == PREP_TAB_REGISTRATIONS) ? ' disabled' : '';
			echo '<button id="prep-next-btn" onclick="nextStep()"' . $disabled . '>' . get_label('Next') . ' &nbsp;<img src="images/next.png" border="0" style="vertical-align:middle"></button>';
		}
	}

	private function showSeatingTab($round_index)
	{
		global $_lang;

		list($event_id, $round_num, $misc_str) = $this->rounds[$round_index];
		$misc = json_decode($misc_str);

		echo '<p><table width="100%"><tr><td>';
		if (empty($misc->seating))
		{
			echo '<button onclick="makeSeating(' . $event_id . ')"><img src="images/create.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Make seating') . '</button>';
		}
		else
		{
			echo '<button onclick="swapPlayers(' . $event_id . ')"><img src="images/user_change.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Swap players') . '</button>';
			echo ' <button onclick="clearSeating(' . $event_id . ', \'' . addslashes(get_label('Are you sure you want to delete the seating?')) . '\')"><img src="images/delete.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Clear seating') . '</button>';
		}
		echo '</td><td align="right">';
		$this->nextButton();
		echo '</td></tr></table></p>';

		if (empty($misc->seating))
		{
			return;
		}

		$seating = $misc->seating;
		$tables_data = is_object($seating) && isset($seating->tables) ? $seating->tables : $seating;
		$raw_mapping = is_object($seating) && isset($seating->mapping) ? $seating->mapping : null;

		// Build index → user_id lookup from mapping
		$index_to_uid = array();
		if (!is_null($raw_mapping))
		{
			foreach ($raw_mapping as $idx => $entry)
			{
				if (is_numeric($entry))
					$index_to_uid[$idx] = (int)$entry;
				elseif (is_object($entry) && isset($entry->id))
					$index_to_uid[$idx] = (int)$entry->id;
				else
					$index_to_uid[$idx] = 0;
			}
		}

		// Collect real user IDs for player info lookup
		$user_ids = array();
		foreach ($tables_data as $table)
			foreach ($table as $game)
				foreach ($game as $raw)
				{
					$uid = is_null($raw_mapping) ? (int)$raw : (isset($index_to_uid[(int)$raw]) ? $index_to_uid[(int)$raw] : 0);
					if ($uid > 0) $user_ids[$uid] = true;
				}

		$users = array();
		if (!empty($user_ids))
		{
			$ids_sql = implode(',', array_keys($user_ids));
			$q = new DbQuery(
				'SELECT u.id, nu.name, tr.flags, u.flags FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ?) <> 0' .
				' LEFT JOIN tournament_regs tr ON tr.user_id = u.id AND tr.tournament_id = ?' .
				' WHERE u.id IN (' . $ids_sql . ')',
				$_lang, $this->id
			);
			while ($row = $q->next())
			{
				$u = new stdClass();
				$u->name = $row[1];
				$u->tr_flags = (int)$row[2];
				$u->flags = (int)$row[3];
				$users[(int)$row[0]] = $u;
			}
		}

		$pic = new Picture(USER_TOURNAMENT_PICTURE, $this->user_pic);

		foreach ($tables_data as $t_idx => $table)
		{
			echo '<p><center><h2>' . get_label('Table [0]', $t_idx + 1) . '</h2></center></p>';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="dark"><td width="8%"></td>';
			for ($k = 0; $k < 10; ++$k)
				echo '<td width="9.2%" align="center"><b>' . ($k + 1) . '</b></td>';
			echo '</tr>';
			foreach ($table as $g_idx => $game)
			{
				if (is_null($game) || count((array)$game) < 10) continue;
				echo '<tr><td align="center" class="dark"><b>' . get_label('Game [0]', $g_idx + 1) . '</b></td>';
				for ($k = 0; $k < 10; ++$k)
				{
					$raw = (int)$game[$k];
					$uid = is_null($raw_mapping) ? $raw : (isset($index_to_uid[$raw]) ? $index_to_uid[$raw] : 0);
					echo '<td align="center">';
					if ($uid > 0 && isset($users[$uid]))
					{
						$u = $users[$uid];
						echo '<table class="transp" width="100%">';
						echo '<tr><td align="center">';
						$pic->set($uid, $u->name, $u->tr_flags, 't' . $this->id)->set($uid, $u->name, $u->flags);
						$pic->show(ICONS_DIR, false, 48);
						echo '</td></tr>';
						echo '<tr><td align="center"><a href="user_info.php?id=' . $uid . '&bck=1">' . $u->name . '</a></td></tr>';
						echo '</table>';
					}
					else
					{
						echo '#' . ($raw + 1);
					}
					echo '</td>';
				}
				echo '</tr>';
			}
			echo '</table>';
		}
	}

	private function showSchemeTab()
	{
		if (empty($this->rounds))
		{
			echo '<p>' . get_label('This tournament has no rounds. Select a tournament type to create rounds automatically.') . '</p>';
		}

		echo '<p><table width="100%"><tr><td>';
		show_tournament_type_select($this->tournament_type, 'tournament-type', 'changeTournamentType()');
		echo '</td><td align="right">';
		$this->nextButton();
		echo '</td></tr></table></p>';

		if (empty($this->rounds))
		{
			return;
		}

		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td>' . get_label('Round') . '</td>';
		echo '<td>' . get_label('Players') . '</td>';
		echo '<td>' . get_label('Tables') . '</td>';
		echo '<td>' . get_label('Games per player') . '</td>';
		echo '</tr>';

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
			echo '</tr>';
		}

		echo '</table></p>';
		echo '<p align="right"><button id="prep-apply-btn" onclick="applyScheme()"' . (!$this->scheme_valid ? ' disabled' : '') . '>' . get_label('Apply') . '</button></p>';
	}

	private function showPairs()
	{
		global $_lang;

		echo '<p align="right">';
		$this->nextButton();
		echo '</p>';

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

		echo '<div class="tab">';
		echo '<button' . ($this->tab == PREP_TAB_SCHEME ? ' class="active"' : '') . ' onclick="goToTab(' . PREP_TAB_SCHEME . ')">' . get_label('Scheme') . '</button>';
		$reg_disabled = !$this->scheme_valid ? ' disabled' : '';
		echo '<button id="prep-tab-registrations"' . ($this->tab == PREP_TAB_REGISTRATIONS ? ' class="active"' : '') . $reg_disabled . ' onclick="goToTab(' . PREP_TAB_REGISTRATIONS . ')">' . get_label('Registrations') . '</button>';
		$locked = (!$this->scheme_valid || !$this->regs_complete) ? ' disabled' : '';
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
			case PREP_TAB_REGISTRATIONS:
				$this->showRegistrationsTab();
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
?>
		function makeSeating(eventId)
		{
			json.post('api/ops/event.php',
			{
				op: 'set_seating',
				event_id: eventId,
			}, refr);
		}

		function clearSeating(eventId, confirmMessage)
		{
			dlg.yesNo(confirmMessage, null, null, function()
			{
				json.post('api/ops/event.php',
				{
					op: 'clear_seating',
					event_id: eventId,
				}, refr);
			});
		}

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

		function changeTournamentType()
		{
			json.post("api/ops/tournament.php",
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				type: $('#tournament-type').val(),
			}, refr);
		}

		function swapPlayers(eventId)
		{
			dlg.form("form/seating_swap.php?event_id=" + eventId, refr, 400);
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
	}
}

$page = new Page();
$page->run(get_label('Preparation'));

?>
