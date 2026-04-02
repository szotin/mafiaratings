<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/seating.php';

initiate_session();

define('DEFAULT_POLICY', PAIR_POLICY_SEPARATE);

try
{
	dialog_title(get_label('Create [0]', get_label('pair')));

	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	list ($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id);

	echo '<table class="dialog_form" width="100%">';

	echo '<tr><td width="120">' . get_label('Player [0]', 1) . ':</td><td>';
	show_user_input('form-player1', '', 'tournament=' . $tournament_id, get_label('Select player.'), 'onSelectPlayer1');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Player [0]', 2) . ':</td><td>';
	show_user_input('form-player2', '', 'tournament=' . $tournament_id, get_label('Select player.'), 'onSelectPlayer2');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Policy') . ':</td><td><select id="form-policy">';
	show_option(PAIR_POLICY_SEPARATE,  DEFAULT_POLICY, get_label('Separate players.'));
	show_option(PAIR_POLICY_AVOID,     DEFAULT_POLICY, get_label('Reduce number of games together but do not separate completely.'));
	show_option(PAIR_POLICY_BALANCED,  DEFAULT_POLICY, get_label('As usual. No separation.'));
	show_option(PAIR_POLICY_WELCOME,   DEFAULT_POLICY, get_label('Increase number of games together.'));
	echo '</select></td></tr>';

	echo '<tr><td colspan="2"><input id="form-tournament-only" type="checkbox"> ' . get_label('for this tournament only') . '</td></tr>';

	echo '</table>';

?>
	<script>
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
				, tournament_id: <?php echo $tournament_id; ?>
				, user1_id: player1Id
				, user2_id: player2Id
				, policy: $("#form-policy").val()
				, global: $("#form-tournament-only").is(":checked") ? 0 : 1
			},
			onSuccess);
		}
	}
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
