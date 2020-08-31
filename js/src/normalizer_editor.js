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

function refreshNormalizerEditor(isDirty)
{
    var html = '<table width="100%" class="bordered dark">';
	if (_data.name)
	{
		html += '<tr><td colspan="4">' + _data.strings.name + ': <input id="normalizer-name" value="' + _data.name + '" onChange="onNormalizerNameChange()"> ' + _data.strings.version + ': ' + _data.version + '</td></tr>';
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
