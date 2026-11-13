<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/tournament_schema.php';

initiate_session();


// A tournament schema of a league - see include/tournament_schema.php. schema_id edits one;
// league_id makes a new one.
try
{
	if (isset($_REQUEST['schema_id']))
	{
		$schema_id = (int)$_REQUEST['schema_id'];
		$row = Db::record(get_label('tournament schema'), 'SELECT league_id, name, ' . TOURNAMENT_SCHEMA_COLUMNS . ' FROM league_tournament_schemas WHERE id = ?', $schema_id);
		$league_id = (int)$row[0];
		$name = $row[1];
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		dialog_title(get_label('Edit [0]', $name));
		$schema = tournament_schema_from_row($row[2], $row[3], $row[4], $row[5], $row[6]);
	}
	else
	{
		if (!isset($_REQUEST['league_id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('league')));
		}
		$schema_id = 0;
		$league_id = (int)$_REQUEST['league_id'];
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		dialog_title(get_label('New tournament schema'));
		$name = '';
		// A new schema starts with what a tournament of the league usually is: one round, the
		// rules of the league, its scoring, no normalizer, the usual awards and nothing hidden.
		list($league_scoring_id) = Db::record(get_label('league'), 'SELECT scoring_id FROM leagues WHERE id = ?', $league_id);
		$schema = new stdClass();
		$schema->rounds = array((object)array('round' => 0, 'games' => 10));
		$schema->rules_league_id = $league_id;
		$schema->scoring_id = (int)$league_scoring_id;
		$schema->normalizer_id = 0;
		$schema->awards = TOURNAMENT_FLAG_AWARD_MVP | TOURNAMENT_FLAG_AWARD_RED | TOURNAMENT_FLAG_AWARD_BLACK;
		$schema = normalize_tournament_schema($schema);
	}

	list($league_name) = Db::record(get_label('league'), 'SELECT name FROM leagues WHERE id = ?', $league_id);

	// The rules a schema of the league refers to: the rules of the league, its default and its
	// named ones, or none - the rules of the club as they are. "l<league id>" is the default rules
	// of a league, "r<rules id>" named rules, "0" none. A schema copied from another league keeps
	// referring to the rules of that one, and they are offered too.
	$rules_choices = array();
	$rules_choices['l' . $league_id] = $league_name;
	$query = new DbQuery('SELECT id, name FROM rules WHERE league_id = ? ORDER BY name', $league_id);
	while ($row = $query->next())
	{
		$rules_choices['r' . (int)$row[0]] = $league_name . ': ' . $row[1];
	}
	$rules_choices['0'] = get_label('The rules of the club as they are');
	if ($schema->rules_id > 0)
	{
		$selected_rules = 'r' . $schema->rules_id;
		if (!isset($rules_choices[$selected_rules]))
		{
			$query = new DbQuery('SELECT l.name, r.name FROM rules r JOIN leagues l ON l.id = r.league_id WHERE r.id = ?', $schema->rules_id);
			if ($row = $query->next())
			{
				$rules_choices[$selected_rules] = $row[0] . ': ' . $row[1];
			}
		}
	}
	else if ($schema->rules_league_id > 0)
	{
		$selected_rules = 'l' . $schema->rules_league_id;
		if (!isset($rules_choices[$selected_rules]))
		{
			$query = new DbQuery('SELECT name FROM leagues WHERE id = ?', $schema->rules_league_id);
			if ($row = $query->next())
			{
				$rules_choices[$selected_rules] = $row[0];
			}
		}
	}
	else
	{
		$selected_rules = '0';
	}

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament schema name') . ':</td><td><input class="longest" id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"></td></tr>';

	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" class="toggle" id="form-long-term"' . ($schema->long_term ? ' checked' : '') . ' onchange="longTermChanged()"> ' . get_label('long term tournament. Like a seasonal club championship.');
	echo '<span id="form-single-game-span">&emsp;&emsp;<input type="checkbox" class="toggle" id="form-single-game"' . ($schema->single_games ? ' checked' : '') . '> ' . get_label('single games from non-tournament events can be assigned to the tournament.') . '</span>';
	echo '</td></tr>';

	echo '<tr id="form-rounds-row"><td valign="top">' . get_label('Rounds') . ':</td><td>';
	echo '<table class="bordered light" width="100%">';
	echo '<tr class="th darker">';
	echo '<td width="32" align="center"><button class="icon" onclick="addRound()" title="' . get_label('Add round') . '"><img src="images/create.png" border="0"></button></td>';
	echo '<td>' . get_label('Round') . '</td>';
	echo '<td>' . get_label('Players') . '</td>';
	echo '<td>' . get_label('Tables') . '</td>';
	echo '<td>' . get_label('Games per player') . '</td>';
	echo '<td>' . get_label('Points weight') . '</td>';
	echo '<td title="' . get_label('Tournament group') . '">' . get_label('Group') . '</td>';
	echo '</tr>';
	echo '<tbody id="form-rounds"></tbody>';
	echo '</table>';
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Rules') . ':</td><td><select id="form-rules">';
	foreach ($rules_choices as $value => $text)
	{
		show_option((string)$value, $selected_rules, $text);
	}
	echo '</select></td></tr>';

	echo '<tr><td valign="top">' . get_label('Scoring system') . ':</td><td>';
	show_scoring_select(NULL, $schema->scoring_id, 0, $schema->normalizer_id, 0, $schema->scoring_options, '<div style="height:6px"></div>', 'null',
		SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_VERSION | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION, 'form-scoring', $league_id);
	echo '</td></tr>';

	$awards = array(
		array(TOURNAMENT_FLAG_AWARD_MVP, get_label('MVP'), 0, ''),
		array(TOURNAMENT_FLAG_AWARD_RED, get_label('best red player'), TOURNAMENT_FLAG_AWARD_NO_SHERIFF_IN_RED, get_label('exclude the sheriff games')),
		array(TOURNAMENT_FLAG_AWARD_BLACK, get_label('best black player'), TOURNAMENT_FLAG_AWARD_NO_DON_IN_BLACK, get_label('exclude the don games')),
		array(TOURNAMENT_FLAG_AWARD_SHERIFF, get_label('best sheriff'), 0, ''),
		array(TOURNAMENT_FLAG_AWARD_DON, get_label('best don'), 0, ''));
	echo '<tr><td valign="top">' . get_label('Special awards') . ':</td><td><table class="transp">';
	foreach ($awards as $award)
	{
		list ($flag, $text, $sub_flag, $sub_text) = $award;
		echo '<tr><td style="padding: 3px 24px 3px 0;" nowrap><input type="checkbox" class="toggle award" data-flag="' . $flag . '"' . (($schema->awards & $flag) ? ' checked' : '') .
			' onchange="awardChanged(this, ' . $sub_flag . ')"> ' . $text . '</td><td style="padding: 3px 0;">';
		if ($sub_flag != 0)
		{
			echo '<span id="form-award-sub-' . $sub_flag . '"' . (($schema->awards & $flag) ? '' : ' style="display:none"') . '>' .
				'<input type="checkbox" class="toggle award" data-flag="' . $sub_flag . '"' . (($schema->awards & $sub_flag) ? ' checked' : '') . '> ' . $sub_text . '</span>';
		}
		echo '</td></tr>';
	}
	echo '</table></td></tr>';

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
	echo '<tr><td>' . get_label('Scoring table') . ':</td><td><select id="form-hide-table" onchange="showHidingTours()">';
	foreach ($table_options as $mode => $text)
	{
		show_option($mode, $schema->hide_table, $text);
	}
	echo '</select> <span id="form-hide-table-tour-span"></span></td></tr>';
	echo '<tr><td>' . get_label('Bonus points') . ':</td><td><select id="form-hide-bonus" onchange="showHidingTours()">';
	foreach ($bonus_options as $mode => $text)
	{
		show_option($mode, $schema->hide_bonus, $text);
	}
	echo '</select> <span id="form-hide-bonus-tour-span"></span></td></tr>';
	echo '</table>';

	$mode_rounds = array();
	foreach (array(TOURNAMENT_HIDE_FROM_MAIN_ROUND, TOURNAMENT_HIDE_FROM_QUARTER_FINALS, TOURNAMENT_HIDE_FROM_SEMI_FINALS, TOURNAMENT_HIDE_FROM_FINALS) as $mode)
	{
		$mode_rounds[$mode] = hiding_mode_round($mode);
	}
	$round_names = array(0 => get_round_name(0));
	foreach (get_round_stage_names() as $stage => $round_name)
	{
		$round_names[$stage] = $round_name;
	}
?>
	<script>
	var rounds = <?php echo json_encode($schema->rounds); ?>;
	var roundNames = <?php echo json_encode((object)$round_names); ?>;
	var groupNames = <?php echo json_encode((object)tournament_group_names()); ?>;

	var hidingModeRounds = <?php echo json_encode((object)$mode_rounds); ?>;
	var hidingTour = { 'form-hide-table': <?php echo (int)$schema->hide_table_tour; ?>, 'form-hide-bonus': <?php echo (int)$schema->hide_bonus_tour; ?> };

	// The normalizer is taken at its current version, like the scoring system: there is no version to pick.
	$('#form-scoring-norm-version').hide();

	function longTermChanged()
	{
		var longTerm = $('#form-long-term').prop('checked');
		$('#form-single-game-span').toggle(longTerm);
		$('#form-rounds-row').toggle(!longTerm);
	}
	longTermChanged();

	function awardChanged(box, subFlag)
	{
		if (subFlag)
		{
			$('#form-award-sub-' + subFlag).toggle(box.checked);
		}
	}

	// The order the rounds are played in: the main round first, then the playoff down to the final.
	function roundOrder(a, b)
	{
		if (a.round == b.round) return 0;
		if (a.round == 0) return -1;
		if (b.round == 0) return 1;
		return b.round - a.round;
	}

	function roundCount(value)
	{
		var v = parseInt(value);
		return (isNaN(v) || v <= 0) ? null : v;
	}

	// What is typed into the rows, back into the rounds.
	function readRounds()
	{
		$('#form-rounds tr').each(function(i)
		{
			var row = $(this);
			rounds[i].players = roundCount(row.find('[data-field="players"]').val());
			rounds[i].tables = roundCount(row.find('[data-field="tables"]').val());
			rounds[i].games = roundCount(row.find('[data-field="games"]').val());
			var w = parseFloat(row.find('[data-field="weight"]').val());
			rounds[i].weight = (isNaN(w) || w <= 0) ? 1 : w;
			rounds[i].group = row.find('[data-field="group"]').val();
		});
	}

	function showRounds()
	{
		rounds.sort(roundOrder);
		var html = '';
		for (var i = 0; i < rounds.length; ++i)
		{
			var r = rounds[i];
			var inp = ' type="number" style="width:60px;"';
			html += '<tr><td align="center"><button class="icon" onclick="deleteRound(' + i + ')" title="<?php echo get_label('Delete round'); ?>"><img src="images/delete.png" border="0"></button></td>';
			html += '<td><b>' + (r.round in roundNames ? roundNames[r.round] : r.round) + '</b></td>';
			html += '<td><input' + inp + ' min="10" data-field="players" value="' + (r.players ? r.players : '') + '"></td>';
			html += '<td><input' + inp + ' min="1" data-field="tables" value="' + (r.tables ? r.tables : '') + '"></td>';
			html += '<td><input' + inp + ' min="1" data-field="games" value="' + (r.games ? r.games : '') + '"></td>';
			html += '<td><input' + inp + ' step="0.1" min="0.1" data-field="weight" value="' + (r.weight ? r.weight : 1) + '"></td>';
			html += '<td><select data-field="group">';
			for (var g in groupNames)
			{
				html += '<option value="' + g + '"' + (r.group == g ? ' selected' : '') + '>' + groupNames[g] + '</option>';
			}
			html += '</select></td></tr>';
		}
		$('#form-rounds').html(html);
	}
	showRounds();

	// The main round when there is none, otherwise a rung of the playoff before the ones there are.
	function addRound()
	{
		readRounds();
		var hasMain = false;
		var playoff = 0;
		for (var r of rounds)
		{
			if (r.round == 0) hasMain = true; else playoff = Math.max(playoff, r.round);
		}
		var round = hasMain ? playoff + 1 : 0;
		if (!(round in roundNames))
		{
			return;
		}
		rounds.push({ round: round, players: round == 1 ? 10 : null, tables: round == 1 ? 1 : null, games: null, weight: 1, group: round == 1 ? 'final' : 'main' });
		showRounds();
	}

	// The playoff keeps going down to the final: the rungs before a deleted one move one step nearer the end.
	function deleteRound(index)
	{
		readRounds();
		var deleted = rounds[index].round;
		rounds.splice(index, 1);
		if (deleted > 0)
		{
			for (var r of rounds)
			{
				if (r.round > deleted) --r.round;
			}
		}
		showRounds();
	}

	function hidingTourSelect(id)
	{
		var mode = $('#' + id).val();
		if (!(mode in hidingModeRounds))
		{
			return '';
		}
		var round = hidingModeRounds[mode];
		var tour = hidingTour[id];
		var html = '<select id="' + id + '-tour" onchange="hidingTour[\'' + id + '\'] = parseInt(this.value)">';
		if (round != 0)
		{
			html += '<option value="0"' + (tour <= 1 ? ' selected' : '') + '><?php echo get_label('from the start of the round'); ?></option>';
		}
		for (var i = 2; i <= Math.max(20, tour); ++i)
		{
			html += '<option value="' + i + '"' + (tour == i || (round == 0 && i == 2 && tour <= 1) ? ' selected' : '') + '>' + '<?php echo get_label('starting from tour [0]', '#'); ?>'.replace('#', i) + '</option>';
		}
		return html + '</select>';
	}

	function showHidingTours()
	{
		$('#form-hide-table-tour-span').html(hidingTourSelect('form-hide-table'));
		$('#form-hide-bonus-tour-span').html(hidingTourSelect('form-hide-bonus'));
	}
	showHidingTours();

	function hidingTourValue(id)
	{
		var t = $('#' + id + '-tour');
		return t.length > 0 ? parseInt(t.val()) : 0;
	}

	// The id the rules select refers to, when it is of this kind: 'l' - a league, 'r' - named rules.
	function rulesRef(kind)
	{
		var v = $('#form-rules').val();
		return v.charAt(0) == kind ? parseInt(v.substr(1)) : 0;
	}

	function commit(onSuccess)
	{
		readRounds();
		var awards = 0;
		$('input.award').each(function()
		{
			if ($(this).prop('checked')) awards |= parseInt($(this).data('flag'));
		});
		var flags = 0;
		if (!$('#form-scoring-night1').prop('checked')) flags |= <?php echo SCORING_OPTION_NO_NIGHT_KILLS; ?>;
		if (!$('#form-scoring-difficulty').prop('checked')) flags |= <?php echo SCORING_OPTION_NO_GAME_DIFFICULTY; ?>;
		var longTerm = $('#form-long-term').prop('checked');
		var schema =
		{
			long_term: longTerm,
			single_games: longTerm && $('#form-single-game').prop('checked'),
			rounds: longTerm ? [] : rounds,
			rules_id: rulesRef('r'),
			rules_league_id: rulesRef('l'),
			scoring_id: parseInt($('#form-scoring-sel').val()),
			scoring_options: { flags: flags },
			normalizer_id: parseInt($('#form-scoring-norm-sel').val()),
			awards: awards,
			hide_table: parseInt($('#form-hide-table').val()),
			hide_table_tour: hidingTourValue('form-hide-table'),
			hide_bonus: parseInt($('#form-hide-bonus').val()),
			hide_bonus_tour: hidingTourValue('form-hide-bonus'),
		};
		var params = { name: $('#form-name').val(), schema: JSON.stringify(schema) };
<?php if ($schema_id > 0): ?>
		params.op = 'change';
		params.schema_id = <?php echo $schema_id; ?>;
<?php else: ?>
		params.op = 'create';
		params.league_id = <?php echo $league_id; ?>;
<?php endif; ?>
		json.post("api/ops/tournament_schema.php", params, onSuccess);
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
