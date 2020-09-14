var _data = null;
var _isDirty = false;
var _onChangeData = null;

function isNormalizerDataCorrect()
{
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

function refreshNormalizerEditor(isDirty)
{
	var norm = _data.normalizer;
	var str = _data.strings;
	
    var html = '<table width="100%" class="bordered light">';
	if (_data.name)
	{
		html += '<tr class="dark"><td colspan="4">' + str.name + ': <input id="normalizer-name" value="' + _data.name + '" onChange="onNormalizerNameChange()"> ' + str.version + ': ' + _data.version + '</td></tr>';
	}
	
	html += '<tr><td colspan="4">' + str.policy + ': <select id="normalizer-policy" onchange="onNormalizerPolicyChange()">';
	html += '<option value="0"' + (!norm.policy ? ' selected' : '') + '>' + str.policy_none + '</option>';
	html += '<option value="1"' + (norm.policy == 1 ? ' selected' : '') + '>' + str.policy_average + '</option>';
	html += '<option value="2"' + (norm.policy == 2 ? ' selected' : '') + '>' + str.policy_by_winrate + '</option>';
	html += '<option value="3"' + (norm.policy == 3 ? ' selected' : '') + '>' + str.policy_by_round + '</option>';
	html += '</td></tr>';
	
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
