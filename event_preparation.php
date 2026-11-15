<?php

require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/seating.php';
require_once 'include/preparation.php';
require_once 'include/event_mailing.php';
require_once 'include/scoring.php';
require_once 'include/rules.php';

// An event has one round, so it has one seating, where a tournament has one per round and its
// pairs in between - the two pages do not line up and each numbers its own tabs.
define('EPREP_TAB_MAILING', 0);
define('EPREP_TAB_SCHEME', 1);
define('EPREP_TAB_RULES', 2);
define('EPREP_TAB_REGISTRATIONS', 3);
define('EPREP_TAB_STREAMS', 4);
define('EPREP_TAB_SEATING', 5);

// What a mailing was sent about, for the list of them.
function event_mailing_type_name($type)
{
	switch ($type)
	{
		case EVENT_EMAIL_INVITE:
			return get_label('invitation');
		case EVENT_EMAIL_CANCEL:
			return get_label('cancelling');
		case EVENT_EMAIL_CHANGE_ADDRESS:
			return get_label('change address');
		case EVENT_EMAIL_CHANGE_TIME:
			return get_label('change time');
		case EVENT_EMAIL_RESTORE:
			return get_label('restore');
	}
	return '';
}

// Getting one event ready to be played: how many sit down, who is coming, where it is being
// streamed from and who sits where.
//
// A round of a tournament is an event like any other, and this page is the same page for it.
// What it shows then is not a copy of what the tournament's own preparation shows for that
// round - it is the same rows, written through the same operations - so the two cannot drift
// apart. The one thing that is each level's own is the registrations: a tournament is entered
// once for all of its rounds, while an event, round or not, records who turned up to it.
class Page extends EventPageBase
{
	use PreparationTabs;

	private $tab;
	private $tab_count;
	private $rounds;
	private $prep_tournament_id;
	private $scheme_valid;
	private $regs_complete;
	private $team_size;
	private $can_edit;
	private $can_mail;

	protected function prepare()
	{
		parent::prepare();

		$this->prep_tournament_id = is_null($this->tournament_id) ? 0 : (int)$this->tournament_id;
		$this->tab_count = EPREP_TAB_SEATING + 1;

		// The shared tabs walk a list of rounds; this page has the one.
		$row = Db::record(get_label('event'),
			'SELECT id, round, misc, players, tables, games FROM events WHERE id = ?', $this->id);
		$this->rounds = array($row);

		list ($players, $tables, $games) = array($row[3], $row[4], $row[5]);
		$this->scheme_valid = !is_null($players) && !is_null($tables) && !is_null($games) &&
			(int)$players >= 10 && (int)$tables >= 1 && (int)$games >= 1 &&
			(int)$tables <= (int)floor((int)$players / 10);

		// Teams belong to a tournament: an event standing on its own is seated as individuals
		// whatever anybody says, so there is nothing here to set. See api/ops/event.php, where
		// the seating reads the size off the tournament or takes it as one.
		$this->team_size = 1;
		if ($this->prep_tournament_id > 0)
		{
			list ($this->team_size) = Db::record(get_label('tournament'),
				'SELECT team_size FROM tournaments WHERE id = ?', $this->prep_tournament_id);
			$this->team_size = max(1, (int)$this->team_size);
		}

		// Nothing here is held back waiting for registrations the way a tournament holds back
		// its pairs - an event is one round, and its scheme, its streams and its seating are
		// set up in whatever order suits.
		$this->regs_complete = true;

		// The mailing is the manager's alone, and it is the first tab, so a referee opening
		// the page lands on the one after it rather than on nothing.
		$this->can_mail = $this->is_manager;
		$first = $this->can_mail ? EPREP_TAB_MAILING : EPREP_TAB_SCHEME;

		$this->tab = isset($_REQUEST['tab']) ? (int)$_REQUEST['tab'] : $first;
		if ($this->tab < $first || $this->tab >= $this->tab_count)
		{
			$this->tab = $first;
		}
	}

	// How many sit down at this event, at how many tables, and how many games each of them
	// plays. For a round of a tournament these are the very numbers its tournament's own
	// preparation shows against that round, and they are saved the same way.
	private function showSchemeTab()
	{
		list ($event_id, $round_num, $misc, $players, $tables, $games) = $this->rounds[0];

		$this->navBar();

		$players_val = is_null($players) ? '' : $players;
		$tables_val = is_null($tables) ? '' : $tables;
		$games_val = is_null($games) ? '' : $games;
		$players_int = ($players_val !== '') ? (int)$players_val : 0;
		$max_tables = ($players_int >= 10) ? (int)floor($players_int / 10) : 0;
		$inp = ' type="number" style="width:60px;" oninput="schemeInputChanged()"';

		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td>' . get_label('Players') . '</td>';
		echo '<td>' . get_label('Tables') . '</td>';
		echo '<td>' . get_label('Games per player') . '</td>';
		if ($this->prep_tournament_id > 0)
		{
			echo '<td>' . get_label('Team size') . '</td>';
		}
		echo '</tr><tr>';
		echo '<td><input' . $inp . ' min="10" id="scheme-players" value="' . $players_val . '"></td>';
		echo '<td><input' . $inp . ' min="1"' . ($max_tables > 0 ? ' max="' . $max_tables . '"' : '') .
			' id="scheme-tables" value="' . $tables_val . '"></td>';
		echo '<td><input' . $inp . ' min="1" id="scheme-games" value="' . $games_val . '"></td>';
		if ($this->prep_tournament_id > 0)
		{
			// Shared with every other round of the tournament, and changing it here changes it
			// for all of them - which is what it means for the two pages to agree.
			echo '<td><input type="number" style="width:60px;" min="1" id="scheme-team-size" value="' . $this->team_size . '"></td>';
		}
		echo '</tr></table></p>';

		echo '<p align="right"><button id="prep-apply-btn" onclick="applyScheme()"' .
			(!$this->scheme_valid ? ' disabled' : '') . '>' . get_label('Apply') . '</button></p>';
	}

	// The rules the games are played by, the scoring, and the special awards. Each is saved the
	// moment it is changed, the same way the tournament's own Rules tab saves them.
	//
	// A round of a tournament plays by the tournament's rules and is scored by its scoring - that
	// is what makes it a round of it rather than an event of its own - so those two are shown as
	// they are and cannot be changed here. They are changed on the tournament's preparation page,
	// which hands them down to every round. The awards are the round's own, the way they are its
	// own in the form.
	private function showRulesTab()
	{
		list ($flags, $rules_code, $scoring_id, $scoring_version, $scoring_options, $club_name, $club_rules) = Db::record(get_label('event'),
			'SELECT e.flags, e.rules, e.scoring_id, e.scoring_version, e.scoring_options, c.name, c.rules' .
			' FROM events e JOIN clubs c ON c.id = e.club_id WHERE e.id = ?', $this->id);
		$flags = (int)$flags;
		// Changing any of this is a manager's, so a referee reading the page sees it and cannot
		// touch it - the change operation would refuse them anyway.
		$of_tournament = ($this->prep_tournament_id > 0);
		$disabled = ($of_tournament || !$this->can_edit) ? ' disabled' : '';
		$awards_disabled = $this->can_edit ? '' : ' disabled';

		$this->navBar();

		echo '<p><table class="bordered light" width="100%">';
		$rules_code = upgrade_rules_code($rules_code);
		echo '<tr><td width="160">' . get_label('Rules') . ':</td><td><select id="prep-rules"' . $disabled . ' onchange="rulesChanged()">';
		foreach (merge_same_rules(get_available_rules($this->club_id, $club_name, $club_rules)) as $r)
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
		show_scoring_select($this->club_id, $scoring_id, $scoring_version, 0, 0, json_decode($scoring_options), '<div style="height:12px"></div>', 'scoringChanged', SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_NORMALIZER, 'prep-scoring');
		echo '</td></tr>';

		// One award a line, all of them alike. Leaving the sheriff out of the best red, and the
		// don out of the best black, is a part of that award, so it stands on its line and is
		// there only while the award is on.
		$awards = array(
			array(EVENT_FLAG_AWARD_MVP, get_label('MVP'), 0, ''),
			array(EVENT_FLAG_AWARD_RED, get_label('best red player'), EVENT_FLAG_AWARD_NO_SHERIFF_IN_RED, get_label('exclude the sheriff games')),
			array(EVENT_FLAG_AWARD_BLACK, get_label('best black player'), EVENT_FLAG_AWARD_NO_DON_IN_BLACK, get_label('exclude the don games')),
			array(EVENT_FLAG_AWARD_SHERIFF, get_label('best sheriff'), 0, ''),
			array(EVENT_FLAG_AWARD_DON, get_label('best don'), 0, ''));
		echo '<tr><td valign="top">' . get_label('Special awards') . ':</td><td>';
		echo '<table class="transp">';
		foreach ($awards as $award)
		{
			list ($flag, $text, $sub_flag, $sub_text) = $award;
			echo '<tr><td style="padding: 6px 24px 6px 0;" nowrap><input type="checkbox" class="toggle"' . (($flags & $flag) ? ' checked' : '') . $awards_disabled .
				' onchange="awardChanged(this, ' . $flag . ', ' . $sub_flag . ')"> ' . $text . '</td>';
			echo '<td style="padding: 6px 0;">';
			if ($sub_flag != 0)
			{
				echo '<span id="award-sub-' . $sub_flag . '"' . (($flags & $flag) ? '' : ' style="display:none"') . '>' .
					'<input type="checkbox" class="toggle"' . (($flags & $sub_flag) ? ' checked' : '') . $awards_disabled .
					' onchange="setEventFlag(' . $sub_flag . ', this.checked)"> ' . $sub_text . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		echo '</td></tr>';
		echo '</table></p>';
	}

	// Who is coming to this event. A round of a tournament keeps its own list too: the
	// tournament says who may play in it at all, this says who turned up to this round.
	// Putting somebody down here tells the tournament about them - see
	// register_event_user_in_tournament().
	private function showRegistrationsTab()
	{
		global $_profile, $_lang;

		$this->navBar();

		$event_reg_pic =
			new Picture(USER_EVENT_PICTURE,
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="87">';
		if ($this->can_edit)
		{
			echo '<button class="icon" onclick="mr.addEventReg(' . $this->id . ')" title="' . get_label('Add registration to [0].', $this->name) . '"><img src="images/create.png" border="0"></button>';
		}
		echo '</td>';
		echo '<td width="40"></td>';
		echo '<td colspan="4">' . get_label('User') . '</td><td width="130">' . get_label('Permissions') . '</td></tr>';

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email, u.flags, eu.nickname, eu.flags, tu.tournament_id, tu.flags, c.id, c.name, c.flags, cu.club_id, cu.flags, ni.name' .
			' FROM event_regs eu' .
			' JOIN users u ON eu.user_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN cities i ON i.id = u.city_id'.
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
			' JOIN events e ON e.id = eu.event_id' .
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' LEFT OUTER JOIN tournament_regs tu ON tu.tournament_id = e.tournament_id AND tu.user_id = eu.user_id' .
			' LEFT OUTER JOIN club_regs cu ON cu.club_id = e.club_id AND cu.user_id = eu.user_id' .
			' WHERE eu.event_id = ?' .
			' ORDER BY nu.name',
			$this->id);
		$count = 0;
		while ($row = $query->next())
		{
			list($id, $name, $email, $user_flags, $user_nickname, $event_reg_flags, $tournament_id, $tournament_reg_flags, $club_id, $club_name, $club_flags, $user_club_id, $club_reg_flags, $city) = $row;
			++$count;

			echo '<tr class="light"><td class="dark">';
			if ($this->can_edit)
			{
				echo '<button class="icon" onclick="mr.removeEventReg(' . $id . ', ' . $this->id . ', \'' . get_label('Are you sure you want to unregister [0]?', $name) . '\')" title="' . get_label('Unregister [0].', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editEventAccess(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/edit.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.eventUserPhoto(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] photo for [1].', $name, $this->name) . '"><img src="images/photo.png" border="0"></button>';
			}
			else
			{
				echo '<img src="images/transp.png" height="32" border="0">';
			}
			echo '</td>';

			echo '<td align="center"><b>' . $count . '</b></td>';
			echo '<td width="60" align="center">';
			$event_reg_pic->
				set($id, $user_nickname, $event_reg_flags, 'e' . $this->id)->
				set($id, $name, $tournament_reg_flags, 't' . $tournament_id)->
				set($id, $name, $club_reg_flags, 'c' . $user_club_id)->
				set($id, $name, $user_flags);
			$event_reg_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td style="position:relative;">';
			if ($event_reg_flags & USER_FLAG_EXHIBITION_PLAYER)
			{
				echo '<span title="' . get_label('Excluded from event standings.') . '" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#a06060;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;">' . get_label('exhibition player') . '</span>';
			}
			echo '<a href="user_info.php?id=' . $id . '&bck=1"><b>' . $name . '</b><br>' . $city . '</a></td>';
			echo '<td width="200">';
			if ($_profile->is_club_manager($club_id))
			{
				echo $email;
			}
			echo '</td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';

			echo '<td>';
			foreach (array(USER_PERM_PLAYER => 'player.png', USER_PERM_REFEREE => 'referee.png', USER_PERM_MANAGER => 'manager.png') as $perm => $icon)
			{
				if ($event_reg_flags & $perm)
				{
					echo '<img src="images/' . $icon . '" width="32">';
				}
				else
				{
					echo '<img src="images/transp.png" width="32">';
				}
			}
			echo '</td></tr>';
		}
		if ($count == 0)
		{
			echo '<tr><td colspan="7">' . get_label('Nobody is registered yet.') . '</td></tr>';
		}
		echo '</table>';
	}

	// What has been written to the people coming to this event, and what is still to go out.
	// A mailing is scheduled against the start of the event rather than against the calendar,
	// so what is shown is when it goes relative to that.
	private function showMailingTab()
	{
		$this->navBar();

		echo '<table class="bordered light" width="100%">';
		if (isset($_REQUEST['msg']) && $_REQUEST['msg'] == 0)
		{
			echo '<tr><td colspan="6" class="light">' . get_label('You have canceled the event. Please check the existing mailings, probably you want to cancel some of them or send new emails about your action.') . '</td></tr>';
		}

		echo '<tr height="36" class="th darker">';
		echo '<td width="60"><button class="icon" onclick="mr.createEventMailing(' . $this->id . ')" title="' . get_label('New mailing') . '">';
		echo '<img src="images/create.png" border="0"></button></td>';
		echo '<td width="150">' . get_label('Date') . '</td><td>' . get_label('Recipients') . '</td><td width="80">' . get_label('Type') . '</td><td width="80">' . get_label('Status') . '</td><td width="80">' . get_label('Emails sent') . '</td></tr>';

		$query = new DbQuery('SELECT id, send_time, send_count, status, langs, flags, type FROM event_mailings WHERE event_id = ? ORDER BY send_time', $this->id);
		$count = 0;
		while ($row = $query->next())
		{
			list($mail_id, $send_time, $send_count, $status, $langs, $flags, $type) = $row;
			++$count;

			$when = format_date($this->start_time - $send_time, get_timezone(), true);
			$seen = '<a href="view_event_mailing.php?id=' . $this->id . '&mailing_id=' . $mail_id . '&bck=1" title="' . get_label('View') . '">' . $when . '</a>';

			echo '<tr height="36">';
			switch ($status)
			{
				case MAILING_WAITING:
					echo '<td class="dark">';
					echo '<button class="icon" onclick="mr.editEventMailing(' . $mail_id . ')" title="' . get_label('Edit mailing') . '"><img src="images/edit.png" border="0"></button>';
					echo '<button class="icon" onclick="deleteMailing(' . $mail_id . ')" title="' . get_label('Delete mailing') . '"><img src="images/delete.png" border="0"></button>';
					echo '</td>';
					echo '<td>' . $when . '</td>';
					echo '<td>' . get_email_recipients($flags, $langs) . '</td>';
					echo '<td>' . event_mailing_type_name($type) . '</td>';
					echo '<td>' . get_label('waiting') . '</td><td></td>';
					break;

				case MAILING_SENDING:
					echo '<td class="dark"></td><td>' . $seen . '</td>';
					echo '<td>' . get_email_recipients($flags, $langs) . '</td>';
					echo '<td>' . event_mailing_type_name($type) . '</td>';
					echo '<td>' . get_label('sending') . '</td><td>' . $send_count . '</td>';
					break;

				case MAILING_COMPLETE:
					echo '<td class="dark"></td><td>' . $seen . '</td>';
					echo '<td>' . get_email_recipients($flags, $langs) . '</td>';
					echo '<td>' . event_mailing_type_name($type) . '</td>';
					echo '<td>' . get_label('complete') . '</td><td>' . $send_count . '</td>';
					break;
			}
			echo '</tr>';
		}
		if ($count == 0)
		{
			echo '<tr><td colspan="6">' . get_label('Nothing has been sent yet.') . '</td></tr>';
		}
		echo '</table>';
	}

	// The last step leads to the seating, and that tab is barred until the scheme is in. See
	// show_body().
	protected function nextDisabled()
	{
		return !$this->scheme_valid && $this->tab == EPREP_TAB_STREAMS;
	}

	protected function show_body()
	{
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
			$this->club_id, $this->id, $this->tournament_id);
		$this->can_edit = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER,
			$this->club_id, $this->id, $this->tournament_id);

		// This page runs the optimizer too, from the offer made after a seating is generated
		// and from the hash shown next to it, so it needs the texts the dialogs are written in.
		show_seating_optimizer_labels();

		echo '<div class="tab">';
		$tabs = array(
			EPREP_TAB_SCHEME => get_label('Scheme'),
			EPREP_TAB_RULES => get_label('Rules'),
			EPREP_TAB_REGISTRATIONS => get_label('Registrations'),
			EPREP_TAB_STREAMS => get_label('Streams'),
			EPREP_TAB_SEATING => get_label('Seating'));
		if ($this->can_mail)
		{
			$tabs = array(EPREP_TAB_MAILING => get_label('Mailing')) + $tabs;
			ksort($tabs);
		}
		foreach ($tabs as $idx => $name)
		{
			// Nothing can be seated until the scheme says how many play, at how many tables and
			// how many games each - the generator has nothing to work from. The tab is barred
			// rather than left to fail, and comes back the moment the scheme is applied.
			$disabled = ($idx == EPREP_TAB_SEATING && !$this->scheme_valid) ? ' disabled' : '';
			echo '<button' . ($this->tab == $idx ? ' class="active"' : '') . $disabled . ' onclick="goToTab(' . $idx . ')">' . $name . '</button>';
		}
		echo '</div>';

		switch ($this->tab)
		{
			case EPREP_TAB_MAILING:
				$this->showMailingTab();
				break;
			case EPREP_TAB_RULES:
				$this->showRulesTab();
				break;
			case EPREP_TAB_REGISTRATIONS:
				$this->showRegistrationsTab();
				break;
			case EPREP_TAB_STREAMS:
				$this->showStreamsTab();
				break;
			case EPREP_TAB_SEATING:
				if ($this->scheme_valid)
				{
					$this->showSeatingTab(0);
				}
				else
				{
					echo '<p>' . get_label('Fill in the scheme first: how many play, at how many tables, and how many games each of them plays.') . '</p>';
				}
				break;
			default:
				$this->showSchemeTab();
				break;
		}
	}

	protected function js()
	{
		$this->prepScripts();
?>
		function goToTab(tabIdx)
		{
			goTo({tab: tabIdx});
		}

		function deleteMailing(mailingId)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete event mailing?'); ?>", null, null,
				function() { mr.deleteEventMailing(mailingId) });
		}

		function nextStep()
		{
			goToTab(<?php echo $this->tab + 1; ?>);
		}

		// One flag of the event on or off, the others left as they are.
		function setEventFlag(flag, on)
		{
			json.post("api/ops/event.php",
			{
				op: "change",
				event_id: <?php echo $this->id; ?>,
				flags: on ? flag : 0,
				flags_mask: flag,
			});
		}

		// An award, and the part of it that is shown only while it is on.
		function awardChanged(box, flag, subFlag)
		{
			setEventFlag(flag, box.checked);
			if (subFlag)
			{
				$('#award-sub-' + subFlag).toggle(box.checked);
			}
		}

		function rulesChanged()
		{
			json.post("api/ops/event.php",
			{
				op: "change",
				event_id: <?php echo $this->id; ?>,
				rules_code: $('#prep-rules').val(),
			});
		}

		// Called by the scoring select whenever anything in it is changed - the scoring, its
		// version or its options - with all of them.
		function scoringChanged(s)
		{
			json.post("api/ops/event.php",
			{
				op: "change",
				event_id: <?php echo $this->id; ?>,
				scoring_id: s.sId,
				scoring_version: s.sVer,
				scoring_options: JSON.stringify(s.ops),
			});
		}
<?php if ($this->tab == EPREP_TAB_RULES && ($this->prep_tournament_id > 0 || !$this->can_edit)): ?>

		// A round is scored by the scoring of its tournament, so the select is shut the way the
		// event form used to shut it, while the options under it stay open - those are the
		// round's own. A referee changes nothing here at all.
		$(function()
		{
<?php if (!$this->can_edit): ?>
			$('[id^="prep-scoring"]').prop('disabled', true);
<?php else: ?>
			$('#prep-scoring-sel').prop('disabled', true);
			$('#prep-scoring-ver').prop('disabled', true);
<?php endif; ?>
		});
<?php endif; ?>

		// A tournament keeps a scheme for every round at once and has an operation of its own
		// for it; a round of one is saved through that very operation, with the round named,
		// so that what this page writes and what the tournament's page writes are one thing.
		// An event standing on its own has nobody to share with and saves itself.
		function applyScheme()
		{
			var players = parseInt($('#scheme-players').val()) || 0;
			var tables = parseInt($('#scheme-tables').val()) || 0;
			var games = parseInt($('#scheme-games').val()) || 0;
<?php if ($this->prep_tournament_id > 0): ?>
			json.post('api/ops/tournament.php',
			{
				op: 'set_scheme',
				tournament_id: <?php echo $this->prep_tournament_id; ?>,
				rounds: JSON.stringify([{ event_id: <?php echo $this->id; ?>, is_main: true,
					players: players, tables: tables, games: games }]),
				team_size: Math.max(1, parseInt($('#scheme-team-size').val()) || 1),
			}, refr);
<?php else: ?>
			json.post('api/ops/event.php',
			{
				op: 'set_scheme',
				event_id: <?php echo $this->id; ?>,
				players: players,
				tables: tables,
				games: games,
			}, refr);
<?php endif; ?>
		}

		// The same rule the tournament applies: a table seats ten, so there cannot be more
		// tables than that allows, and nothing is saved until the three numbers make sense.
		function schemeInputChanged()
		{
			var players = parseInt($('#scheme-players').val()) || 0;
			var tables = parseInt($('#scheme-tables').val()) || 0;
			var games = parseInt($('#scheme-games').val()) || 0;
			var maxTables = Math.floor(players / 10);
			if (maxTables > 0)
			{
				$('#scheme-tables').attr('max', maxTables);
				if (tables > maxTables)
				{
					$('#scheme-tables').val(maxTables);
					tables = maxTables;
				}
			}
			$('#prep-apply-btn').prop('disabled',
				!(players >= 10 && tables >= 1 && games >= 1 && tables <= maxTables));
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Preparation'));

?>
