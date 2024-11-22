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
	if (typeof str == "number")
	{
		return true;
	}
	if (typeof str != "string")
	{
		return false;
	}
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

function mvpRadioChange(controlId, value)
{
    var ids = controlId.split('-');
    var policy = _data.scoring[ids[0]][parseInt(ids[1])];
	switch (value)
	{
	case 0:
		delete policy.mvp;
		break;
	case 1:
		policy.mvp = true;
		break;
	case 2:
		policy.mvp = policy.points;
		break;
	}
    refreshScoringEditor(true);
}

function mvpPointsChange(controlId)
{
	var ids = controlId.split('-');
	var policy = _data.scoring[ids[0]][parseInt(ids[1])];
	var value = $('#' + controlId).val();
	if (isNumeric(value))
	{
        policy.mvp = parseFloat(value);
	}
	else
	{
        policy.mvp = value;
	}
    dirty(true);
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
	var p = { matter: 0, points: 0 };
	switch (sectionName)
	{
	case 'legacy':
	case 'extra':
	case 'penalty':
		p.mvp = true;
		break;
	case 'counters':
		delete p.points;
		break;
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

var currentControlId = '';
function seShowLangMenu(controlId)
{
	currentControlId = controlId;
	setCurrentMenu('#lang-menu');
	var langMenu = $('#lang-menu').menu();
	var b = $('#' + controlId + '-lang');
	langMenu.show(0, function()
	{
		langMenu.position(
		{
			my: "left top",
			at: "left bottom",
			of: b
		});
		$(document).one("click", function() { setCurrentMenu(null); });
	});
}

function seAddLang(langCode)
{
	if (currentControlId)
	{
		var ids = currentControlId.split('-');
		var policy = _data.scoring[ids[0]][parseInt(ids[1])];
		policy['name_' + langCode] = '';
		refreshScoringEditor(true);
	}
}

function seRemoveLang(controlId)
{
	var ids = controlId.split('-');
	var policy = _data.scoring[ids[0]][parseInt(ids[1])];
	delete policy[ids[2]];
	refreshScoringEditor(true);
}

function seNameChange(controlId)
{
	var ids = controlId.split('-');
	var policy = _data.scoring[ids[0]][parseInt(ids[1])];
	var name = ids[2];
	policy[name] = $('#' + controlId).val();
	if (name == 'name' && policy[name] == '')
	{
		delete policy[name];
	}
	dirty(true);
}

function sectionHtml(sectionName)
{
    var section = _data.scoring[sectionName];
    var html = '<tr class="darker"><td width="32" align="center"><button class="icon" title="' + _data.strings.policyAdd + '" onclick="createPolicy(\'' + sectionName + '\')"><img src="images/create.png"></button></td><td colspan="4"><b>' + _data.sections[sectionName] + '</b></td></tr>';
	if (section)
	{
		for (var i = 0; i < section.length; ++i)
		{
			var controlId = sectionName + '-' + i;
			var policy = section[i];
			var matter = policy.matter;
			if (matter <= 0)
				policy.message = _data.strings.actionErr;
			
			delete policy.message;
			html += '<tr valign="top"><td align="center" rowspan="2"><button class="icon" title="' + _data.strings.policyDel + '" onclick="deletePolicy(\'' + sectionName + '\', ' + i + ')"><img src="images/delete.png"></button></td>';
			
			html += '<td width="300" valign="top"><p>' + _data.strings.policyName + ':<br>';
			
			html += '<table class="transp"><tr><td width="24"><img src="images/sync.png" width="20"></td><td><input id="' + controlId + '-name" oninput="seNameChange(\'' + controlId + '-name' + '\')" value="';
			if (policy.name)
			{
				html += policy.name;
			}
			html += '"></td><td><button id="' + controlId + '-lang" class="icon" onMouseEnter="seShowLangMenu(\'' +  controlId + '\')"><img src="images/create.png"></button></td></tr>';
			for (const l in _data.langs)
			{
				var code = _data.langs[l];
				var n = 'name_' + code;
				if (typeof policy[n] == 'string')
				{
					var cn = controlId + '-' + n;
					html += 
						'<tr><td><img src="images/' + code + '.png" width="20"></td>' +
						'<td><input id="' + cn + '" value="' + policy[n] + '" oninput="seNameChange(\'' + cn + '\')"></td>' +
						'<td><button class="icon" onClick="seRemoveLang(\'' + cn + '\')"><img src="images/delete.png"></button></td></tr>';
				}
			}
			html += '</table></p></td>';
			
			html += '<td width="120" valign="top"><p>' + rolesHtml(sectionName, i) + '</p></td>';
			
			if (typeof policy.message == "string")
			{
				html += '<td class="light" rowspan="2" align="center"><p><font color="red">' + policy.message + '</font></p>';
			}
			else
			{
				var mvp = 0;
				switch (typeof policy.mvp)
				{
				case 'string':
				case 'number':
					mvp = 2;
					break;
				case 'boolean':
					if (policy.mvp)
						mvp = 1;
					break;
				}
				var base = sectionName + '-' + i;
				var controlId = base + '-points';
				var mvpId = base + '-mvp';
				html += '<td rowspan="2" align="center"><table class="transp" width="100%">';
				html += '<tr><td><p>' + _data.strings.points + ':</p></td><td><p><input id="' + controlId + '" style="width: 350px;" oninput="pointsChange(\'' + controlId + '\')"><button class="small_icon" onclick="mr.functionHelp()"><img src="images/function.png" width="12"></button></p></td></tr>';
				html += '<tr><td colspan="2">';
				html += '<p><input type="radio" name="' + mvpId + '"' + (mvp == 0 ? ' checked' : '') + ' onclick="mvpRadioChange(\'' + mvpId + '\', 0)""> ' + _data.strings.noMvp;
				html += '<br><input type="radio" name="' + mvpId + '"' + (mvp == 1 ? ' checked' : '') + ' onclick="mvpRadioChange(\'' + mvpId + '\', 1)"> ' + _data.strings.yesMvp;
				html += '<br><input type="radio" name="' + mvpId + '"' + (mvp == 2 ? ' checked' : '') + ' onclick="mvpRadioChange(\'' + mvpId + '\', 2)"> ' + _data.strings.customMvp;
				html += '</p></td></tr>';
				if (mvp == 2)
				{
					html += '<tr><td><p>' + _data.strings.mvpPoints + ':</p></td><td><p><input id="' + mvpId + '" style="width: 350px;" oninput="mvpPointsChange(\'' + mvpId + '\')"><button class="small_icon" onclick="mr.functionHelp()"><img src="images/function.png" width="12"></button></p></td></tr>';
				}
				html += '</table>';
			}
			html += '</td></tr>';
			
			html += '<tr><td colspan="2"><p>'
			var matter = policy.matter;
			while (matter > 0)
			{
				var oldMatter = matter;
				matter &= matter - 1;
				html += matterSelectHtml(sectionName, i, matter ^ oldMatter);
			}
			html += matterSelectHtml(sectionName, i, 0);
			html += '</p></td></tr>'
		}
	}
    html += '<tr class="light"><td colspan="5"></td></tr>';
    return html;
}

function countersHtml()
{
    var counters = _data.scoring.counters;
    var html = '<tr class="darker"><td width="32" align="center"><button class="icon" title="' + _data.strings.counterAdd + '" onclick="createPolicy(\'counters\')"><img src="images/create.png"></button></td><td colspan="4"><b>' + _data.strings.counters + '</b></td></tr>';
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
    var html = '<tr class="darker"><td colspan="6"><b>' + _data.strings.sorting + '</b></td></tr>';
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
		html += '<tr><td colspan="5">' + _data.strings.name + ': <input id="scoring-name" value="' + _data.name + '" oninput="onScoringNameChange()"> ' + _data.strings.version + ': ' + _data.version + '</td></tr>';
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
				if (section[i].mvp)
				{
					$('#' + sectionName + '-' + i + '-mvp').val(section[i].mvp);
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
