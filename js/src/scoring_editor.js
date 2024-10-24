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

function isNumeric(str)
{
	if (str.length <= 0)
	{
		return false;
	}
	
	var i = 0;
	var dotCount = 0;
	var c = str.charCodeAt(i);
	if (c == 43 || c == 45) // '+' and '-'
	{
		++i;
	}
	for (; i < str.length; ++i)
	{
		c = str.charCodeAt(i);
		if ((c < 48 || c > 57) && (c != 46 || ++dotCount > 1)) // '0', '9' and '.'
		{
			return false;
		}
	}
	return true;
}

function pointsChange(controlId)
{
    var ids = controlId.split('-');
    var policy = _data.scoring[ids[0]][parseInt(ids[1])];
	var value = $('#' + controlId).val();
	if (isNumeric(value))
	{
        policy.points = parseFloat(value);
	}
	else
	{
        policy.points = value;
	}
    dirty(true);
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
	var p = { matter: 0 };
	if (sectionName != 'counters')
	{
		p.points = 0;
	}
    section.push(p);
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
				var controlId = sectionName + '-' + i + '-points';
				html += '<td valign="middle" align="center">' + _data.strings.points + ': <input id="' + controlId + '" style="width: 300px;" onChange="pointsChange(\'' + controlId + '\')">';
			}
			html += '</td></tr>';
		}
	}
    html += '<tr class="light"><td colspan="5"></td></tr>';
    return html;
}

function countersHtml()
{
    var counters = _data.scoring.counters;
    var html = '<tr class="darker"><td width="32" align="center"><button class="icon" title="' + _data.strings.counterAdd + '" onclick="createPolicy(\'counters\')"><img src="images/create.png"></button></td><td colspan="3">' + _data.strings.counters + '</td></tr>';
	if (counters)
	{
		for (var i = 0; i < counters.length; ++i)
		{
			var counter = counters[i];
			html += '<tr valign="top"><td align="center"><button class="icon" title="' + _data.strings.counterDel + '" onclick="deletePolicy(\'counters\',' + i + ')"><img src="images/delete.png"></button></td>';
			html += '<td width="100">';
			html += rolesHtml('counters', i);
			html += '</td><td width="140" colspan="2">';
			var matter = counter.matter;
			if (matter <= 0)
			{
				html += _data.strings.actionErr + '<br><br>';
			}
			else while (matter > 0)
			{
				var oldMatter = matter;
				matter &= matter - 1;
				html += matterSelectHtml('counters', i, matter ^ oldMatter);
			}
			html += matterSelectHtml('counters', i, 0);
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
    html += countersHtml();
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
				$('#' + sectionName + '-' + i + '-points').val(section[i].points ? section[i].points : 0);
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
