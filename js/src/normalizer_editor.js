var _data = null;
var _isDirty = false;
var _onChangeData = null;

function isNormalizerDataCorrect()
{
	return true;
}

function dirty(isDirty)
{
	// if (isDirty)
	// {
		// console.log(_data.normalizer);
	// }
    if (typeof isDirty == "boolean")
    {
		_isDirty = isDirty;
		if (_onChangeData)
		{
			_onChangeData(_data, isDirty);
		}
    }
    return _isDirty;
}

function onNormalizerNameChange()
{
	_data.name = $("#normalizer-name").val();
	dirty(true);
}

function setNormalizerVersion(version)
{
	if (_data.version != version)
	{
		_data.version = version;
		refreshNormalizerEditor();
	}
}

function createPolicy()
{
	var norm = _data.normalizer;
	var p = {};
	if (norm.policies)
	{
		var i = norm.policies.length;
		norm.policies.push(p);
		if (i > 0)
		{
			var c = getPolicyCondName(norm.policies[i-1]);
			if (c)
			{
				p[c] = {};
				initMinCondition(i);
				initMaxCondition(i);
			}
		}
	}
	else
	{
		norm.policies = [p];
	}
	refreshNormalizerEditor(true);
}

function deletePolicy(policyNum)
{
	var norm = _data.normalizer;
    norm.policies.splice(policyNum, 1);
	if (norm.policies.length == 0)
	{
		delete norm.policies;
	}
    refreshNormalizerEditor(true);
}

function onNormalizerPolicyChange()
{
	var policy = $('#normalizer-policy').val();
	var norm = _data.normalizer;
	if (policy != 0)
		norm.policy = parseInt(policy);
	else
		delete norm.policy;
	dirty(true);
}

function onPolicyConditionChange(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	delete policy.games;
	delete policy.gamesPerc;
	delete policy.rounds;
	delete policy.roundsPerc;
	delete policy.winPerc;
	var condName = $("#policy-cond-" + policyNum).val();
	if (condName != '')
	{
		policy[condName] = {};
	}
	initMinCondition(policyNum);
	initMaxCondition(policyNum);
    refreshNormalizerEditor(true);
}

function getPolicyCondName(policy)
{
	if (policy.games)
	{
		return 'games';
	}
	else if (policy.gamesPerc)
	{
		return 'gamesPerc';
	}
	else if (policy.rounds)
	{
		return 'rounds';
	}
	else if (policy.roundsPerc)
	{
		return 'roundsPerc';
	}
	else if (policy.winPerc)
	{
		return 'winPerc';
	}
	return null;
}

function initMinCondition(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var prop = getPolicyCondName(policy);
	if (prop != null)
	{
		for (var i = policyNum - 1; i >= 0; --i)
		{
			var p = _data.normalizer.policies[i];
			if (p[prop] && p[prop].max)
			{
				policy[prop].min = p[prop].max;
				return;
			}
		}
		policy[prop].min = 0;
	}
}

function initMaxCondition(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var prop = getPolicyCondName(policy);
	if (prop != null)
		policy[prop].max = (policy[prop].min ? policy[prop].min : 0) + 10;
}

function onMinClick(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	if ($("#policy-cond-min-def-" + policyNum).attr("checked"))
	{
		initMinCondition(policyNum);
	}
	else
	{
		var prop = getPolicyCondName(policy);
		delete policy[prop].min;
	}
    refreshNormalizerEditor(true);
}

function onCondMinChange(policyNum)
{
	var p = _data.normalizer.policies[policyNum];
	var m = parseInt($('#policy-cond-min-' + policyNum).val());
	p[getPolicyCondName(p)].min = m;
	$('#policy-cond-max-' + policyNum).prop('min', m);
    dirty(true);
}

function onCondMaxChange(policyNum)
{
	var p = _data.normalizer.policies[policyNum];
	var m = parseInt($('#policy-cond-max-' + policyNum).val());
	p[getPolicyCondName(p)].max = m;
	$('#policy-cond-min-' + policyNum).prop('max', m);
    dirty(true);
}

function onMaxClick(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	if ($("#policy-cond-max-def-" + policyNum).attr("checked"))
	{
		initMaxCondition(policyNum);
	}
	else
	{
		var prop = getPolicyCondName(policy);
		delete policy[prop].max;
	}
    refreshNormalizerEditor(true);
}

function getCondHtml(policyNum, min, max)
{
	var str = _data.strings;
	var policy = _data.normalizer.policies[policyNum];
	var html = '';
	var minChecked = (typeof min != "undefined");
	var maxChecked = (typeof max != "undefined");
	
	var minMinProp = 'min="0"';
	var minMaxProp = '';
	var maxMinProp = 'min="0"';
	var maxMaxProp = '';
	var minPre = '';
	var maxPre = '';
	var post = '';
	if (policy.games)
	{
		if (typeof policy.games.max != "undefined")
			minMaxProp = ' max="' + policy.games.max + '"';
		if (typeof policy.games.min != "undefined")
		{
			maxMinProp = ' min="' + policy.games.min + '"';
			maxPre = str.cntMaxPre1;
		}
		else
		{
			maxPre = str.cntMaxPre2;
		}
		minPre = str.cntMinPre;
		post = str.gamesPost;
	}
	else if (policy.gamesPerc)
	{
		if (typeof policy.gamesPerc.max != "undefined")
			minMaxProp = ' max="' + policy.gamesPerc.max + '"';
		if (typeof policy.gamesPerc.min != "undefined")
		{
			maxMinProp = ' min="' + policy.gamesPerc.min + '"';
			maxPre = str.cntMaxPre1;
		}
		else
		{
			maxPre = str.cntMaxPre2;
		}
		maxMaxProp = 'max="100"';
		minPre = str.cntMinPre;
		post = str.gamesPercPost;
	}
	else if (policy.rounds)
	{
		if (typeof policy.rounds.max != "undefined")
			minMaxProp = ' max="' + policy.rounds.max + '"';
		if (typeof policy.rounds.min != "undefined")
		{
			maxMinProp = ' min="' + policy.rounds.min + '"';
			maxPre = str.cntMaxPre1;
		}
		else
		{
			maxPre = str.cntMaxPre2;
		}
		minPre = str.cntMinPre;
		post = str.roundsPost;
	}
	else if (policy.roundsPerc)
	{
		if (typeof policy.roundsPerc.max != "undefined")
			minMaxProp = ' max="' + policy.roundsPerc.max + '"';
		if (typeof policy.roundsPerc.min != "undefined")
		{
			maxMinProp = ' min="' + policy.roundsPerc.min + '"';
			maxPre = str.cntMaxPre1;
		}
		else
		{
			maxPre = str.cntMaxPre2;
		}
		maxMaxProp = 'max="100"';
		minPre = str.cntMinPre;
		post = str.roundsPercPost;
	}
	else if (policy.winPerc)
	{
		if (typeof policy.winPerc.max != "undefined")
			minMaxProp = ' max="' + policy.winPerc.max + '"';
		if (typeof policy.winPerc.min != "undefined")
		{
			maxMinProp = ' min="' + policy.winPerc.min + '"';
			maxPre = str.rateMaxPre1;
		}
		else
		{
			maxPre = str.rateMaxPre2;
		}
		maxMaxProp = 'max="100"';
		minPre = str.rateMinPre;
		post = str.winPercPost;
	}
	
	html += '<p><input type="checkbox" id="policy-cond-min-def-' + policyNum + '" onclick="onMinClick(' + policyNum + ')"' + (minChecked ? ' checked' : '') + '><label for="policy-cond-min-def-' + policyNum + '"> ' + str.condMin + '</label><br>';
	html += '<input type="checkbox" id="policy-cond-max-def-' + policyNum + '" onclick="onMaxClick(' + policyNum + ')"' + (maxChecked ? ' checked' : '') + '><label for="policy-cond-max-def-' + policyNum + '"> ' + str.condMax + '</label></p>';
	
	html += '<p><table class="transp">';
	if (minChecked)
	{
		html += '<tr><td><label for="policy-cond-min-' + policyNum + '">' + minPre + '&nbsp;</label></td><td><input type="number"' + minMinProp + minMaxProp + ' style="width: 35px;" id="policy-cond-min-' + policyNum + '" value="' + min + '" onchange="onCondMinChange(' + policyNum + ')">' + post + '</td></tr>';
	}
	if (maxChecked)
	{
		html += '<tr><td><label for="policy-cond-max-' + policyNum + '">' + maxPre + '&nbsp;</label></td><td><input type="number"' + maxMinProp + maxMaxProp + ' style="width: 35px;" id="policy-cond-max-' + policyNum + '" value="' + max + '" onchange="onCondMaxChange(' + policyNum + ')">' + post + '</td></tr>';
	}
	html += '</table></p>';
	return html;
}

function onMultiplyInterpClicked(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	if ($("#policy-multiply-interp-" + policyNum).attr("checked"))
	{
		if (!policy.multiply)
		{
			policy.multiply = { val: 1 }
		}
		policy.multiply.max = policy.multiply.val + 0.4;
	}
	else if (policy.multiply.val == 1)
	{
		delete policy.multiply;
	}
	else
	{
		delete policy.multiply.max;
	}
    refreshNormalizerEditor(true);
}

function onPolicyMultiplyValChanged(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var val = parseFloat($("#policy-multiply-val-" + policyNum).val());
	if (!policy.multiply)
	{
		policy.multiply = {};
	}
	policy.multiply.val = val;
	$('#policy-multiply-max-' + policyNum).prop('min', val);
	dirty(true);
}

function onPolicyMultiplyMaxChanged(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var max = parseFloat($("#policy-multiply-max-" + policyNum).val());
	policy.multiply.max = max;
	$('#policy-multiply-val-' + policyNum).prop('max', max);
	dirty(true);
}

function onPolicyAvTypeChange(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var obj = policy.gameAv ? policy.gameAv : policy.roundAv;
	delete obj.add;
	delete obj.min;
	switch ($('#policy-av-type-' + policyNum).val())
	{
		case '1':
			obj.add = 1;
			break;
		case '2':
			obj.min = 10;
			break;
	}
    refreshNormalizerEditor(true);
}

function onPolicyAvValueChange(policyNum)
{
	var p = _data.normalizer.policies[policyNum];
	var v = parseInt($('#policy-av-val-' + policyNum).val());
	if (p.roundAv)
	{
		if (p.roundAv.min)
			p.roundAv.min = v;
		else
			p.roundAv.add = v;
	}
	else
	{
		if (!p.gameAv)
			p.gameAv = {};
		if (p.gameAv.min)
			p.gameAv.min = v;
		else
			p.gameAv.add = v;
	}
    dirty(true);
}

function getRuleHtml(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var str = _data.strings;
	var html = '';
	if (policy.gameAv)
	{
		var avType = 0;
		if (typeof policy.gameAv.add != "undefined")
		{
			avType = 1;
		}
		else if (typeof policy.gameAv.min != "undefined")
		{
			avType = 2;
		}
		html += '<p><select id="policy-av-type-' + policyNum + '" onchange="onPolicyAvTypeChange(' + policyNum + ')">';
		html += '<option value="0"' + (avType == 0 ? ' selected' : '') + '>' + str.avTypeNothing + '</option>'; 
		html += '<option value="1"' + (avType == 1 ? ' selected' : '') + '>' + str.avGamesTypeAdd + '</option>'; 
		html += '<option value="2"' + (avType == 2 ? ' selected' : '') + '>' + str.avGamesTypeMin + '</option>'; 
		html += '</select></p>';
		
		switch (avType)
		{
			case 0:
				html += '<p>' + str.avGamesNone + '</p>';
				break;
			case 1:
				html += '<p>' + str.avGamesAdd + ': <input type="number" style="width: 35px;" min="1" id="policy-av-val-' + policyNum + '" onchange="onPolicyAvValueChange(' + policyNum + ')" value="' + policy.gameAv.add + '"></p>';
				break;
			case 2:
				html += '<p>' + str.avGamesMin + ': <input type="number" style="width: 35px;" min="1" id="policy-av-val-' + policyNum + '" onchange="onPolicyAvValueChange(' + policyNum + ')" value="' + policy.gameAv.min + '"></p>';
				break;
		}
	}
	else if (policy.roundAv)
	{
		var avType = 0;
		if (typeof policy.roundAv.add != "undefined")
		{
			avType = 1;
		}
		else if (typeof policy.roundAv.min != "undefined")
		{
			avType = 2;
		}
		html += '<p><select id="policy-av-type-' + policyNum + '" onchange="onPolicyAvTypeChange(' + policyNum + ')">';
		html += '<option value="0"' + (avType == 0 ? ' selected' : '') + '>' + str.avTypeNothing + '</option>'; 
		html += '<option value="1"' + (avType == 1 ? ' selected' : '') + '>' + str.avRoundsTypeAdd + '</option>'; 
		html += '<option value="2"' + (avType == 2 ? ' selected' : '') + '>' + str.avRoundsTypeMin + '</option>'; 
		html += '</select></p>';
		
		switch (avType)
		{
			case 0:
				html += '<p>' + str.avRoundsNone + '</p>';
				break;
			case 1:
				html += '<p>' + str.avRoundsAdd + ': <input type="number" style="width: 35px;" min="1" id="policy-av-val-' + policyNum + '" onchange="onPolicyAvValueChange(' + policyNum + ')" value="' + policy.roundAv.add + '"></p>';
				break;
			case 2:
				html += '<p>' + str.avRoundsMin + ': <input type="number" style="width: 35px;" min="1" id="policy-av-val-' + policyNum + '" onchange="onPolicyAvValueChange(' + policyNum + ')" value="' + policy.roundAv.min + '"></p>';
				break;
		}
	}
	else if (policy.byWinRate)
	{
	}
	else
	{
		var val = 1;
		var max = -1;
		if (policy.multiply)
		{
			if (typeof policy.multiply.val != "undefined")
			{
				val = policy.multiply.val;
			}   
			if (typeof policy.multiply.max != "undefined")
			{
				max = policy.multiply.max;
			}
		}
		
		html += '<p><input type="checkbox" id="policy-multiply-interp-' + policyNum + '" onclick="onMultiplyInterpClicked(' + policyNum + ')';
		if (max >= 0)
		{
			html += '" checked> '  + str.multiplyInterp + '</p>';
			html += '<p><table class="transp"><tr><td>' + str.multiplyMin + '&nbsp;</td><td><input id="policy-multiply-val-' + policyNum + '" type="number" style="width: 40px;" min="0" max="' + max + '" step="0.1" value="' + val + '" onchange="onPolicyMultiplyValChanged(' + policyNum + ')"></td></tr>';
			html += '<tr><td>' + str.multiplyMax + '&nbsp;</td><td><input id="policy-multiply-max-' + policyNum + '" type="number" style="width: 40px;" min="' + val + '" step="0.1" value="' + max + '" onchange="onPolicyMultiplyMaxChanged(' + policyNum + ')"></td></tr></table></p>';
		}
		else
		{
			html += '"> '  + str.multiplyInterp + '</p>';
			html += '<p>' + str.multiplyVal + ' <input id="policy-multiply-val-' + policyNum + '" type="number" style="width: 40px;" min="0" step="0.1" value="' + val + '" onchange="onPolicyMultiplyValChanged(' + policyNum + ')"></p>';
		}
	}
	return html;
}

function onNormalizerRuleChange(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	delete policy.multiply;
	delete policy.gameAv;
	delete policy.roundAv;
	delete policy.byWinRate;
	switch ($('#normalizer-policy-rule-' + policyNum).val())
	{
		case '1':
			policy.gameAv = {};
			break;
		case '2':
			policy.roundAv = {};
			break;
		case '3':
			policy.byWinRate = {};
			break;
	}
	refreshNormalizerEditor(true);
}

function getCondHelp(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var str = _data.strings;
	var help = '';
	if (policy.gamesPerc)
	{
		return str.gamesPercHelp;
	}
	else if (policy.roundsPerc)
	{
		return str.roundsPercHelp;
	}
	return '';
}

function getRuleHelp(policyNum)
{
	var policy = _data.normalizer.policies[policyNum];
	var str = _data.strings;
	if (policy.gameAv)
	{
		if (typeof policy.gameAv.add != "undefined")
		{
			return str.avGamesTypeAddHelp;
		}
		if (typeof policy.gameAv.min != "undefined")
		{
			return str.avGamesTypeMinHelp;
		}
		return str.avGamesTypeNothingHelp;
	}
	else if (policy.roundAv)
	{
		if (typeof policy.roundAv.add != "undefined")
		{
			return str.avRoundsTypeAddHelp;
		}
		if (typeof policy.roundAv.min != "undefined")
		{
			return str.avRoundsTypeMinHelp;
		}
		return str.avRoundsTypeNothingHelp;
	}
	else if (policy.byWinRate)
	{
		return str.winRateHelp;
	}
	else
	{
		var max = -1;
		if (policy.multiply)
		{
			if (typeof policy.multiply.max != "undefined")
			{
				max = policy.multiply.max;
			}
		}
		
		if (max >= 0)
		{
			return str.multiplyInterpHelp;
		}
	}
	return '';
}

function refreshNormalizerEditor(isDirty)
{
	var norm = _data.normalizer;
	var str = _data.strings;
	
    var html = '<table width="100%" class="bordered light">';
	if (_data.name)
	{
		html += '<tr class="dark"><td colspan="3"><label for="normalizer-name">' + str.name + ': </label><input id="normalizer-name" value="' + _data.name + '" onChange="onNormalizerNameChange()"><label for="normalizer-name"> ' + str.version + ': ' + _data.version + '</label></td></tr>';
	}
	
	html += '<tr class="darker"><td width="32" align="center"><button class="icon" title="' + str.policyAdd + '" onclick="createPolicy()"><img src="images/create.png"></button></td>';
	html += '<td colspan="2">' + str.policies + '</td></tr>';
	if (norm.policies)
	{
		for (var i = 0; i < norm.policies.length; ++i)
		{
			var policy = norm.policies[i];
			var condName = getPolicyCondName(policy);

			html += '<tr style="border-bottom: none;"><td valign="top" rowspan="2"><button class="icon" title="' + str.policyDel + '" onclick="deletePolicy(' + i + ')"><img src="images/delete.png"></button><td width="400" valign="top">';
			html += '<p><select id="policy-cond-' + i + '" onchange="onPolicyConditionChange(' + i + ')">';
			html += '<option value=""' + (condName == null ? ' selected' : '') + '>' + str.condAll + '</option>';
			html += '<option value="games"' + (condName == 'games' ? ' selected' : '') + '>' + str.condGames + '</option>';
			html += '<option value="gamesPerc"' + (condName == 'gamesPerc' ? ' selected' : '') + '>' + str.condGamesPerc + '</option>';
			html += '<option value="rounds"' + (condName == 'rounds' ? ' selected' : '') + '>' + str.condEvents + '</option>';
			html += '<option value="roundsPerc"' + (condName == 'roundsPerc' ? ' selected' : '') + '>' + str.condEventsPerc + '</option>';
			html += '<option value="winPerc"' + (condName == 'winPerc' ? ' selected' : '') + '>' + str.condwinPerc + '</option>';
			html += '</select></p>';
			
			if (condName != null)
			{
				html += getCondHtml(i, policy[condName].min, policy[condName].max, 0);
			}
			
			html += '</td><td valign="top">';
			
			html += '<p><select id="normalizer-policy-rule-' + i + '" onchange="onNormalizerRuleChange(' + i + ')">';
			html += '<option value="0"' + (policy.multiply ? ' selected' : '') + '>' + str.ruleMultiply + '</option>';
			html += '<option value="1"' + (policy.gameAv ? ' selected' : '') + '>' + str.ruleAverage + '</option>';
			html += '<option value="2"' + (policy.roundAv ? ' selected' : '') + '>' + str.ruleAvPerRound + '</option>';
			html += '<option value="3"' + (policy.byWinRate ? ' selected' : '') + '>' + str.ruleWinPerc + '</option>';
			html += '</select></p>';
			html += getRuleHtml(i);
			
			html += '</td></tr>';
			
			var condHelp = getCondHelp(i);
			if (condHelp)
				condHelp = '* ' + condHelp;
			var ruleHelp = getRuleHelp(i);
			if (ruleHelp)
				ruleHelp = '* ' + ruleHelp;
			html += '<tr class="dark" style="border-top: none;"><td>' + condHelp + '</td><td>' + ruleHelp + '</td></tr>';
		}
	}
	
    html += '</table>';
    
    $("#normalizer-editor").html(html);
    dirty(isDirty);
}

function initNormalizerEditor(data, onChangeData)
{
    _data = data;
	_onChangeData = onChangeData;
    refreshNormalizerEditor();
}
