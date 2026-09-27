// Editor for a series gaining system - the json stored in gaining_versions.gaining.
//
// The format is whatever include/gaining.php reads, and every part of it is editable here:
//   points        the formula giving a player the points for one tournament of the series
//   seriesPoints  the formula used instead of points when what is being scored is a subseries
//   maxTournaments  how many of a player's best tournaments count; absent means all of them
//   globals       name -> expression, evaluated once per competition, in order
//   vars          name -> expression, re-evaluated for every player, in order
//   table         numbers looked up by table(...), nested to any depth
//
// Expressions are not parsed here. The preview under the editor evaluates them through the
// real Evaluator on the server, so it doubles as the syntax check.

var _data = null;
var _isDirty = false;
var _onChangeData = null;
var _onPreview = null;

// globals and vars are ordered: a global may use one defined before it, so the editor keeps
// them as arrays and writes the objects back in that order.
var _globals = [];
var _vars = [];

// While the limit is off there is no field holding the number, so the last one is remembered here
// and offered again when it is turned back on.
var _lastMaxTournaments = 10;

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
	str = str.trim();
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
		if (i >= str.length)
		{
			return false;
		}
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

// The button that opens the help on the names a formula may use, next to every field that holds
// a formula. _data.functions is the list the hosting page wants offered.
// button.small_icon sets margin to 0 and no vertical alignment, so on its own the button ends up
// glued to the field and sitting on its baseline - next to a three row textarea that reads as the
// bottom. The field it accompanies is centered the same way, which centers the two on each other.
function geHelpButton()
{
	return '<button class="small_icon" style="margin-left: 8px; vertical-align: middle;"' +
		' onclick="mr.functionHelp(\'' + _data.functions + '\')"><img src="images/function.png" width="12"></button>';
}

// A button carrying its icon and its wording together, so that the whole thing is what you click
// rather than a bare icon with loose text beside it. The icons are 24 square, which towers over
// the text of a button, so they are asked for at about the height of a line instead.
function geIconButton(onclick, icon, text)
{
	return '<button onclick="' + onclick + '">' +
		'<img src="images/' + icon + '.png" border="0" width="14" style="vertical-align: middle;"> ' +
		text + '</button>';
}

// An icon control at the size the delete, up and down controls of a row already are, so that one
// standing at the head of their column lines up with them instead of looking like a different kind
// of thing. Same shape as those too - an image in a link, which is what this project uses for them.
function geRowButton(onclick, icon, title)
{
	return '<a href="javascript:' + onclick + '" title="' + geEscape(title) + '">' +
		'<img src="images/' + icon + '.png" border="0" style="vertical-align: middle;"></a>';
}

// Each part of the editor is its own table, so that they stand apart instead of running together
// into one grid where it is not clear what belongs to what.
function geSectionStart(cls)
{
	return '<table class="bordered ' + (cls ? cls : 'light') + '" width="100%" style="margin-bottom: 14px;">';
}

function geSectionEnd()
{
	return '</table>';
}

function geEscape(s)
{
	if (typeof s != "string")
	{
		s = (typeof s == "undefined" || s === null) ? '' : '' + s;
	}
	return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// A gaining system without a points formula gains nobody anything, and a table row that is not
// a list of numbers cannot be stored - both block saving.
function isGainingDataCorrect()
{
	if (typeof _data.gaining.points != "string" || _data.gaining.points.trim().length <= 0)
	{
		return false;
	}
	for (var i = 0; i < _globals.length; ++i)
	{
		if (_globals[i].name.trim().length <= 0)
		{
			return false;
		}
	}
	for (var i = 0; i < _vars.length; ++i)
	{
		if (_vars[i].name.trim().length <= 0)
		{
			return false;
		}
	}
	return geLeavesCorrect(_data.gaining.table);
}

function geLeavesCorrect(node)
{
	if (!Array.isArray(node))
	{
		return true;
	}
	if (geIsLeaf(node))
	{
		for (var i = 0; i < node.length; ++i)
		{
			if (!isNumeric(node[i]))
			{
				return false;
			}
		}
		return true;
	}
	for (var i = 0; i < node.length; ++i)
	{
		if (!geLeavesCorrect(node[i]))
		{
			return false;
		}
	}
	return true;
}

//--------------------------------------------------------------------------------------
// name, version, formulas
//--------------------------------------------------------------------------------------
function geNameChange()
{
	_data.name = $("#gaining-name").val();
	dirty(true);
}

function gePointsChange(which)
{
	_data.gaining[which] = $("#gaining-" + which).val();
	dirty(true);
}

function geSeriesPointsToggle()
{
	if ($("#gaining-has-series-points").attr('checked'))
	{
		_data.gaining.seriesPoints = _data.gaining.points;
	}
	else
	{
		delete _data.gaining.seriesPoints;
	}
	refreshGainingEditor(true);
}

//--------------------------------------------------------------------------------------
// maxTournaments
//--------------------------------------------------------------------------------------
function geMaxTournamentsToggle()
{
	if ($("#gaining-limit-tournaments").attr('checked'))
	{
		_data.gaining.maxTournaments = _lastMaxTournaments;
	}
	else
	{
		// Absent, not zero: complete_competitions.php reads a missing key as "count them all",
		// while an explicit zero is read as "count nothing" on the player page.
		delete _data.gaining.maxTournaments;
	}
	refreshGainingEditor(true);
}

function geMaxTournamentsChange()
{
	var value = parseInt($("#gaining-max-tournaments").val());
	if (isNaN(value) || value < 1)
	{
		value = 1;
	}
	_data.gaining.maxTournaments = _lastMaxTournaments = value;
	dirty(true);
}

//--------------------------------------------------------------------------------------
// globals and vars
//--------------------------------------------------------------------------------------
function geList(which)
{
	return which == 'globals' ? _globals : _vars;
}

function geStoreList(which)
{
	var list = geList(which);
	if (list.length == 0)
	{
		delete _data.gaining[which];
		return;
	}
	var obj = {};
	for (var i = 0; i < list.length; ++i)
	{
		obj[list[i].name] = list[i].expr;
	}
	_data.gaining[which] = obj;
}

function geVarNameChange(which, index)
{
	geList(which)[index].name = $('#' + which + '-name-' + index).val();
	geStoreList(which);
	dirty(true);
}

function geVarExprChange(which, index)
{
	geList(which)[index].expr = $('#' + which + '-expr-' + index).val();
	geStoreList(which);
	dirty(true);
}

function geVarAdd(which)
{
	geList(which).push({ name: '', expr: '0' });
	geStoreList(which);
	refreshGainingEditor(true);
}

function geVarDelete(which, index)
{
	geList(which).splice(index, 1);
	geStoreList(which);
	refreshGainingEditor(true);
}

function geVarMove(which, index, delta)
{
	var list = geList(which);
	var to = index + delta;
	if (to < 0 || to >= list.length)
	{
		return;
	}
	var tmp = list[index];
	list[index] = list[to];
	list[to] = tmp;
	geStoreList(which);
	refreshGainingEditor(true);
}

function geVarsHtml(which, title, help)
{
	var list = geList(which);
	// The heading is the first row of the list itself, so that the plus that adds a value sits in
	// the same narrow column as the buttons of every row below it, at the size they are.
	//
	// Bordered rather than transparent: table.transp carries no padding at all, which left the
	// buttons of neighbouring rows touching. This both spaces them and draws the line between the
	// rows, the same way the table editor below does.
	var html = geSectionStart();
	html += '<tr class="darker"><td width="1" valign="middle" nowrap>' +
		geRowButton('geVarAdd(\'' + which + '\')', 'create', _data.strings.varAdd) +
		'</td><td colspan="2"><b>' + title + '</b> &mdash; ' + help + '</td></tr>';
	for (var i = 0; i < list.length; ++i)
	{
		var v = list[i];
		// width="1" shrinks the cell to its minimum, so without nowrap the three buttons stack up
		// one under another instead of sitting in a row.
		html += '<tr><td width="1" valign="middle" nowrap>';
		html += '<a href="javascript:geVarDelete(\'' + which + '\', ' + i + ')" title="' + _data.strings.varDel + '"><img src="images/delete.png" border="0" style="vertical-align: middle;"></a>';
		html += '<a href="javascript:geVarMove(\'' + which + '\', ' + i + ', -1)" title="' + _data.strings.moveUp + '"><img src="images/up.png" border="0" style="vertical-align: middle;"></a>';
		html += '<a href="javascript:geVarMove(\'' + which + '\', ' + i + ', 1)" title="' + _data.strings.moveDown + '"><img src="images/down.png" border="0" style="vertical-align: middle;"></a>';
		html += '</td>';
		html += '<td width="160" valign="middle"><input id="' + which + '-name-' + i + '" style="width: 150px; vertical-align: middle;" value="' + geEscape(v.name) + '"' +
			' oninput="geVarNameChange(\'' + which + '\', ' + i + ')" onchange="refreshGainingPreview()"></td>';
		html += '<td valign="middle">=&nbsp;<input id="' + which + '-expr-' + i + '" style="width: 90%; vertical-align: middle;" value="' + geEscape(v.expr) + '"' +
			' oninput="geVarExprChange(\'' + which + '\', ' + i + ')" onchange="refreshGainingPreview()">' + geHelpButton() + '</td>';
		html += '</tr>';
	}
	html += geSectionEnd();
	return html;
}

//--------------------------------------------------------------------------------------
// table
//--------------------------------------------------------------------------------------
// A node is a leaf when it holds no arrays - those are the numbers table() finally lands on.
// Depth is read off the data rather than fixed, so the same code covers the one-dimensional
// tables and the four-dimensional ones alike, including a root whose entries differ in depth.
function geIsLeaf(node)
{
	if (!Array.isArray(node))
	{
		return true;
	}
	for (var i = 0; i < node.length; ++i)
	{
		if (Array.isArray(node[i]))
		{
			return false;
		}
	}
	return true;
}

// Walks _data.gaining.table down a path of indices.
function geTableNode(path)
{
	var node = _data.gaining.table;
	for (var i = 0; i < path.length; ++i)
	{
		node = node[path[i]];
	}
	return node;
}

function gePathId(path)
{
	return path.length ? path.join('_') : 'root';
}

function geTableAdd()
{
	_data.gaining.table = [];
	refreshGainingEditor(true);
}

function geTableDrop()
{
	delete _data.gaining.table;
	refreshGainingEditor(true);
}

function geLeafChange(pathStr)
{
	var path = pathStr.length ? pathStr.split('_').map(Number) : [];
	var parent = geTableNode(path.slice(0, path.length - 1));
	var text = $('#table-leaf-' + gePathId(path)).val();
	var parts = text.split(',');
	var values = [];
	for (var i = 0; i < parts.length; ++i)
	{
		var p = parts[i].trim();
		if (p.length == 0)
		{
			continue;
		}
		// Anything unparseable is kept as typed so that the field still shows what the author
		// wrote; isGainingDataCorrect() then blocks the save until it is fixed.
		values.push(isNumeric(p) ? parseFloat(p) : p);
	}
	if (path.length == 0)
	{
		_data.gaining.table = values;
	}
	else
	{
		parent[path[path.length - 1]] = values;
	}
	dirty(true);
}

function geEntryAdd(pathStr, nested)
{
	var path = pathStr.length ? pathStr.split('_').map(Number) : [];
	var node = geTableNode(path);
	node.push(nested ? [[]] : []);
	refreshGainingEditor(true);
}

// Turns an empty row into a nested table by giving it one empty row of its own.
function geLeafToGroup(pathStr)
{
	var path = pathStr.length ? pathStr.split('_').map(Number) : [];
	geTableNode(path).push([]);
	refreshGainingEditor(true);
}

function geEntryDelete(pathStr)
{
	var path = pathStr.length ? pathStr.split('_').map(Number) : [];
	var parent = geTableNode(path.slice(0, path.length - 1));
	parent.splice(path[path.length - 1], 1);
	refreshGainingEditor(true);
}

function geEntryMove(pathStr, delta)
{
	var path = pathStr.length ? pathStr.split('_').map(Number) : [];
	var parent = geTableNode(path.slice(0, path.length - 1));
	var index = path[path.length - 1];
	var to = index + delta;
	if (to < 0 || to >= parent.length)
	{
		return;
	}
	var tmp = parent[index];
	parent[index] = parent[to];
	parent[to] = tmp;
	refreshGainingEditor(true);
}

function geNodeHtml(node, path)
{
	var pathStr = path.join('_');
	var id = gePathId(path);
	var html = '';

	if (geIsLeaf(node))
	{
		var bad = false;
		for (var i = 0; i < node.length; ++i)
		{
			if (!isNumeric(node[i]))
			{
				bad = true;
			}
		}
		html += '<input id="table-leaf-' + id + '" style="width: 90%; vertical-align: middle;' + (bad ? ' background: #fdd;' : '') + '"' +
			' value="' + geEscape(node.join(', ')) + '"' +
			' oninput="geLeafChange(\'' + pathStr + '\')" onchange="refreshGainingPreview()">';
		// An empty row is still undecided: it can hold numbers, or it can become the level above
		// another one. Without this there would be no way to build a nested table from scratch.
		if (node.length == 0)
		{
			html += ' <a href="javascript:geLeafToGroup(\'' + pathStr + '\')" title="' + _data.strings.entryAddTable + '"><img src="images/create.png" border="0" style="vertical-align: middle;"></a>';
		}
		return html;
	}

	// The two ways of growing this level live in its heading rather than in a row of their own
	// under the entries.
	html += '<table class="bordered light" width="100%">';
	html += '<tr class="dark"><td colspan="2">';
	html += geIconButton('geEntryAdd(\'' + pathStr + '\', 0)', 'create', _data.strings.entryAddRow);
	html += ' ' + geIconButton('geEntryAdd(\'' + pathStr + '\', 1)', 'create', _data.strings.entryAddTable);
	html += '</td></tr>';
	for (var i = 0; i < node.length; ++i)
	{
		var childPath = path.concat([i]);
		var childStr = childPath.join('_');
		// The index is the number a formula passes to table(), so it is plain - brackets would
		// only read as part of it. Middle everywhere: the cell against the row, and the digit
		// against the buttons, which are images and would otherwise sit on the baseline.
		html += '<tr><td width="1" class="dark" valign="middle" nowrap>';
		html += '<b style="vertical-align: middle;">' + i + '</b> ';
		html += '<a href="javascript:geEntryDelete(\'' + childStr + '\')" title="' + _data.strings.entryDel + '"><img src="images/delete.png" border="0" style="vertical-align: middle;"></a>';
		html += '<a href="javascript:geEntryMove(\'' + childStr + '\', -1)" title="' + _data.strings.moveUp + '"><img src="images/up.png" border="0" style="vertical-align: middle;"></a>';
		html += '<a href="javascript:geEntryMove(\'' + childStr + '\', 1)" title="' + _data.strings.moveDown + '"><img src="images/down.png" border="0" style="vertical-align: middle;"></a>';
		html += '</td><td valign="middle">';
		html += geNodeHtml(node[i], childPath);
		html += '</td></tr>';
	}
	html += '</table>';
	return html;
}

function geTableHtml()
{
	// Creating and dropping the table belong at the head of its own heading row, in the column the
	// numbers below line up under.
	var present = typeof _data.gaining.table != "undefined";
	var html = geSectionStart();
	html += '<tr class="darker"><td width="1" valign="middle" nowrap>';
	html += present
		? geRowButton('geTableDrop()', 'delete', _data.strings.tableDrop)
		: geRowButton('geTableAdd()', 'create', _data.strings.tableAdd);
	html += '</td><td><b>' + _data.strings.table + '</b> &mdash; ' + _data.strings.tableHelp + '</td></tr>';
	if (present)
	{
		html += '<tr><td></td><td>' + geNodeHtml(_data.gaining.table, []) + '</td></tr>';
	}
	html += geSectionEnd();
	return html;
}

//--------------------------------------------------------------------------------------
// the editor
//--------------------------------------------------------------------------------------
function setGainingVersion(version)
{
	if (_data.version != version)
	{
		_data.version = version;
		refreshGainingEditor();
	}
}

function refreshGainingEditor(isDirty)
{
	var g = _data.gaining;
	var html = '';

	// The name of the system and which version is open.
	html += geSectionStart('dark');
	html += '<tr><td>' + _data.strings.name + ': <input id="gaining-name" value="' + geEscape(_data.name) + '" oninput="geNameChange()">' +
		' &emsp; ' + _data.strings.version + ': ' + _data.version + '</td></tr>';
	html += geSectionEnd();

	// The formulas.
	html += geSectionStart();
	html += '<tr class="darker"><td><b>' + _data.strings.points + '</b> &mdash; ' + _data.strings.pointsHelp + '</td></tr>';
	html += '<tr><td><textarea id="gaining-points" rows="3" style="width: 95%; vertical-align: middle;"' +
		' oninput="gePointsChange(\'points\')" onchange="refreshGainingPreview()">' + geEscape(g.points) + '</textarea>' +
		geHelpButton() + '</td></tr>';
	html += '<tr><td><input type="checkbox" id="gaining-has-series-points" onclick="geSeriesPointsToggle()"' +
		(typeof g.seriesPoints == "undefined" ? '' : ' checked') + '> ' + _data.strings.seriesPointsUse + '</td></tr>';
	if (typeof g.seriesPoints != "undefined")
	{
		html += '<tr><td><textarea id="gaining-seriesPoints" rows="3" style="width: 95%; vertical-align: middle;"' +
			' oninput="gePointsChange(\'seriesPoints\')" onchange="refreshGainingPreview()">' + geEscape(g.seriesPoints) + '</textarea>' +
			geHelpButton() + '</td></tr>';
	}
	html += geSectionEnd();

	// How many of a player's tournaments count. One line either way, but it says what is actually
	// happening: a greyed out "count only the best 10" still reads as a limit of 10, so with the box
	// clear the line says every tournament counts and no number is shown at all.
	var limited = typeof g.maxTournaments != "undefined";
	html += geSectionStart();
	html += '<tr class="darker"><td><b>' + _data.strings.maxTournaments + '</b></td></tr>';
	html += '<tr><td><input type="checkbox" id="gaining-limit-tournaments" onclick="geMaxTournamentsToggle()"' +
		(limited ? ' checked' : '') + '> ';
	if (limited)
	{
		html += _data.strings.countBest +
			' <input type="number" min="1" step="1" style="width: 60px; vertical-align: middle;" id="gaining-max-tournaments"' +
			' value="' + geEscape(g.maxTournaments) + '"' +
			' oninput="geMaxTournamentsChange()" onchange="refreshGainingPreview()"> ' + _data.strings.countBestPost;
	}
	else
	{
		html += _data.strings.allTournaments;
	}
	html += '</td></tr>';
	html += geSectionEnd();

	html += geVarsHtml('globals', _data.strings.globals, _data.strings.globalsHelp);
	html += geVarsHtml('vars', _data.strings.vars, _data.strings.varsHelp);
	html += geTableHtml();

	$("#gaining-editor").html(html);
	dirty(isDirty);
	refreshGainingPreview();
}

// The hosting page owns the preview - it knows where to send the json and what to do with the
// answer - so the editor only says when the formulas changed enough to be worth re-evaluating.
function refreshGainingPreview()
{
	if (_onPreview)
	{
		_onPreview(_data);
	}
}

function initGainingEditor(data, onChangeData, onPreview)
{
	_data = data;
	_onChangeData = onChangeData;
	_onPreview = onPreview;

	if (typeof _data.gaining != "object" || _data.gaining === null)
	{
		_data.gaining = {};
	}
	if (typeof _data.gaining.points != "string")
	{
		_data.gaining.points = '0';
	}
	if (typeof _data.gaining.maxTournaments != "undefined")
	{
		_lastMaxTournaments = _data.gaining.maxTournaments;
	}

	_globals = [];
	if (_data.gaining.globals)
	{
		for (var name in _data.gaining.globals)
		{
			_globals.push({ name: name, expr: '' + _data.gaining.globals[name] });
		}
	}
	_vars = [];
	if (_data.gaining.vars)
	{
		for (var name in _data.gaining.vars)
		{
			_vars.push({ name: name, expr: '' + _data.gaining.vars[name] });
		}
	}

	refreshGainingEditor();
}
