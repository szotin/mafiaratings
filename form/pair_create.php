<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/seating.php';

initiate_session();

define('DEFAULT_POLICY', PAIR_POLICY_SEPARATE);

try
{
	dialog_title(get_label('Create [0]', get_label('pair')));

	$tournament_id = isset($_REQUEST['tournament_id']) ? (int)$_REQUEST['tournament_id'] : 0;
	$club_id       = isset($_REQUEST['club_id'])       ? (int)$_REQUEST['club_id']       : 0;
	$league_id     = isset($_REQUEST['league_id'])     ? (int)$_REQUEST['league_id']     : 0;

	if ($tournament_id > 0)
	{
		global $_lang;
		list ($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id);

		$reg_users = array();
		$q = new DbQuery(
			'SELECT tr.user_id, n.name' .
			' FROM tournament_regs tr' .
			' JOIN users u ON u.id = tr.user_id' .
			' JOIN names n ON n.id = u.name_id AND (n.langs & ?) <> 0' .
			' WHERE tr.tournament_id = ? AND (tr.flags & ?) <> 0 AND (tr.flags & ?) = 0' .
			' ORDER BY n.name',
			$_lang, $tournament_id, USER_PERM_MASK, USER_TOURNAMENT_FLAG_NOT_ACCEPTED);
		while ($row = $q->next())
		{
			$u = new stdClass();
			$u->id   = (int)$row[0];
			$u->name = $row[1];
			$reg_users[] = $u;
		}
	}
	else if ($club_id > 0)
	{
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		$player_condition = 'club=' . $club_id;
	}
	else if ($league_id > 0)
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		$player_condition = '';
	}
	else
	{
		check_permissions(PERMISSION_ADMIN);
		$player_condition = '';
	}

	echo '<table class="dialog_form" width="100%">';

	echo '<tr><td width="120">' . get_label('Player [0]', 1) . ':</td><td>';
	if ($tournament_id > 0)
	{
		echo '<select id="form-player1" style="width:100%"><option value="0">' . get_label('Select player.') . '</option>';
		foreach ($reg_users as $u)
			echo '<option value="' . $u->id . '">' . htmlspecialchars($u->name) . '</option>';
		echo '</select>';
	}
	else
	{
		show_user_input('form-player1', '', $player_condition, get_label('Select player.'), 'onSelectPlayer1');
	}
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Player [0]', 2) . ':</td><td>';
	if ($tournament_id > 0)
	{
		echo '<select id="form-player2" style="width:100%"><option value="0">' . get_label('Select player.') . '</option>';
		foreach ($reg_users as $u)
			echo '<option value="' . $u->id . '">' . htmlspecialchars($u->name) . '</option>';
		echo '</select>';
	}
	else
	{
		show_user_input('form-player2', '', $player_condition, get_label('Select player.'), 'onSelectPlayer2');
	}
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Policy') . ':</td><td><select id="form-policy">';
	show_option(PAIR_POLICY_SEPARATE,  DEFAULT_POLICY, get_label('Separate players.'));
	show_option(PAIR_POLICY_AVOID,     DEFAULT_POLICY, get_label('Reduce number of games together but do not separate completely.'));
	show_option(PAIR_POLICY_WELCOME,   DEFAULT_POLICY, get_label('Increase number of games together.'));
	echo '</select></td></tr>';

	if ($tournament_id > 0)
		echo '<tr><td colspan="2"><input id="form-tournament-only" type="checkbox"> ' . get_label('for this tournament only') . '</td></tr>';
	else if ($league_id > 0)
		echo '<tr><td colspan="2"><input id="form-tournament-only" type="checkbox"> ' . get_label('for this league only') . '</td></tr>';
	else if ($club_id > 0)
		echo '<tr><td colspan="2"><input id="form-tournament-only" type="checkbox"> ' . get_label('for this club only') . '</td></tr>';

	echo '</table>';

?>
	<script>
	<?php if ($tournament_id > 0): ?>
	function commit(onSuccess)
	{
		var player1Id = parseInt($("#form-player1").val()) || 0;
		var player2Id = parseInt($("#form-player2").val()) || 0;
		if (player1Id <= 0)
		{
			dlg.error("<?php echo get_label('Please enter player [0].', 1); ?>");
		}
		else if (player2Id <= 0)
		{
			dlg.error("<?php echo get_label('Please enter player [0].', 2); ?>");
		}
		else if (player1Id == player2Id)
		{
			dlg.error("<?php echo get_label('Players must be different.'); ?>");
		}
		else
		{
			json.post("api/ops/seating.php",
			{
				op: "add_pair"
				, tournament_id: <?php echo $tournament_id; ?>
				, user1_id: player1Id
				, user2_id: player2Id
				, policy: $("#form-policy").val()
				, global: $("#form-tournament-only").is(":checked") ? 0 : 1
			},
			onSuccess);
		}
	}
	<?php else: ?>
	var player1Id = 0;
	var player2Id = 0;

	function onSelectPlayer1(_user)
	{
		if (typeof _user.id == "number")
		{
			player1Id = _user.id;
		}
		else
		{
			player1Id = 0;
		}
	}

	function onSelectPlayer2(_user)
	{
		if (typeof _user.id == "number")
		{
			player2Id = _user.id;
		}
		else
		{
			player2Id = 0;
		}
	}

	function commit(onSuccess)
	{
		if (player1Id <= 0)
		{
			dlg.error("<?php echo get_label('Please enter player [0].', 1); ?>");
		}
		else if (player2Id <= 0)
		{
			dlg.error("<?php echo get_label('Please enter player [0].', 2); ?>");
		}
		else if (player1Id == player2Id)
		{
			dlg.error("<?php echo get_label('Players must be different.'); ?>");
		}
		else
		{
			json.post("api/ops/seating.php",
			{
				op: "add_pair"
				<?php if ($club_id > 0): ?>, club_id: <?php echo $club_id; ?><?php endif; ?>
				<?php if ($league_id > 0): ?>, league_id: <?php echo $league_id; ?><?php endif; ?>
				, user1_id: player1Id
				, user2_id: player2Id
				, policy: $("#form-policy").val()
				<?php if ($club_id > 0 || $league_id > 0): ?>
				, global: $("#form-tournament-only").is(":checked") ? 0 : 1
				<?php else: ?>
				, global: 1
				<?php endif; ?>
			},
			onSuccess);
		}
	}
	<?php endif; ?>
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>
