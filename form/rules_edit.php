<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/rules.php';

initiate_session();

function show_rules_select($club_id, $rules_code)
{
	$club_rules_code = null;
	$club_name = null;
	if ($club_id != null)
	{
		list ($club_rules_code, $club_name) = Db::record(get_label('club'), 'SELECT rules, name FROM clubs WHERE id = ?', $club_id);
	}
	
	$found = false;
	
	$all_rules = get_available_rules($club_id, $club_name, $club_rules_code);
	$r = new stdClass();
	$r->rules = $rules_code;
	$r->name = get_label('Custom...');
	$all_rules[] = $r;
	
	echo '<select id="form-rules" onchange="rulesChanged()">';
	foreach ($all_rules as $r)
	{
		if (show_option($r->rules, $rules_code, $r->name))
		{
			$rules_code = '';
		}
	}
	echo '</select>';
}

try
{
	$create = isset($_REQUEST['create']);
	$club_id = 0;
	if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
	}
	
	$demo_game = null;
	$event_id = 0;
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		if ($event_id > 0)
		{
			$table_num = 0;
			if (isset($_REQUEST['table']))
			{
				$table_num = (int)$_REQUEST['table'];
			}
			
			$game_num = 0;
			if (isset($_REQUEST['game']))
			{
				$game_num = (int)$_REQUEST['game'];
			}
		}
		else if (isset($_SESSION['demogame']))
		{
			$demo_game = $_SESSION['demogame']->game;
		}
	}
	
	$tournament_id = 0;
	if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
	}
	
	$league_id = 0;
	if (isset($_REQUEST['league_id']))
	{
		$league_id = (int)$_REQUEST['league_id'];
	}
	
	$rules_id = 0;
	if (isset($_REQUEST['rules_id']))
	{
		$rules_id = $_REQUEST['rules_id'];
	}

	$last_rule = 0;
	if (isset($_SESSION['last_edited_rule']))
	{
		$last_rule = (int)$_SESSION['last_edited_rule'];
	}

	$rules_filter = 'null';
	if ($rules_id > 0)
	{
		list($club_id, $rules_code, $rules_name) = Db::record(get_label('rules'), 'SELECT club_id, rules, name FROM club_rules WHERE id = ?', $rules_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);

		dialog_title(get_label('Edit [0]', get_label('rules [0]', $rules_name)));
?>
		<script>
		function commit(onSuccess)
		{
			var params =
			{
				op: 'change'
				, rules_id: <?php echo $rules_id; ?>
				, name: $("#form-name").val()
				, rules: rulesCode
			};
			json.post("api/ops/rules.php", params, onSuccess);
		}
		</script>
<?php
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<p><input class="longest" id="form-name" value="' . $rules_name . '"></p><p>';
		show_rules_select($club_id, $rules_code);
		echo '</p></td></tr>';
	}
	else if ($event_id > 0)
	{
		if ($table_num > 0 && $game_num > 0)
		{
			list($club_id, $tournament_id, $rules_code, $game, $rules_name, $tournament_name) = Db::record(get_label('game'), 
				'SELECT e.club_id, e.tournament_id, e.rules, g.game, e.name, t.name'.
				' FROM current_games g'.
				' JOIN events e ON e.id = g.event_id'.
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
				' WHERE g.event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
			$game = json_decode($game);
			if (isset($game->clubId))
			{
				$club_id = $game->clubId;
			}
			if (isset($game->eventId))
			{
				$event_id = $game->eventId;
			}
			if (isset($game->tournamentId))
			{
				$tournament_id = $game->tournamentId;
			}
			if (isset($game->rules))
			{
				$rules_code = $game->rules;
			}
			
			if ($tournament_name != null)
			{
				$rules_name = $tournament_name . ' - ' . $rules_name;
			}
			$rules_name .= ' ' . get_label('Table [0] / Game [1]', $table_num, $game_num);
?>			
			<script>
			function commit(onSuccess)
			{
				let text = '<p><?php echo get_label('Also use these rules:'); ?></p><table class="transp">';
<?php
				if ($tournament_id != null)
				{
?>
					text += '<td><input type="checkbox" id="form-tournament" checked></td><td><?php echo get_label('in the tournament'); ?></td></tr>';
<?php
				}
				else
				{
?>
					text += '<td><input type="checkbox" id="form-event" checked></td><td><?php echo get_label('in the event'); ?></td></tr>';
<?php
				}
?>
				text += '<td><input type="checkbox" id="form-club"></td><td><?php echo get_label('in the club'); ?></td></tr></table>';
	
				dlg.okCancel(text, null, 200, function()
				{
					let flags = 0;
					if ($('#form-event').attr("checked"))
						flags |= <?php echo UPDATE_FLAG_EVENT; ?>;
					if ($('#form-club').attr("checked"))
						flags |= <?php echo UPDATE_FLAG_CLUB; ?>;
<?php
					if ($tournament_id != null)
					{
?>
						if ($('#form-tournament').attr("checked"))
							flags |= <?php echo UPDATE_FLAG_TOURNAMENT; ?>;
<?php
					}
?>
					var params =
					{
						op: 'rules'
						, event_id: <?php echo $event_id; ?>
						, table_num: <?php echo $table_num; ?>
						, game_num: <?php echo $game_num; ?>
						, rules_code: rulesCode
						, update_flags: flags
					};
					json.post("api/ops/game.php", params, onSuccess);
				});
			}
			</script>
<?php
		}
		else
		{
			list($club_id, $tournament_id, $rules_code, $rules_name) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, rules, name FROM events WHERE id = ?', $event_id);
			
?>
			<script>
			function commit(onSuccess)
			{
				let text = '<p><?php echo get_label('Also use these rules:'); ?></p><table class="transp">';
<?php
				if ($tournament_id != null)
				{
?>
					text += '<tr><td><input type="checkbox" id="form-tournament"></td><td><?php echo get_label('in the tournament'); ?></td></tr>';
<?php
				}
?>
				text += '<tr><td><input type="checkbox" id="form-club"></td><td><?php echo get_label('in the club'); ?></td></tr></table>';
	
				dlg.okCancel(text, null, 200, function()
				{
					let flags = 0;
					if ($('#form-club').attr("checked"))
						flags |= <?php echo UPDATE_FLAG_CLUB; ?>;
<?php
					if ($tournament_id != null)
					{
?>
						if ($('#form-tournament').attr("checked"))
							flags |= <?php echo UPDATE_FLAG_TOURNAMENT; ?>;
<?php
					}
?>
					var params =
					{
						op: 'change'
						, event_id: <?php echo $event_id; ?>
						, rules_code: rulesCode
						, update_flags: flags
					};
					json.post("api/ops/event.php", params, onSuccess);
				});
			}
			</script>
<?php
		}
		if (is_null($tournament_id))
		{
			$tournament_id = 0;
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_MANAGER | PERMISSION_EVENT_REFEREE, $club_id, $event_id);
		}
		else
		{
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_MANAGER | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
		}
		
		dialog_title(get_label('Edit [0]', get_label('rules for [0]', $rules_name)));
		echo '<table class="transp" width="100%"><tr><td><h3>';
		show_rules_select($club_id, $rules_code);
		echo '</h3><br></td></tr>';
	}
	else if ($demo_game)
	{
		$club_id = $demo_game->clubId;
		$rules_code = $demo_game->rules;
		$rules_name = get_label('Demo');
		
?>			
			<script>
			function commit(onSuccess)
			{
				var params =
				{
					op: 'rules'
					, event_id: 0
					, rules_code: rulesCode
				};
				json.post("api/ops/game.php", params, onSuccess);
			}
			</script>
<?php
		
		dialog_title(get_label('Edit [0]', get_label('rules for [0]', $rules_name)));
		echo '<table class="transp" width="100%"><tr><td><h3>';
		show_rules_select($club_id, $rules_code);
		echo '</h3><br></td></tr>';
	}
	else if ($tournament_id > 0)
	{
		list($club_id, $rules_code, $rules_name) = Db::record(get_label('rules'), 'SELECT club_id, rules, name FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id);

		dialog_title(get_label('Edit [0]', get_label('rules for [0]', $rules_name)));
		
?>
			<script>
			function commit(onSuccess)
			{
				let text = '<p><?php echo get_label('Also use these rules:'); ?></p><table class="transp">';
				text += '<tr><td><input type="checkbox" id="form-club"></td><td><?php echo get_label('in the club'); ?></td></tr></table>';
	
				dlg.okCancel(text, null, 200, function()
				{
					let flags = 0;
					if ($('#form-club').attr("checked"))
						flags |= <?php echo UPDATE_FLAG_CLUB; ?>;
					var params =
					{
						op: 'change'
						, tournament_id: <?php echo $tournament_id; ?>
						, rules_code: rulesCode
						, update_flags: flags
					};
					json.post("api/ops/tournament.php", params, onSuccess);
				});
			}
			</script>
<?php
		echo '<table class="transp" width="100%"><tr><td><h3>';
		show_rules_select($club_id, $rules_code);
		echo '</h3><br></td></tr>';
	}
	else if ($club_id <= 0)
	{
		if ($league_id > 0)
		{
			list ($rules_code, $rules_filter, $rules_name) = Db::record(get_label('league'), 'SELECT default_rules, rules, name FROM leagues l WHERE id = ?', $league_id);
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);

			dialog_title(get_label('Edit [0]', get_label('rules for [0]', $rules_name)));
?>
			<script>
			function commit(onSuccess)
			{
				var params =
				{
					op: 'change'
					, league_id: <?php echo $league_id; ?>
					, default_rules: rulesCode
				};
				json.post("api/ops/league.php", params, onSuccess);
			}
			</script>
<?php
			echo '<table class="transp" width="100%"><tr><td><h3>';
			echo $rules_name;
			echo '</h3><br></td></tr>';
		}
		else
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
	}
	else if ($create)
	{
		$rules_name = '';
		list($rules_code, $rules_name) = Db::record(get_label('club'), 'SELECT rules, name FROM clubs WHERE id = ?', $club_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		
		dialog_title(get_label('Create [0]', get_label('rules for [0]', $rules_name)));
?>
		<script>
		function commit(onSuccess)
		{
			var params =
			{
				op: 'create'
				, club_id: <?php echo $club_id; ?>
				, name: $("#form-name").val()
				, rules: rulesCode
			};
			json.post("api/ops/rules.php", params, onSuccess);
		}
		</script>
<?php
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<p><input class="longest" id="form-name"></p><p>';
		show_rules_select(null, $rules_code);
		echo '</p></td></tr>';
	}
	else if ($league_id > 0)
	{
		list ($rules_code, $rules_filter,  $rules_name) = Db::record(get_label('league'), 'SELECT lc.rules, l.rules, l.name FROM league_clubs lc JOIN leagues l ON l.id = lc.league_id WHERE lc.league_id = ? AND lc.club_id = ?', $league_id, $club_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);

		dialog_title(get_label('Edit [0]', get_label('rules for [0]', $rules_name)));
?>
		<script>
		function commit(onSuccess)
		{
			var params =
			{
				op: 'change'
				, club_id: <?php echo $club_id; ?>
				, league_id: <?php echo $league_id; ?>
				, rules: rulesCode
			};
			json.post("api/ops/rules.php", params, onSuccess);
		}
		</script>
<?php
		echo '<table class="transp" width="100%"><tr><td><h3>';
		echo $rules_name;
		echo '</h3><br></td></tr>';
	}
	else
	{
		list($rules_code, $rules_name) = Db::record(get_label('club'), 'SELECT rules, name FROM clubs WHERE id = ?', $club_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		
		dialog_title(get_label('Edit [0]', get_label('rules for [0]', $rules_name)));
?>
		<script>
		function commit(onSuccess)
		{
			var params =
			{
				op: 'change'
				, club_id: <?php echo $club_id; ?>
				, rules: rulesCode
			};
			json.post("api/ops/rules.php", params, onSuccess);
		}
		</script>
<?php
		echo '<table class="transp" width="100%"><tr><td><p>';
		show_rules_select(null, $rules_code);
		echo '</p></td></tr>';
	}
	
	echo '<tr><td><span id="form-rules-edit"></span></td></tr>';
	
	echo '</table><script>';
	
	$rules = include '../include/languages/' . get_lang_code($_lang) . '/rules.php';
	$rules_array = array();
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$option = $_rules_options[$i];
		$paragraph = $option[RULE_OPTION_PARAGRAPH];
		$item = $option[RULE_OPTION_ITEM];
		$rule = $rules[$paragraph][RULE_PARAGRAPH_ITEMS][$item];
		$rules_array[] = array($rule[RULE_ITEM_NAME], ($paragraph + 1) . '.' . ($item + 1), $rule[RULE_ITEM_OPTIONS], $rule[RULE_ITEM_OPTIONS_SHORT], $option[RULE_OPTION_NAME], $option[RULE_OPTION_VALUES]);
	}
	echo "\nvar rulesOptions = " . json_encode($rules_array) . ";\n";
	
?>	
	var rulesCode = "<?php echo $rules_code; ?>";
	var rulesFilter = <?php echo $rules_filter; ?>;
	var currentRule = <?php echo $last_rule; ?>;
	
	function rulesChanged()
	{
		rulesCode = $('#form-rules').val();
		showRule(currentRule);
	}

	function getRule(ruleNum)
	{
		return rulesCode.substr(ruleNum, 1);
	}
	
	function setRule(ruleNum, value)
	{
		rulesCode = rulesCode.substr(0, ruleNum) + value + rulesCode.substr(ruleNum + 1);
		$('#form-full-text').html('<p>' + rulesOptions[ruleNum][1] + '. ' + rulesOptions[ruleNum][2][value] + '</p>');
		if ($('#form-rules').length > 0)
		{
			$('#form-rules option:last').val(rulesCode);
			$('#form-rules').val(rulesCode);
		}
	}
	
	function isRuleAllowed(ruleNum, value)
	{
		if (rulesFilter == null)
			return true;
		
		var ruleName = rulesOptions[ruleNum][4];
		var ruleValue = rulesOptions[ruleNum][5][value];
		var rule = rulesFilter[ruleName];
		if (typeof rule == "object")
		{
			for (var i = 0; i < rule.length; ++i)
				if (rule[i] == ruleValue)
					return true;
			return rule.length <= 0;
		}
		return typeof rule == "undefined" || rule == ruleValue;
	}
	
	function isRuleConfigurable(ruleNum)
	{
		if (rulesFilter == null)
			return true;
		
		var ruleName = rulesOptions[ruleNum][4];
		var rule = rulesFilter[ruleName];
		return typeof rule == "undefined" || (typeof rule == "object" && rule.length != 1);
	}		
		
	function showRule(ruleNum)
	{
		if (currentRule != ruleNum)
		{
			currentRule = ruleNum;
			json.post("api/ops/rules.php", 
			{
				op: 'set_last_rule',
				rule: currentRule
			});
		}
		
		var html = '<table class="bordered" width="100%"><tr class="bordered"><td class="bordered" width="300" valign="top">';
		var prev = -1;
		var next = -1;
		for (i = 0; i < rulesOptions.length; ++i)
		{
			if (!isRuleConfigurable(i))
			{
				if (i == ruleNum)
				{
					++ruleNum;
				}
				continue;
			}
			
			if (i == ruleNum)
			{
				html += '<p><strong>' + rulesOptions[i][1] + ". " + rulesOptions[i][0] + "</strong></p>";
			}
			else
			{
				html += '<p><a href="javascript:showRule(' + i + ')"> ' + rulesOptions[i][1] + ". " + rulesOptions[i][0] + "</a></p>";
				if (i < ruleNum)
				{
					prev = i;
				}
				else if (next < 0 && i > ruleNum)
				{
					next = i;
				}
			}
		}
		
		if (ruleNum < rulesOptions.length)
		{
			var curOp = getRule(ruleNum);
			var op = rulesOptions[ruleNum];
			var ops = op[3];
			
			html += '</td><td valign="top"><table height="100%" width="100%"><tr><td align="left" width="32">';
			if (prev >= 0)
			{
				html += '<button class="icon" onclick="showRule(' + prev + ')"><img src="images/prev.png" border="0"></button>';
			}
			html += '</td><td><b>' + op[1] + ". " + op[0] + '</b></td><td align="right">';
			if (next >= 0)
			{
				html += '<button class="icon" onclick="showRule(' + next + ')"><img src="images/next.png" border="0"></button>';
			}
			html += '</td></tr>';
			
			for (i = 0; i < ops.length; ++i)
			{
				if (isRuleAllowed(ruleNum, i))
				{
					html += '<tr><td align="center" valign="center"><input name="rb" type="radio" onclick="setRule(' + ruleNum + ', ' + i + ')"';
					if (curOp == i)
					{
						html += ' checked';
					}
					html += '><td colspan="2"><p><b>' + ops[i] + '</b></p></td></tr><tr><td></td><td style="padding-left:10pt;">' + op[2][i] + "</td></tr>";
				}
			}
			html += '</table>';
		}
		else
		{
			html += "<?php echo get_label('[0] does not allow custom rules.', $rules_name); ?>";
		}
		html += "</td></tr></table>";
		$('#form-rules-edit').html(html);
	}
	
	showRule(currentRule);
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