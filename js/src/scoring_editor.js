var _scoring = null;
var _strings = null;
var _matters = null;
var _sections = null;
var _isDirty = false;

function dirty(isDirty)
{
    if (typeof isDirty == "boolean")
    {
        _isDirty = isDirty;
    }
    return _isDirty;
}

function spinnerChange(e, ui)
{
    var ids = e.target.id.split('-');
    var policy = _scoring[ids[0]][parseInt(ids[1])];
    var spinnerId = ids[2];
    var value = parseFloat(e.target.value);
    dirty(true);
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
            policy.min_difficulty = value;
            break;
        case 'maxdif':
            policy.max_difficulty = value;
            break;
        case 'minnight1':
            policy.min_night1 = value;
            break;
        case 'maxnight1':
            policy.max_night1 = value;
            break;
    }
}

function pointsPolicyChange(sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
    var value = parseInt($('#' + sectionName + '-' + policyNum + '-pp').val());
    switch (value)
    {
        case 1:
            if (typeof policy.points != "undefined")
                policy.min_points = policy.max_points = policy.points;
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
            delete policy.points;
            delete policy.min_night1;
            delete policy.max_night1;
            break;
        case 2:
            if (typeof policy.points != "undefined")
                policy.min_points = policy.max_points = policy.points;
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
            delete policy.points;
            delete policy.min_difficulty;
            delete policy.max_difficulty;
            break;
        default:
            if (typeof policy.points == "undefined")
            {
                policy.points = 1;
                if (typeof policy.max_points != "undefined")
                    policy.points = policy.max_points;
                else if (typeof policy.max_points != "undefined")
                    policy.points = policy.min_points;
            }
            delete policy.min_points;
            delete policy.max_points;
            delete policy.min_night1;
            delete policy.max_night1;
            delete policy.min_difficulty;
            delete policy.max_difficulty;
            break;
    }
    refreshScoringEditor(true);
}

function pointsPolicySelect(sectionName, policyNum, option)
{
    var html = '<select id="' + sectionName + '-' + policyNum + '-pp" onchange="pointsPolicyChange(\'' + sectionName + '\', ' + policyNum + ')">';
    html += '<option value="0"' + (option == 0 ? ' selected' : '') + '>' + _strings.statPoints + '</option>';
    html += '<option value="1"' + (option == 1 ? ' selected' : '') + '>' + _strings.difPoints + '</option>';
    html += '<option value="2"' + (option == 2 ? ' selected' : '') + '>' + _strings.shotPoints + '</option>';
    html += '</select><p>';
    return html;
}

function pointsHtml(sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
    html = '';
    var base = sectionName + '-' + policyNum;
    if (typeof policy.min_difficulty != "undefined" || typeof policy.max_difficulty != "undefined")
    {
        html += pointsPolicySelect(sectionName, policyNum, 1);
        html += _strings.minDif + ': <input id="' + base + '-mindif"> ';
        html += _strings.points + ': <input id="' + base + '-minpoints"><br>';
        html += _strings.maxDif + ': <input id="' + base + '-maxdif"> ';
        html += _strings.points + ': <input id="' + base + '-maxpoints">';
    }
    else if (typeof policy.min_night1 != "undefined" || typeof policy.max_night1 != "undefined")
    {
        html += pointsPolicySelect(sectionName, policyNum, 2);
        html += _strings.minNight1 + ': <input id="' + base + '-minnight1"> ';
        html += _strings.points + ': <input id="' + base + '-minpoints"><br>';
        html += _strings.maxNight1 + ': <input id="' + base + '-maxnight1"> ';
        html += _strings.points + ': <input id="' + base + '-maxpoints">';
    }
    else
    {
        html += pointsPolicySelect(sectionName, policyNum, 0);
        var points = 0;
        if (typeof policy.points != "undefined")
        {
            points = policy.points;
        }
        html += _strings.points + ': <input id="' + base + '-points">';
    }
    return html;
}

function optChange(sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
    if ($('#' + sectionName + '-' + policyNum + '-opt').attr('checked'))
    {
        policy.option_name = "";
    }
    else
    {
        delete policy.option_name;
        delete policy.def;
    }
    refreshScoringEditor(true);
}

function defChange(sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
    if ($('#' + sectionName + '-' + policyNum + '-def').attr('checked'))
    {
        delete policy.def;
    }
    else
    {
        policy.def = false;
    }
    refreshScoringEditor(true);
}

function optNameChange(sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
    policy.option_name = $('#' + sectionName + '-' + policyNum + '-optname').val();
    refreshScoringEditor(true);
}

function optionHtml(sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
    var html = '<input type="checkbox" id="' + sectionName + '-' + policyNum + '-opt" onclick="optChange(\'' + sectionName + '\', ' + policyNum + ')"';
    if (typeof policy.option_name == "string")
    {
        html += ' checked> ' + _strings.opt;
        html += '<br><input type="checkbox" id="' + sectionName + '-' + policyNum + '-def" onclick="defChange(\'' + sectionName + '\', ' + policyNum + ')"';
        if (typeof policy.def == "undefined" || policy.def)
        {
            html += ' checked';
        }
        html += '> ' + _strings.defOn;
        html += '<br>' + _strings.optName + ':<br><input id="' + sectionName + '-' + policyNum + '-optname" onchange="optNameChange(\'' + sectionName + '\', ' + policyNum + ')" value="' + policy.option_name + '">';
        if (policy.option_name.length == 0)
        {
            policy.message = _strings.optNameErr;
        }
    }
    else
    {
        html += '> ' + _strings.opt;
    }
    return html;
}

function deletePolicy(sectionName, policyNum)
{
    var section = _scoring[sectionName];
    section.splice(policyNum, 1);
    refreshScoringEditor(true);
}

function createPolicy(sectionName)
{
    var section = _scoring[sectionName];
    section.push({ matter: 0, points: 0 });
    refreshScoringEditor(true);
}

function rolesChange(role, sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
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
    var policy = _scoring[sectionName][policyNum];
    var roles = 15;
    if (typeof policy.roles != "undefined")
        roles = policy.roles;
    
    if ((roles & 15) == 0)
    {
        policy.message = _strings.roleErr;
    }
    
    var html = '<input id="' + sectionName + '-' + policyNum + '-role-1" onclick="rolesChange(1, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 1)
    {
        html += ' checked';
    }
    html += '> ' + _strings.civ + '<br><input id="' + sectionName + '-' + policyNum + '-role-2" onclick="rolesChange(2, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 2)
    {
        html += ' checked';
    }
    html += '> ' + _strings.sheriff + '<br><input id="' + sectionName + '-' + policyNum + '-role-4" onclick="rolesChange(4, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 4)
    {
        html += ' checked';
    }
    html += '> ' + _strings.maf + '<br><input id="' + sectionName + '-' + policyNum + '-role-8" onclick="rolesChange(8, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 8)
    {
        html += ' checked';
    }
    html += '> ' + _strings.don;
    return html;
}

function matterSelectChange(sectionName, policyNum, matterFlag)
{
    var policy = _scoring[sectionName][policyNum];
    var flag = $('#' + sectionName + '-' + policyNum + '-matter-' + matterFlag).val();
    policy.matter &= ~ matterFlag;
    policy.matter |= flag;
    refreshScoringEditor(true);
}

function matterSelectHtml(sectionName, policyNum, matterFlag)
{
    var html = '<select id="' + sectionName + '-' + policyNum + '-matter-' + matterFlag + '" onChange="matterSelectChange(\'' + sectionName + '\', ' + policyNum + ', ' + matterFlag + ')"><option value="0"></option>';
    for (var flag in _matters)
    {
        html += '<option value="' + flag + '"';
        if (flag == matterFlag)
        {
            html += ' selected';
        }
        html += '>' + _matters[flag] + '</option>';
    }
    html += '</select>';
    if (matterFlag != 0)
    {
        html += '<br>';
    }
    return html;
}

function sectionHtml(sectionName, isFirst)
{
    var section = _scoring[sectionName];
    var html = '';
    if (!isFirst)
    {
        html += '<tr class="light"><td colspan="5"></td></tr>';
    }
    html += '<tr class="darker"><td width="32" align="center"><button class="icon" title="' + _strings.policyAdd + '" onclick="createPolicy(\'' + sectionName + '\')"><img src="images/create.png"></button></td><td colspan="4">' + _sections[sectionName] + '</td></tr>';
    for (var i = 0; i < section.length; ++i)
    {
        var policy = section[i];
        delete policy.message;
        html += '<tr valign="top"><td align="center"><button class="icon" title="' + _strings.policyDel + '" onclick="deletePolicy(\'' + sectionName + '\', ' + i + ')"><img src="images/delete.png"></button></td><td width="140">';
        html += optionHtml(sectionName, i);
        html += '</td><td width="140">'
        var matter = policy.matter;
        if (matter <= 0)
            policy.message = _strings.actionErr;
        else while (matter > 0)
        {
            var oldMatter = matter;
            matter &= matter - 1;
            html += matterSelectHtml(sectionName, i, matter ^ oldMatter);
        }
        html += matterSelectHtml(sectionName, i, 0);
        html += '</td><td width="100">'
        html += rolesHtml(sectionName, i);
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
    return html;
}

function refreshScoringEditor(isDirty)
{
    var html = '<table width="100%" class="bordered">';
    var isFirst = true;
    for (var sectionName in _sections)
    {
        html += sectionHtml(sectionName, isFirst);
        isFirst = false;
    }
    html += '</table>';
    
    $("#scoring-editor").html(html);
    $("#result").html(JSON.stringify(_scoring));
    
    for (var sectionName in _sections)
    {
        section = _scoring[sectionName];
        for (var i = 0; i < section.length; ++i)
        {
            var policy = section[i];
            var base = '#' + sectionName + '-' + i;
            if (typeof policy.min_difficulty != "undefined" || typeof policy.max_difficulty != "undefined")
            {
                $(base + '-mindif').spinner({ step:0.1, min:0, max:1, change:spinnerChange}).width(40).val(policy.min_difficulty);
                $(base + '-minpoints').spinner({ step:0.1, change:spinnerChange }).width(40).val(policy.min_points);
                $(base + '-maxdif').spinner({ step:0.1, min:0, max:1, change:spinnerChange }).width(40).val(policy.max_difficulty);
                $(base + '-maxpoints').spinner({ step:0.1, change:spinnerChange }).width(40).val(policy.max_points);
            }
            else if (typeof policy.min_night1 != "undefined" || typeof policy.max_night1 != "undefined")
            {
                $(base + '-minnight1').spinner({ step:0.1, min:0, max:1, change:spinnerChange }).width(40).val(policy.min_night1);
                $(base + '-minpoints').spinner({ step:0.1, change:spinnerChange }).width(40).val(policy.min_points);
                $(base + '-maxnight1').spinner({ step:0.1, min:0, max:1, change:spinnerChange }).width(40).val(policy.max_night1);
                $(base + '-maxpoints').spinner({ step:0.1, change:spinnerChange }).width(40).val(policy.max_points);
            }
            else
            {
                $(base + '-points').spinner({ step:0.1, change:spinnerChange }).width(40).val(policy.points);
            }
        }
    }
    dirty(isDirty);
}

function initScoringEditor(scoringStr, strings, sections, matters)
{
    _strings = strings;
    _sections = sections;
    _matters = matters;
    
    try
    {
        _scoring = JSON.parse(scoringStr);
    }
    catch (error)
    {
        console.log(error);
        _scoring = JSON.parse("{}");
    }
    refreshScoringEditor();
}
