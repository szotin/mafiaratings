//-----------------------------------------------------------
// Private API. Don't use it outside of this file.
//-----------------------------------------------------------
function _uiOption(value, current, text)
{
	if (value == current)
	{
		return '<option value="' + value + '" selected>' + text + '</option>';
	}
	return '<option value="' + value + '">' + text + '</option>';
}

function _uiInit()
{
	var html = '<option value="0"></option>';
	for (var i in regs)
	{
		var r = regs[i];
		html += '<option value="' + r.id + '">' + r.name + '</option>';;
	}
	
	for (var i = 0; i < 10; ++i)
	{
		var p = game.players[i];
		$('#player' + i).html(html).val(p.id ? p.id : 0);
	}
}

function _uiError(message, data)
{
	if (data)
	{
		console.log(data);
	}
	
	// dlg.error(text, title, width, onClose)
	dlg.error(message, undefined, undefined, function()
	{
		goTo({round:undefined});
	}); 
	
	// returning true makes json.post to bypass showing error dialog
	return true; 
}

function _uiConnectionListener(state)
{
	var url = "images/connected.png";
	if (state == 1)
		url = "images/save.png";
	else if (state == 2)
		url = "images/disconnected.png";
	else if (state == 3)
		url = "images/warn.png";
	$('#saving-img').attr("src", url);
}
	
//-----------------------------------------------------------
// Public API
//-----------------------------------------------------------
function uiStart(eventId, tableNum, roundNum)
{
	$('#ops').click(function()
	{
		var menu = $('#ops-menu').menu();
		menu.show(0, function()
		{
			menu.position(
			{
				my: "left top",
				at: "left bottom",
				of: $('#ops')
			});
			$(document).one("click", function() { menu.hide(); });
		});
		return false;
	});
	
	gameInit(eventId, tableNum, roundNum, _uiInit, _uiError, _uiConnectionListener);
}
	
// Call this to change a player at num. Don't use gameSetPlayer.	
// User with userId must be registered for the event. Use uiRegisterPlayer instead if uncertain.
function uiSetPlayer(num, userId)
{
	if (!userId)
	{
		userId = $('#player' + num).val();
	}
	else
	{
		$('#player' + num).val(userId);
	}
	
	var n = gameSetPlayer(num, userId);
	if (n >= 0)
	{
		$('#player' + n).val(0);
	}
}

function uiRegisterPlayer(num, data)
{
	if (data)
	{
		regs = data.regs;
		_uiInit();
		uiSetPlayer(num, data.user_id);
	}
	else
	{
		dlg.infoForm("form/event_register_player.php?num=" + num + "&event_id=" + game.eventId, 800);
	}
}
	
function uiCreatePlayer(num)
{
	dlg.form("form/event_create_player.php?event_id=" + game.eventId, function(data) { uiRegisterPlayer(num, data); }, 500);
}

function uiConfig(text, onClose)
{
	var html = '<table class="dialog_form" width="100%">';
	
	if (text)
	{
		html += '<tr><td colspan="2" align="center"><p><b>' + text + '</b></p></td></tr>';
	}
	
	html += '<tr><td>' + l('Moder') + ':</td><td><select id="dlg-moder">'
	html += _uiOption(0, game.moderator.id, '');
	for (var i in regs)
	{
		var r = regs[i];
		html += _uiOption(r.id, game.moderator.id, r.name);
	}
	html += '</select></td></tr>';
	if (langs.length > 1)
	{
		html += '<tr><td>' + l('Lang') + ':</td><td><select id="dlg-lang">';
		for (var i in langs)
		{
			var lang = langs[i];
			html += _uiOption(lang.code, game.language, lang.name);
		}
		html += '</select></td></tr>';
	}
	
	html += '<tr><td colspan="2"><input type="checkbox" id="dlg-rating"';
	if (!isSet(game.rating) || game.rating)
	{
		html += ' checked';
	}
	html += '> ' + l('Rating') + '</td></tr>';

	html += '</table>';
		
	dlg.okCancel(html, $('#game-id').text(), 500, function()
	{
		gameSetIsRating($('#dlg-rating').attr('checked') ? 1 : 0);
		if (langs.length > 1)
		{
			gameSetLang($('#dlg-lang').val());
		}
		gameSetModerator($('#dlg-moder').val());
		if (onClose)
		{
			onClose();
		}
	});
}
