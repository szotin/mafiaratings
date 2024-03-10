var _data = null;
var _isDirty = false;
var _onChangeData = null;

function isScoringDataCorrect()
{
    for (var sectionName in _data.sections)
	{
		var section = _data.scoring[sectionName];
		if (!section)
			continue;
		for (var i = 0; i < section.length; ++i)
		{
			var policy = section[i];
			if (typeof policy.message == "string")
				return false;
		}
	}
	return true;
}

function dirty(isDirty)
{
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

function spinnerChange(controlId)
{
    var ids = controlId.split('-');
    var policy = _data.scoring[ids[0]][parseInt(ids[1])];
    var spinnerId = ids[2];
    var value = parseFloat($('#' + controlId).val());
    switch (spinnerId)
    {
        case 'points':
            policy.points = value;
            break;
        case 'minpoints':
            policy.min_points = value;
            break;
        case 'maxpoints':
            policy.max_points = value;
            break;
        case 'mindif':
            policy.min_difficulty = value / 100;
            break;
        case 'maxdif':
            policy.max_difficulty = value / 100;
            break;
        case 'minnight1':
			policy.min_night1 = value;
			if (policy.percent)
				policy.min_night1 /= 100;
            break;
        case 'maxnight1':
			policy.max_night1 = value;
			if (policy.percent)
				policy.max_night1 /= 100;
            break;
		case 'fiim_night':
            policy.fiim_first_night_score = value;
            break;
		case 'percent':
            policy.percent = value / 100;
            break;
		case 'weight':
            policy.extra_points_weight = value;
            break;
    }
    dirty(true);
}

function checkboxChange(controlId)
{
    var ids = controlId.split('-');
    var policy = _data.scoring[ids[0]][parseInt(ids[1])];
    var checkboxId = ids[2];
    var value = $('#' + controlId).attr('checked') ? true : false;
    switch (checkboxId)
    {
        case 'percent':
			if (policy.percent != value)
			{
				if (value)
				{
					policy.min_night1 /= 100;
					policy.max_night1 /= 100;
				}
				else
				{
					policy.min_night1 *= 100;
					policy.max_night1 *= 100;
				}
				policy.percent = value;
			}
            break;
        case 'lostonly':
            policy.lost_only = value;
            break;
    }
    dirty(true);
}

function pointsPolicyChange(sectionName, policyNum)
{
    var policy = _data.scoring[sectionName][policyNum];
    var value = parseInt($('#' + sectionName + '-' + policyNum + '-pp').val());
    switch (value)
    {
        case 1:
            if (typeof policy.points != "undefined")
                policy.min_points = policy.max_points = policy.points;
			else if (typeof policy.fiim_first_night_score != "undefined")
                policy.min_points = policy.max_points = policy.fiim_first_night_score;
            if (typeof policy.min_difficulty == "undefined")
            {
                policy.min_difficulty = 0;
                if (typeof policy.min_night1 != "undefined")
                    policy.min_difficulty = policy.min_night1;
            }
            if (typeof policy.max_difficulty == "undefined")
            {
                policy.max_difficulty = 1;
                if (typeof policy.max_night1 != "undefined")
                    policy.max_difficulty = policy.max_night1;
            }
			delete policy.fiim_first_night_score;
            delete policy.points;
            delete policy.min_night1;
            delete policy.max_night1;
			delete policy.lost_only;
			delete policy.percent;
			delete policy.extra_points_weight;
            break;
        case 2:
            if (typeof policy.points != "undefined")
                policy.min_points = policy.max_points = policy.points;
			else if (typeof policy.fiim_first_night_score != "undefined")
                policy.min_points = policy.max_points = policy.fiim_first_night_score;
            if (typeof policy.min_night1 == "undefined")
            {
                policy.min_night1 = 0;
                if (typeof policy.min_difficulty != "undefined")
                    policy.min_night1 = policy.min_difficulty;
            }
            if (typeof policy.max_night1 == "undefined")
            {
                policy.max_night1 = 1;
                if (typeof policy.max_difficulty != "undefined")
                    policy.max_night1 = policy.max_difficulty;
            }
			policy.lost_only = false;
			policy.percent = true;
			delete policy.fiim_first_night_score;
            delete policy.points;
            delete policy.min_difficulty;
            delete policy.max_difficulty;
			delete policy.extra_points_weight;
            break;
        case 3:
			if (typeof policy.fiim_first_night_score == "undefined")
			{
				policy.fiim_first_night_score = 0.5;
			}
			policy.percent = 0.4;
            delete policy.points;
            delete policy.min_points;
            delete policy.max_points;
            delete policy.min_difficulty;
            delete policy.max_difficulty;
            delete policy.min_night1;
            delete policy.max_night1;
			delete policy.lost_only;
			delete policy.extra_points_weight;
            break;
		case 4:
			if (typeof policy.extra_points_weight == "undefined")
			{
				policy.extra_points_weight = 1;
			}
            delete policy.points;
			delete policy.fiim_first_night_score;
            delete policy.min_points;
            delete policy.max_points;
            delete policy.min_night1;
            delete policy.max_night1;
            delete policy.min_difficulty;
            delete policy.max_difficulty;
			delete policy.lost_only;
			delete policy.percent;
            break;
        default:
            if (typeof policy.points == "undefined")
            {
                policy.points = 1;
                if (typeof policy.max_points != "undefined")
                    policy.points = policy.max_points;
                else if (typeof policy.max_points != "undefined")
                    policy.points = policy.min_points;
				else if (typeof policy.fiim_first_night_score != "undefined")
					policy.points = policy.fiim_first_night_score;
            }
			delete policy.fiim_first_night_score;
            delete policy.min_points;
            delete policy.max_points;
            delete policy.min_night1;
            delete policy.max_night1;
            delete policy.min_difficulty;
            delete policy.max_difficulty;
			delete policy.lost_only;
			delete policy.percent;
			delete policy.extra_points_weight;
            break;
    }
    refreshScoringEditor(true);
}

function pointsPolicySelect(sectionName, policyNum, option)
{
    var html = '<select id="' + sectionName + '-' + policyNum + '-pp" onchange="pointsPolicyChange(\'' + sectionName + '\', ' + policyNum + ')">';
    html += '<option value="0"' + (option == 0 ? ' selected' : '') + '>' + _data.strings.statPoints + '</option>';
    html += '<option value="1"' + (option == 1 ? ' selected' : '') + '>' + _data.strings.difPoints + '</option>';
    html += '<option value="2"' + (option == 2 ? ' selected' : '') + '>' + _data.strings.shotPoints + '</option>';
    html += '<option value="3"' + (option == 3 ? ' selected' : '') + '>' + _data.strings.shotPointsFiim + '</option>';
    html += '<option value="4"' + (option == 4 ? ' selected' : '') + '>' + _data.strings.bonusPoints + '</option>';
    html += '</select><p>';
    return html;
}

function pointsHtml(sectionName, policyNum)
{
    var policy = _data.scoring[sectionName][policyNum];
    html = '';
    var base = sectionName + '-' + policyNum;
    if (typeof policy.min_difficulty != "undefined" || typeof policy.max_difficulty != "undefined")
    {
        html += pointsPolicySelect(sectionName, policyNum, 1);
        html += _data.strings.minDif + ': <input type="number" style="width: 45px;" id="' + base + '-mindif" step="1" min"0" max"100" onChange="spinnerChange(\'' + base + '-mindif\')"> ';
        html += _data.strings.points + ': <input type="number" style="width: 45px;" id="' + base + '-minpoints" step="0.1" onChange="spinnerChange(\'' + base + '-minpoints\')"><br>';
        html += _data.strings.maxDif + ': <input type="number" style="width: 45px;" id="' + base + '-maxdif" step="1" min="0" max="100" onChange="spinnerChange(\'' + base + '-maxdif\')"> ';
        html += _data.strings.points + ': <input type="number" style="width: 45px;" id="' + base + '-maxpoints" step="0.1" onChange="spinnerChange(\'' + base + '-maxpoints\')">';
    }
    else if (typeof policy.min_night1 != "undefined" || typeof policy.max_night1 != "undefined")
    {
        html += pointsPolicySelect(sectionName, policyNum, 2);
        html += '<p><input type="checkbox" id="' + base + '-lostonly" onChange="checkboxChange(\'' + base + '-lostonly\')"> ' + _data.strings.lostOnly + '<br>';
        html += '<input type="checkbox" id="' + base + '-percent" onChange="checkboxChange(\'' + base + '-percent\')"> ' + _data.strings.dependingOnPercent + '</p>';
        html += _data.strings.minNight1 + ': <input type="number" style="width: 45px;" id="' + base + '-minnight1" step="1" min="0" max="100" onChange="spinnerChange(\'' + base + '-minnight1\')"> ';
        html += _data.strings.points + ': <input type="number" style="width: 45px;" id="' + base + '-minpoints" step="0.1" onChange="spinnerChange(\'' + base + '-minpoints\')"><br>';
        html += _data.strings.maxNight1 + ': <input type="number" style="width: 45px;" id="' + base + '-maxnight1" step="1" min="0" max="100" onChange="spinnerChange(\'' + base + '-maxnight1\')"> ';
        html += _data.strings.points + ': <input type="number" style="width: 45px;" id="' + base + '-maxpoints" step="0.1" onChange="spinnerChange(\'' + base + '-maxpoints\')">';
    }
    else if (typeof policy.fiim_first_night_score != "undefined")
    {
        html += pointsPolicySelect(sectionName, policyNum, 3);
        html += _data.strings.percent + ': <input type="number" style="width: 45px;" id="' + base + '-percent" step="1" min="0" max="100" onChange="spinnerChange(\'' + base + '-percent\')"><br>';
        html += _data.strings.points + ': <input type="number" style="width: 45px;" id="' + base + '-fiim_night" step="0.1" onChange="spinnerChange(\'' + base + '-fiim_night\')">';
    }
	else if (typeof policy.extra_points_weight != "undefined")
	{
        html += pointsPolicySelect(sectionName, policyNum, 4);
        html += _data.strings.extraPointsWeight + ': <input type="number" style="width: 45px;" id="' + base + '-weight" step="0.1" onChange="spinnerChange(\'' + base + '-weight\')">';
	}
    else
    {
        html += pointsPolicySelect(sectionName, policyNum, 0);
        html += _data.strings.points + ': <input type="number" style="width: 45px;" id="' + base + '-points" step="0.1" onChange="spinnerChange(\'' + base + '-points\')">';
    }
    return html;
}

function deletePolicy(sectionName, policyNum)
{
    var section = _data.scoring[sectionName];
    section.splice(policyNum, 1);
	if (section.length == 0)
	{
		delete _data.scoring[sectionName];
	}
    refreshScoringEditor(true);
}

function createPolicy(sectionName)
{
    var section = _data.scoring[sectionName];
	if (!section)
	{
		section = _data.scoring[sectionName] = [];
	}
    section.push({ matter: 0, points: 1 });
    refreshScoringEditor(true);
}

function rolesChange(role, sectionName, policyNum)
{
    var policy = _data.scoring[sectionName][policyNum];
    if (typeof policy.roles == "undefined")
        policy.roles = 15;

    if ($('#' + sectionName + '-' + policyNum + '-role-' + role).attr('checked'))
        policy.roles |= role;
    else
        policy.roles &= ~role;
    if ((policy.roles & 15) == 15)
        delete policy.roles;
    refreshScoringEditor(true);
}

function rolesHtml(sectionName, policyNum)
{
    var policy = _data.scoring[sectionName][policyNum];
    var roles = 15;
    if (typeof policy.roles != "undefined")
        roles = policy.roles;
    
    if ((roles & 15) == 0)
    {
        policy.message = _data.strings.roleErr;
    }
    
    var html = '<input id="' + sectionName + '-' + policyNum + '-role-1" onclick="rolesChange(1, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 1)
    {
        html += ' checked';
    }
    html += '> ' + _data.strings.civ + '<br><input id="' + sectionName + '-' + policyNum + '-role-2" onclick="rolesChange(2, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 2)
    {
        html += ' checked';
    }
    html += '> ' + _data.strings.sheriff + '<br><input id="' + sectionName + '-' + policyNum + '-role-4" onclick="rolesChange(4, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 4)
    {
        html += ' checked';
    }
    html += '> ' + _data.strings.maf + '<br><input id="' + sectionName + '-' + policyNum + '-role-8" onclick="rolesChange(8, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 8)
    {
        html += ' checked';
    }
    html += '> ' + _data.strings.don;
    return html;
}

function matterSelectChange(sectionName, policyNum, matterFlag)
{
    var policy = _data.scoring[sectionName][policyNum];
    var flag = $('#' + sectionName + '-' + policyNum + '-matter-' + matterFlag).val();
    policy.matter &= ~ matterFlag;
    policy.matter |= flag;
    refreshScoringEditor(true);
}

function matterSelectHtml(sectionName, policyNum, matterFlag)
{
    var html = '<select id="' + sectionName + '-' + policyNum + '-matter-' + matterFlag + '" onChange="matterSelectChange(\'' + sectionName + '\', ' + policyNum + ', ' + matterFlag + ')"><option value="0"></option>';
    for (var flag in _data.matters)
    {
        html += '<option value="' + flag + '"';
        if (flag == matterFlag)
        {
            html += ' selected';
        }
        html += '>' + _data.matters[flag] + '</option>';
    }
    html += '</select>';
    if (matterFlag != 0)
    {
        html += '<br>';
    }
    return html;
}

function sectionHtml(sectionName)
{
    var section = _data.scoring[sectionName];
    var html = '<tr class="darker"><td width="32" align="center"><button class="icon" title="' + _data.strings.policyAdd + '" onclick="createPolicy(\'' + sectionName + '\')"><img src="images/create.png"></button></td><td colspan="3">' + _data.sections[sectionName] + '</td></tr>';
	if (section)
	{
		for (var i = 0; i < section.length; ++i)
		{
			var policy = section[i];
			delete policy.message;
			html += '<tr valign="top"><td align="center"><button class="icon" title="' + _data.strings.policyDel + '" onclick="deletePolicy(\'' + sectionName + '\', ' + i + ')"><img src="images/delete.png"></button></td>';
			html += '<td width="100">'
			html += rolesHtml(sectionName, i);
			html += '</td><td width="140">'
			var matter = policy.matter;
			if (matter <= 0)
				policy.message = _data.strings.actionErr;
			else while (matter > 0)
			{
				var oldMatter = matter;
				matter &= matter - 1;
				html += matterSelectHtml(sectionName, i, matter ^ oldMatter);
			}
			html += matterSelectHtml(sectionName, i, 0);
			html += '</td>'
			if (typeof policy.message == "string")
			{
				html += '<td class="light" align="center"><p><font color="red">' + policy.message + '</font></p>';
			}
			else
			{
				html += '<td>';
				html += pointsHtml(sectionName, i);
			}
			html += '</td></tr>';
		}
	}
    html += '<tr class="light"><td colspan="5"></td></tr>';
    return html;
}

function sortingChange()
{
    var sorting = '';
    var layer = 0;
    while (true)
    {
        var d = $('#sorting-desc-' + layer).val();
        if (typeof d == "undefined")
        {
            break;
        }
        
        var substr = '';
        var index = 0;
        while (true)
        {
            var c = $('#sorting-' + layer + '-' + index).val();
            if (typeof c == "undefined")
            {
                break;
            }
            if (typeof _data.sorting[c] == 'string')
            {
                substr = substr.concat(c);
            }
            ++index;
        }
        
        if (substr.length > 0)
        {
            if (d == 1)
            {
                sorting = sorting.concat('-');
            }
            
            if (substr.length > 1)
            {
                sorting = sorting.concat('(' + substr + ')');
            }
            else
            {
                sorting = sorting.concat(substr);
            }
        }
        ++layer;
    }
    
    if (sorting == '(epg)wsk')
    {
        delete _data.scoring.sorting;
    }
    else
    {
        _data.scoring.sorting = sorting;
    }
    refreshScoringEditor(true);
}

function sortingSelect(letter, layer, index)
{
    var html = ' <select id="sorting-' + layer + '-' + index + '" onchange="sortingChange()"><option value="0"></option>';
    for (var l in _data.sorting)
    {
        html += '<option value="' + l + '"' + (letter == l ? ' selected' : '') + '>' + _data.sorting[l] + '</option>';
    }
    html += '</select>';
    return html;
}

function sortingSectionStart(layer, desc)
{
    return '<tr><td colspan="5">' + _data.strings.theOne + ' <select id="sorting-desc-' + layer + '" onchange="sortingChange()">' +
        '<option value="0"' + (desc ? '' : ' selected') + '>' + _data.strings.higher + '</option>' +
        '<option value="1"' + (desc ? ' selected' : '') + '>' + _data.strings.lower + '</option>' +
        '</select> ' + _data.strings.sumOf + ':';
}

function sortingSectionEnd(layer, index)
{
    return sortingSelect('0', layer, index) + '</td></tr>';
}

function sortingHtml()
{
    var html = '<tr class="darker"><td colspan="5">' + _data.strings.sorting + '</td></tr>';
    var sorting = '(epg)wsk';
    if (typeof _data.scoring.sorting == "string")
    {
        sorting = _data.scoring.sorting;
    }
    
    var inBrackets = false;
    var desc = false;
    var layer = 0;
    var index = 0;
    for (var i = 0; i < sorting.length; ++i)
    {
        var c = sorting.charAt(i);
        if (inBrackets)
        {
            if (c == ')')
            {
                html += sortingSectionEnd(layer++, index);
                desc = inBrackets = false;
            }
            else
            {
                html += sortingSelect(c, layer, index++);
            }
        }
        else if (c == '-')
        {
            desc = true;
        }
        else
        {
            html += sortingSectionStart(layer, desc);
            if (c == '(')
            {
                inBrackets = true;
                index = 0;
            }
            else
            {
                html += sortingSelect(c, layer, 0);
                html += sortingSectionEnd(layer++, 1);
                desc = false;
            }
        }
    }
    html += sortingSectionStart(layer, false);
    html += sortingSectionEnd(layer, 0);
    return html;
}

function onScoringNameChange()
{
	_data.name = $("#scoring-name").val();
	dirty(true);
}

function setScoringVersion(version)
{
	if (_data.version != version)
	{
		_data.version = version;
		refreshScoringEditor();
	}
}

function refreshScoringEditor(isDirty)
{
    var html = '<table width="100%" class="bordered dark">';
	if (_data.name)
	{
		html += '<tr><td colspan="4">' + _data.strings.name + ': <input id="scoring-name" value="' + _data.name + '" onChange="onScoringNameChange()"> ' + _data.strings.version + ': ' + _data.version + '</td></tr>';
	}
    for (var sectionName in _data.sections)
    {
        html += sectionHtml(sectionName);
    }
    html += sortingHtml();
    html += '</table>';
    
    $("#scoring-editor").html(html);
    
    for (var sectionName in _data.sections)
    {
        section = _data.scoring[sectionName];
		if (section)
		{
			for (var i = 0; i < section.length; ++i)
			{
				var policy = section[i];
				var base = '#' + sectionName + '-' + i;
				if (typeof policy.min_difficulty != "undefined" || typeof policy.max_difficulty != "undefined")
				{
					$(base + '-mindif').val(policy.min_difficulty * 100);
					$(base + '-minpoints').val(policy.min_points);
					$(base + '-maxdif').val(policy.max_difficulty * 100);
					$(base + '-maxpoints').val(policy.max_points);
				}
				else if (typeof policy.min_night1 != "undefined" || typeof policy.max_night1 != "undefined")
				{
					$(base + '-lostonly').prop('checked', policy.lost_only);
					$(base + '-percent').prop('checked', policy.percent);
					$(base + '-minnight1').val(policy.percent ? policy.min_night1 * 100 : policy.min_night1);
					$(base + '-minpoints').val(policy.min_points);
					$(base + '-maxnight1').val(policy.percent ? policy.max_night1 * 100 : policy.max_night1);
					$(base + '-maxpoints').val(policy.max_points);
				}
				else if (typeof policy.fiim_first_night_score != "undefined")
				{
					$(base + '-percent').val(policy.percent * 100);
					$(base + '-fiim_night').val(policy.fiim_first_night_score);
				}
				else if (typeof policy.extra_points_weight != "undefined")
				{
					$(base + '-weight').val(policy.extra_points_weight);
				}
				else
				{
					$(base + '-points').val(policy.points);
				}
			}
		}
    }
    dirty(isDirty);
}

function initScoringEditor(data, onChangeData)
{
    _data = data;
	_onChangeData = onChangeData;
    refreshScoringEditor();
}
