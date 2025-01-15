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

function _uiRender(flags)
{
	if (flags & 1) // players changed
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
	
	if (flags & 6) // game time changed or roles changed
	{
		var dStyle = gameIsNight() ? 'night-' : 'day-';
		var eStyle = dStyle + 'empty';

		$('#r-1').removeClass().addClass(eStyle);
		$('#head').removeClass().addClass(eStyle);
		for (var i = 0; i < 10; ++i)
		{
			$('#r' + i).removeClass().addClass(dStyle + (isSet(game.players[i].death) ? 'dead' : 'alive'));
			$('#num' + i).removeClass();
			$('#panel' + i).html('').removeClass();
			$('#control' + i).html('').removeClass();
		}
		
		var status = '';
		var control1Html = '';
		var nextDisabled = false;
		var backDisabled = false;
		if (!isSet(game.time))
		{
			status = l('StartGame');
			$('#info').html('');
			control1Html = '<button class="day-vote" onclick="gameRandomizeSeats()">' + l('RandSeats') + '</button>';
			backDisabled = true;
		}
		else
		{
			// for (var i = 0; i < 10; ++i)
			// {
				// if (player.state != /*PLAYER_STATE_ALIVE*/0)
				// {
					// $('#btns-' + i).html('');
				// }
				// else
				// {
					// $('#btns-' + i).html(
							// '<button class="icon" onclick="mafia.warnPlayer(' + i + ')"><img src="images/warn.png" title="' + l('Warn') + '"></button>' +
							// '<button class="icon" onclick="mafia.ui.leaveGame(' + i + ')"><img src="images/suicide.png" title="' + l('GiveUp') + '"></button>');
				// }
			// }
			
			var info = 'Day';
			switch (game.time.time)
			{
			case 'start':
				status = l('AssignRoles');
				control1Html = '<button class="day-vote" onclick="gameGenerateRoles()">' + l('GenRoles') + '</button>';
				for (var i = 0; i < 10; ++i)
				{
					var p = game.players[i];
					var r = isSet(p.role) ? p.role : 'civ';
					$('#panel' + i).html(
						'<button class="night-char" id="role-' + i + '-civ" onclick="uiSetRole(' + i + ', \'civ\')"><img class="role-icon" src="images/civ.png"></button>' +
						'<button class="night-char" id="role-' + i + '-sheriff" onclick="uiSetRole(' + i + ', \'sheriff\')" title="' + l('sheriff') + '"><img class="role-icon" src="images/sheriff.png"></button>' +
						'<button class="night-char" id="role-' + i + '-maf" onclick="uiSetRole(' + i + ', \'maf\')" title="' + l('mafia') + '"><img class="role-icon" src="images/maf.png"></button>' +
						'<button class="night-char" id="role-' + i + '-don" onclick="uiSetRole(' + i + ', \'don\')" title="' + l('don') + '"><img class="role-icon" src="images/don.png"></button>');
					$('#control' + i).html(
						'<select id="role-' + i + '" onchange="uiSetRole(' + i + ')"><option value="civ"></option><option value="sheriff">' + l('sheriff') + '</option><option value="maf">' + l('mafia') + '</option><option value="don">' + l('don') + '</option></select>');
					$('#role-' + i + '-' + r).attr('checked', '');
					$('#role-' + i).val(r);
				}
				nextDisabled = !gameAreRolesSet();
				break;
			case 'arrangement':
				break;
			case 'day start':
				break;
			case 'night kill speaking':
				break;
			case 'speaking':
				break;
			case 'voting':
				break;
			case 'day kill speaking':
				break;
			case 'shooting':
				break;
			case 'don':
				break;
			case 'sheriff':
				break;
			case 'end':
				break;
			}
			$('#info').html(l(info, game.time.round));
		}
		$('#status').html(status);
		$('#control-1').html(control1Html);
		$('#game-next').prop('disabled', nextDisabled);
		$('#game-back').prop('disabled', backDisabled);
	}
}

function _uiErrorListener(type, message, data)
{
	if (data)
	{
		console.log(data);
	}
	
	if (type == 0) // error getting data
	{
		// dlg.error(text, title, width, onClose)
		dlg.error(message, undefined, undefined, function()
		{
			goTo({round:undefined});
		});
	}
	else // error setting data
	{
		// nothing to do - connection listener takes care
	}
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
	
	gameInit(eventId, tableNum, roundNum, _uiRender, _uiErrorListener, _uiConnectionListener);
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

function uiSetRole(num, role)
{
	if (!role)
	{
		role = $('#role-' + num).val();
	}
	gameSetRole(num, role);
}
