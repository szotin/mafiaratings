var _scoring = null;
var _strings = null;
var _matters = null;
var _sections = null;
var sections = ["main", "prima_nocta", "extra", "penalty", "night1"];

function deletePolicy(sectionName, policyNum)
{
    var section = _scoring[sectionName];
    section.splice(policyNum, 1);
    refreshScoringEditor();
}

function createPolicy(sectionName)
{
    var section = _scoring[sectionName];
    section.push({ matter: 0, points: 0 });
    refreshScoringEditor();
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
    refreshScoringEditor();
}

function rolesHtml(sectionName, policyNum)
{
    var policy = _scoring[sectionName][policyNum];
    var roles = 15;
    if (typeof policy.roles != "undefined")
        roles = policy.roles;
    
    if ((roles & 15) == 0)
    {
        policy.message = _strings[4];
    }
    
    var html = '<input id="' + sectionName + '-' + policyNum + '-role-1" onclick="rolesChange(1, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 1)
    {
        html += ' checked';
    }
    html += '> ' + _strings[0] + '<br><input id="' + sectionName + '-' + policyNum + '-role-2" onclick="rolesChange(2, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 2)
    {
        html += ' checked';
    }
    html += '> ' + _strings[1] + '<br><input id="' + sectionName + '-' + policyNum + '-role-4" onclick="rolesChange(4, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 4)
    {
        html += ' checked';
    }
    html += '> ' + _strings[2] + '<br><input id="' + sectionName + '-' + policyNum + '-role-8" onclick="rolesChange(8, \'' + sectionName + '\', ' + policyNum + ')" type="checkbox"';
    if (roles & 8)
    {
        html += ' checked';
    }
    html += '> ' + _strings[3];
    return html;
}

function matterSelectChange(sectionName, policyNum, matterFlag)
{
    var policy = _scoring[sectionName][policyNum];
    var flag = $('#' + sectionName + '-' + policyNum + '-matter-' + matterFlag).val();
    policy.matter &= ~ matterFlag;
    policy.matter |= flag;
    refreshScoringEditor();
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
        html += '<tr class="light"><td colspan="4"></td></tr>';
    }
    html += '<tr class="darker"><td width="32" align="center"><button class="icon" title="' + _strings[6] + '" onclick="createPolicy(\'' + sectionName + '\')"><img src="images/create.png"></button></td><td colspan="3">' + _sections[sectionName] + '</td></tr>';
    for (var i = 0; i < section.length; ++i)
    {
        var policy = section[i];
        delete policy.message;
        html += '<tr valign="top"><td align="center"><button class="icon" title="' + _strings[7] + '" onclick="deletePolicy(\'' + sectionName + '\', ' + i + ')"><img src="images/delete.png"></button></td><td width="80">';
        var matter = policy.matter;
        if (matter <= 0)
            policy.message = _strings[5];
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
            html += '<td class="light"><font color="red">' + policy.message + '</font><br>';
        }
        else
        {
            html += '<td>';
        }
        html += '</td></tr>';
    }
    return html;
}

function refreshScoringEditor()
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
