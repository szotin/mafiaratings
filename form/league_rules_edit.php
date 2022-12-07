<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/rules.php';

initiate_session();

try
{
	if (!isset($_REQUEST['league_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = (int)$_REQUEST['league_id'];
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	list($league_rules, $league_name) = Db::record(get_label('league'), 'SELECT rules, name FROM leagues WHERE id = ?', $league_id);
	dialog_title(get_label('Edit [0]', get_label('rules [0]', $league_name)));
?>
	<script>
	var rules = <?php echo $league_rules; ?>;
	function commit(onSuccess)
	{
		var params =
		{
			op: 'change'
			, league_id: <?php echo $league_id; ?>
			, rules: rules
		};
		json.post("api/ops/league.php", params, onSuccess);
	}
	</script>
<?php
	echo '<table class="transp" width="100%"><tr><td><span id="form-rules"></span></td></tr>';
	
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

	function isRuleAllowed(ruleNum, value)
	{
		var ruleName = rulesOptions[ruleNum][4];
		var ruleValue = rulesOptions[ruleNum][5][value];
		var rule = rules[ruleName];
		if (typeof rule == "object")
		{
			for (var i = 0; i < rule.length; ++i)
				if (rule[i] == ruleValue)
					return true;
			return rule.length <= 0;
		}
		return typeof rule == "undefined" || rule == ruleValue;
	}
	
	function setRule(ruleNum, value)
	{
		var ruleName = rulesOptions[ruleNum][4];
		var allValues = rulesOptions[ruleNum][5];
		var ruleValue = allValues[value];
		var rule = rules[ruleName];
		if ($("#option" + value).attr("checked"))
		{
			if (typeof rule == "string" || typeof rule == "boolean")
			{
				if (rule != ruleValue)
				{
					rule = [rule, ruleValue];
				}
			}
			else if (typeof rule != "undefined")
			{
				for (var i = 0; i < rule.length; ++i)
				{
					if (rule[i] == ruleValue)
					{
						showRule(ruleNum);
						return;
					}
				}
				rule.push(ruleValue);
			}
			
			if (rule.length == allValues.length)
			{
				rule = null;
			}
		}
		else if (typeof rule != "string" && typeof rule != "boolean")
		{
			if (typeof rule == "undefined")
			{
				rule = [];
				for (var i = 0; i < allValues.length; ++i)
				{
					if (i != value)
					{
						rule.push(allValues[i]);
					}
				}
			}
			else
			{
				if (rule.length == 0)
				{
					for (var i = 0; i < allValues.length; ++i)
					{
						if (i != value)
						{
							rule.push(allValues[i]);
						}
					}
				}
				else for (var i = 0; i < rule.length; ++i)
				{
					if (rule[i] == ruleValue)
					{
						rule.splice(i, 1);
						break;
					}
				}
			}
			
			if (rule.length == 1)
			{
				rule = rule[0];
			}
		}
		
		if (rule != null)
		{
			rules[ruleName] = rule;
		}
		else
		{
			delete rules[ruleName];
		}
		showRule(ruleNum);
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
		
		for (i = 0; i < ops.length; ++i)
		{
			html += '<tr><td align="center" valign="center"><input name="rb" type="checkbox" id="option' + i + '" onclick="setRule(' + ruleNum + ', ' + i + ')"';
			if (isRuleAllowed(ruleNum, i))
			{
				html += ' checked';
			}
			html += '><td colspan="2"><p>' + ops[i] + '</p></td></tr><tr><td></td><td style="padding-left:8pt;">' + op[1] + '. ' + op[2][i];
		}
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