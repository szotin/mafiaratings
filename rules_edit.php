<?php

require_once 'include/session.php';
require_once 'include/rules.php';

initiate_session();

try
{
	$create = isset($_REQUEST['create']);
	$club_id = 0;
	if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
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

	if ($club_id <= 0)
	{
		list($club_id, $rules_code, $rules_name) = Db::record(get_label('rules'), 'SELECT club_id, rules, name FROM club_rules WHERE id = ?', $rules_id);
	}
	else if ($create)
	{
		$rules_name = '';
		list($rules_code) = Db::record(get_label('club'), 'SELECT rules FROM clubs WHERE id = ?', $club_id);
	}
	else if ($rules_id > 0)
	{
		list($rules_code, $rules_name) = Db::record(get_label('rules'), 'SELECT rules, name FROM club_rules WHERE id = ? AND club_id = ?', $rules_id, $club_id);
	}
	else if ($league_id > 0)
	{
		list($rules_code, $rules_name) = Db::record(get_label('league'), 'SELECT c.rules, l.name FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE club_id = ?', $club_id);
	}
	else
	{
		list($rules_code, $rules_name) = Db::record(get_label('club'), 'SELECT rules, name FROM clubs WHERE id = ?', $club_id);
	}
	
	if ($_profile == NULL || !$_profile->is_club_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if ($create)
	{
		dialog_title(get_label('Create [0]', get_label('rules')));
?>
		<script>
		var rulesCode = "<?php echo $rules_code; ?>";
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
		echo '<p><input class="longest" id="form-name"></p>';
		echo '</td></tr>';
	}
	else if ($rules_id > 0)
	{
		dialog_title(get_label('Edit [0]', get_label('rules [0]', $rules_name)));
?>
		<script>
		var rulesCode = "<?php echo $rules_code; ?>";
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
		echo '<p><input class="longest" id="form-name" value="' . $rules_name . '"></p>';
		echo '</td></tr>';
	}
	else if ($league_id > 0)
	{
		dialog_title(get_label('Edit [0]', get_label('rules [0]', $rules_name)));
?>
		<script>
		var rulesCode = "<?php echo $rules_code; ?>";
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
		dialog_title(get_label('Edit [0]', get_label('rules [0]', $rules_name)));
?>
		<script>
		var rulesCode = "<?php echo $rules_code; ?>";
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
		echo '<table class="transp" width="100%"><tr><td><h3>';
		echo $rules_name;
		echo '</h3><br></td></tr>';
	}
	
	echo '<tr><td><span id="form-rules"></span></td></tr>';
	
	echo '</table><script>';
	
	$rules = include 'include/languages/' . $_lang_code . '/rules.php';
	$rules_array = array();
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$paragraph = $_rules_options[$i][RULE_OPTION_PARAGRAPH];
		$item = $_rules_options[$i][RULE_OPTION_ITEM];
		$rule = $rules[$paragraph][RULE_PARAGRAPH_ITEMS][$item];
		$rules_array[] = array($rule[RULE_ITEM_NAME], ($paragraph + 1) . '.' . ($item + 1), $rule[RULE_ITEM_OPTIONS], $rule[RULE_ITEM_OPTIONS_SHORT]);
	}
	echo "\nvar rulesOptions = " . json_encode($rules_array) . ";\n";
	
?>	

	function getRule(ruleNum)
	{
		return rulesCode.substr(ruleNum, 1);
	}
	
	function setRule(ruleNum, value)
	{
		rulesCode = rulesCode.substr(0, ruleNum) + value + rulesCode.substr(ruleNum + 1);
		$('#form-full-text').html('<p>' + rulesOptions[ruleNum][1] + '. ' + rulesOptions[ruleNum][2][value] + '</p>');
	}
		
	function showRule(ruleNum)
	{
		var op = rulesOptions[ruleNum];
		var ops = op[3];
		var html = '<table class="bordered" width="100%"><tr class="bordered"><td class="bordered" width="300" valign="top">';
		for (i = 0; i < rulesOptions.length; ++i)
		{
			if (i == ruleNum)
			{
				html += '<p><strong>' + rulesOptions[i][1] + ". " + rulesOptions[i][0] + "</strong></p>";
			}
			else
			{
				html += '<p><a href="javascript:showRule(' + i + ')"> ' + rulesOptions[i][1] + ". " + rulesOptions[i][0] + "</a></p>";
			}
		}
		
		html += '</td><td valign="top"><table height="100%" width="100%"><tr><td align="left" width="32">';
		if (ruleNum > 0)
		{
			html += '<button class="icon" onclick="showRule(' + (ruleNum - 1) + ')"><img src="images/prev.png" border="0"></button>';
		}
		html += '</td><td><b>' + op[1] + ". " + op[0] + '</b></td><td align="right">';
		if (ruleNum < rulesOptions.length - 1)
		{
			html += '<button class="icon" onclick="showRule(' + (ruleNum + 1) + ')"><img src="images/next.png" border="0"></button>';
		}
		html += '</td></tr>';
		
		var curOp = getRule(ruleNum);
		for (i = 0; i < ops.length; ++i)
		{
			html += '<tr><td align="center" valign="center"><input name="rb" type="radio" onclick="setRule(' + ruleNum + ', ' + i + ')"';
			if (curOp == i)
			{
				html += ' checked';
			}
			html += '><td colspan="2"><p>' + ops[i] + "</p></td></tr>";
		}
		html += '</table><table width="100%"><tr><td id="form-full-text"><p>';
		html += op[1] + '. ' + op[2][curOp];
		html += "</p></td></tr></table></td></tr></table>";
		$('#form-rules').html(html);
	}
	
	showRule(0);
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