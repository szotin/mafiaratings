var timer = new function()
{
	var _start = 0;
	var _max = 0;
	var _cur = 0;
	var _prompt = 0;
	var _blinkCount = 0;
	var _html = '<table id="t-area" class="timer timer-0" width="100%"><tr><td width="1"><button id="timerBtn" class="timer" onclick="timer.toggle()"><img id="timerImg" src="images/resume_big.png" class="timer"></button></td><td><div id="timer" class="timer"></div></td><td width="1"><button class="timer" onclick="timer.inc(-10)"><img src="images/dec_big.png" class="timer"></button></td><td width="1"><button class="timer" onclick="timer.inc(10)"><img src="images/inc_big.png" class="timer"></button></td></tr></table>';
	var _eSnd;
	var _pSnd;
	var _hidden = true;
	
	function _blink()
	{
		if (_blinkCount <= 0) return;
		try
		{
			var a = $('#t-area');
			var c = a.attr('class');
			var i = c.indexOf('timer-') + 6;
			var n = (i >= 0 ? parseInt(c.substr(i, 1)) + 1 : 1);
			if (isNaN(n) || n > 1)
			{
				n = 0;
				--_blinkCount;
			}
			a.attr('class', 'timer timer-' + n);
			setTimeout(_blink, 60);
		}
		catch (err)
		{
		}
	}
	
	function _set(val)
	{
		if (val < 0) val = 0;
		var m = Math.floor(val / 60);
		var s = val % 60;
		if (s < 10)
		{
			s = '0' + s;
		}
		try
		{
			$('#timer').html(m + ':' + s);
		}
		catch (err)
		{
		}
	}
	
	function _get()
	{
		var v = 0;
		try
		{
			var a = $('#timer').html().split(':');
			if (a.length > 0)
			{
				var m = 0;
				if (a.length == 1)
				{
					v = parseInt(a[0]);
				}
				else
				{
					m = parseInt(a[0]);
					if (isNaN(m)) m = 0;
					v = parseInt(a[1]);
				}
				if (isNaN(v)) v = 0;
				v += m * 60;
			}
		}
		catch (err)
		{
		}
		return v;
	}
	
	this.hide = function(reset, clockHtml)
	{
		$('#clock').html(clockHtml);
		_hidden = true;
		
		if (reset)
			_start = 0;
		
	}
	
	this.show = function(total, prompt, reset)
	{
		if (_hidden)
		{
			$('#clock').html(_html);
			_hidden = false;
		}
		
		$('#t-area').attr('class', 'timer timer-0');
		_eSnd = document.getElementById('end-snd').cloneNode(true);
		_pSnd = document.getElementById('prompt-snd').cloneNode(true);
		
		_blinkCount = 0;
		_prompt = parseInt(prompt);
		if (reset)
		{
			if (_start > 0)
			{
				$('#timerImg').attr('src', "images/resume_big.png");
				_start = 0;
			}
			_cur = _max = total;
		}
		else
		{
			_max = _cur;
			if (_start > 0)
			{
				$('#timerImg').attr('src', "images/pause_big.png");
				_start = (new Date()).getTime();
			}
		}
		_set(_cur);
		
		if (mafia.data().user.settings.flags & /*S_FLAG_START_TIMER*/0x2)
			timer.start();
	}
	
	this.stop = function()
	{
		if (_start > 0)
		{
			try
			{
				$('#timerImg').attr('src', "images/resume_big.png");
			}
			catch (err)
			{
			}
			_start = 0;
		}
	}
	
	this.start = function()
	{
		if (_start <= 0)
		{
			_cur = _max = _get();
			if (_max > 0)
			{
				try
				{
					$('#timerImg').attr('src', "images/pause_big.png");
				}
				catch (err)
				{
				}
				_start = (new Date()).getTime();
			}
		}
	}
	
	this.toggle = function()
	{
		if (_start > 0)
		{
			timer.stop();
		}
		else
		{
			timer.start();
		}
	}

	this.inc = function(s)
	{
		var t;
		if (_start > 0)
		{
			if (isNaN(_max)) _max = 0;
			_max += s;
			if (_max < 0) _max = 0;
			_cur = t = _max - Math.round(((new Date()).getTime() - _start) / 1000);
		}
		else
		{
			t = _get() + s;
		}
		_set(t);
	}

	this.tick = function()
	{
		if (_start > 0)
		{
			var t = _max - Math.round(((new Date()).getTime() - _start) / 1000);
			var f = mafia.data().user.settings.flags;
			if (t <= 0)
			{
				if ((f & /*S_FLAG_NO_SOUND*/0x4) == 0)
				{
					_eSnd.play();
				}
				if ((f & /*S_FLAG_NO_BLINKING*/0x8) == 0)
				{
					_blinkCount = 15;
					_blink();
				}
				_cur = 0;
				_set(_cur);
				timer.stop();
			}
			else
			{
				if (_get() > _prompt && t <= _prompt)
				{
					if ((f & /*S_FLAG_NO_SOUND*/0x4) == 0)
					{
						_pSnd.play();
					}
					if ((f & /*S_FLAG_NO_BLINKING*/0x8) == 0)
					{
						_blinkCount = 2;
						_blink();
					}
				}
				_cur = t;
				_set(t);
			}
		}
	}
} // timer

var statusWaiter = new function()
{
	var busy = false;

	// returning false cancels the operation
	this.start = function()
	{
		if (!busy)
		{
			busy = true;
			$('#saving-img').attr("src", "images/save.png");
			$('#saving-btn').attr("onclick", "");
			$('#saving').html(l('Saving'));
			return true;
		}
		return false;
	}
	
	this.success = function()
	{
		busy = false;
		this.connected(http.connected());
	}
	
	this.error = function(message)
	{
		busy = false;
		if (http.connected())
		{
			$('#saving-img').attr("src", "images/warn.png");
			$('#saving-btn').attr("onclick", "statusWaiter.update()");
			$('#saving').html(l('SaveErr', message));
		}
	}
	
	this.info = function(message, title, onClose)
	{
		console.log(message);
		onClose();
	}
	
	this.connected = function(c)
	{
		var data = mafia.data();
		if (c)
		{
			$('#saving-img').attr("src", "images/connected.png");
			$('#saving-btn').attr("onclick", "http.connected(false)");
		}
		else
		{
			$('#saving-img').attr("src", "images/disconnected.png");
			$('#saving-btn').attr("onclick", "mafia.sync()");
		}
        $('#saving').html('');
	}
	
	this.update = function()
	{
		this.connected(http.connected());
	}
} // statusWaiter

mafia.ui = new function()
{
	var _mobile = false;
	var _shortSpeech = false;
	var _lCounter = 0;
	var _gCounter = 0;
	var _backPage = null;
	var _oldState = -1;

	function _option(value, current, text)
	{
		if (value == current)
		{
			return '<option value="' + value + '" selected>' + text + '</option>';
		}
		return '<option value="' + value + '">' + text + '</option>';
	}

	function _enableMenuItem(i, b)
	{
		if (b)
			i.removeClass('ui-state-disabled');
		else
			i.addClass('ui-state-disabled');
	}

	function _enable(elem, state)
	{
		if (state)
			elem.removeAttr('disabled');
		else
			elem.attr('disabled','disabled');
	}

	function _votingRButtons(game)
	{
		if (mafia.curVoting().canceled == 0)
		{
			var html;
			for (var i = 0; i < 10; ++i)
			{
				p = game.players[i];
				if (p.state == /*PLAYER_STATE_ALIVE*/0)
				{
					var c = $('#control' + i);
					if (mafia.isNominated(i))
					{
						c.addClass('day-grey');
						c.html('<center>' + l('Nominated') + '</center>');
					}
					else
					{
						html = '<button class="day-vote" onclick="mafia.nominatePlayer(' + i + ')"';
						if (i == game.current_nominant)
							html += ' checked';
						html += '>' + l('Nominate', i + 1) + '</button>';
						c.html(html);
					}
				}
			}
			html = '<button class="day-vote" onclick="mafia.nominatePlayer(-1)"';
			if (game.current_nominant < 0)
				html += ' checked';
			html += '>' + l('NoNom') + '</button>';
			$('#control-1').html(html);
		}
	}
	
	this.mobile = function(val)
	{
		var m = _mobile;
		if (typeof val != "undefined")
			_mobile = val;
		return m;
	}

	this.updateButtons = function()
	{
		var data = mafia.data();
		
		$('#back').prop('disabled', !mafia.canBack());
		_enableMenuItem($('#save'), mafia.localDirty());
		_enableMenuItem($('#sync'), mafia.globalDirty());
		_enableMenuItem($('#club'), data != null && data.user.clubs.length > 1);
		
		var v = mafia.curVoting();
		_enableMenuItem($('#voting'), v != null);
		if (v == null || v.canceled <= 0)
			$('#voting-txt').html(l('CancelVoting'))
		else
			$('#voting-txt').html(l('RestoreVoting'))
	}
	
	this.FLAG_MOBILE = 1;
	this.FLAG_ONLINE = 2;
	this.FLAG_EDITING = 4;
	this.start = function (flags, clubId, eventId, backPage)
	{
		if (typeof backPage === "string")
		{
			_backPage = backPage;
		}
		
		var html = 
			'<div id="demo">' + l('Demo') + '</div>' +
			'<table class="bordered" width="100%" id="players">' +
				'<tr class="day-empty header-row" align="center"><td id="head" colspan="6">' +
					'<table class="transp" width="100%">' + 
						'<tr>' +
							'<td width="50">' +
								'<button id="ops" class="ops"><img src="images/settings_big.png" border="0" height="54"></button>' +
								'<ul id="ops-menu" style="position:absolute;">' +
									'<li id="club" class="ops-item"><a href="#" onclick="selectClubForm.show()"><img src="images/gun.png" class="text"> ' + l('ChangeClub') + '</a></li>' +
									'<li type="separator"></li>' +
									'<li id="sync" class="ops-item"><a href="#" onclick="mafia.sync()"><img src="images/sync.png" class="text"> ' + l('SendData') + '</a></li>' +
									'<li id="save" class="ops-item"><a href="#" onclick="mafia.ui.save()"><img src="images/save.png" class="text"> ' + l('Save') + '</a></li>' +
									'<li type="separator"></li>' +
									'<li id="voting" class="ops-item"><a href="#" onclick="mafia.toggleVoting()"><img src="images/vote.png" class="text"> <span id="voting-txt">' + l('CancelVoting') + '</span></a></li>' +
									'<li type="separator"></li>';
		if (flags & this.FLAG_ONLINE)
			html +=
									'<li id="offline" class="ops-item"><a href="#" onclick="mafia.ui.offline()"><img src="images/disconnected.png" class="text"> ' + l('Offline') + '</a></li>'
						
		html +=
									'<li id="settings" class="ops-item"><a href="#" onclick="mafia.ui.settings()"><img src="images/settings.png" class="text"> ' + l('Settings') + '</a></li>' +
								'</ul>' +
							'</td>' +
							'<td id="status" align="center"></td>' +
							'<td id="clock" width="320"></td>' +
						'</tr>' +
					'</table>' +
				'</td></tr>';
		for (var i = 0; i < 10; ++i)
		{
			html +=
				'<tr class="day-alive player-row" id="r' + i + '">' +
					'<td width="20" align="center" id="num' + i + '">' + (i + 1) + '</td>' +
					'<td id="name' + i + '"></td>' +
					'<td id="panel' + i + '" width="114"></td>' +
					'<td id="control' + i + '" width="160"></td>' +
					'<td id="warn' + i + '" width="100"></td>' +
					'<td width="90">' +
						'<span id="btns-' + i + '">' +
							'<button class="icon" onclick="mafia.ui.warnPlayer(' + i + ')"><img src="images/warn.png"></button>' +
							'<button class="icon" onclick="mafia.ui.suicide(' + i + ')"><img src="images/suicide.png"></button>' +
							'<button class="icon" onclick="mafia.ui.kickOut(' + i + ')"><img src="images/delete.png""></button>' +
						'</span>' +
					'</td>' +
				'</tr>';
		}
		html +=
				'<tr class="day-empty footer-row" id="r-1">' +
					'<td colspan="3">' +
						'<table class="invis" width="100%"><tr>' +
							'<td><button class="icon" id="saving-btn"><img id="saving-img" border="0"></button></td>' +
							'<td id="saving"></td>' +
							'<td id="game-id" align="right"></td>' +
						'</tr></table>' +
					'</td>' +
					'<td id="control-1"></td>' +
					'<td id="noms" colspan="2"></td>' +
				'</tr>' +
			'</table>' +
			'<div class="btn-panel"><table class="transp" width="100%"><tr>' +
				'<td><button class="game-btn" id="back" onclick="mafia.ui.back()"><img src="images/prev.png" class="text"></button></td>' +
				'<td id="info" align="center"></td>' +
				'<td align="right"><button class="game-btn" id="next" onclick="mafia.ui.next()"><img src="images/next.png" class="text"></button></td>' +
			'</tr></table></div>' +
			'<audio id="end-snd" src="sound/end.mp3" preload></audio>' +
			'<audio id="prompt-snd" src="sound/10sec.mp3" preload></audio>';
			
		$('#game-area').html(html);
	
	
		var menu = $('#ops-menu').menu().hide();
		$('#ops').click(function()
		{
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

		window.onbeforeunload = function()
		{
			mafia.save();
		};

		dialogWaiter.connected = silentWaiter.connected = statusWaiter.connected;
	
		mafia.ui.mobile((flags & this.FLAG_MOBILE) != 0);
		mafia.editing((flags & this.FLAG_EDITING) != 0);
		mafia.stateChange(mafia.ui.sync);
		mafia.dirtyEvent(mafia.ui.updateButtons);
		mafia.failEvent(function(message) { dlg.error(message); });
		if (typeof localStorage != "object")
		{
			dlg.info(l('ErrNoStorage'));
		}
		
		mafia.sync(clubId, eventId, function()
		{
			mafia.ui.fillUsers();

			setInterval(function()
			{
				var s = mafia.data().user.settings;
				_lCounter += 10;
				_gCounter += 10;
				if (_gCounter >= s.g_autosave)
				{
					var s = true;
					_gCounter = 0;
					if (mafia.globalDirty())
					{
						var w = http.waiter(statusWaiter);
						mafia.sync();
						http.waiter(w);
						s = false;
					}
					if (s)
						mafia.save();
					if (_lCounter >= s.l_autosave) _lCounter = 0;
				}
				else if (_lCounter >= s.l_autosave)
				{
					_lCounter = 0;
					mafia.save();
				}
			}, 10000);
		});
	}

	this.sync = function(flags)
	{
		var data = mafia.data();
		var club = data.club;
		var game = data.game;
		var user = data.user;
		var html;
		
		var status = '';
		var onStatus = null;
		var voting = mafia.curVoting();
		var reset = ((flags & /*STATE_CHANGE_FLAG_RESET_TIMER*/1) != 0);
		
		var st = 0;
		var spt;
		var clockHtml = '';
		
		if (reset) _shortSpeech = false;
		
		if (game.id > 0)
			$('#game-id').html('<b>' + data.club.name + ' : ' + l('Game') + ' ' + data.game.id + '</b>');
		else
			$('#game-id').html('<b>' + data.club.name + '</b>');
		
		if (flags & /*STATE_CHANGE_FLAG_CLUB_CHANGED*/2)
			statusWaiter.update();
			
		if (flags & /*STATE_CHANGE_FLAG_GAME_WAS_ENDED*/4)
			mafia.ui.fillUsers();
		
		$('#control-1').html('');
		
		// console.log("... " + game.gamestate);
		if (game.gamestate == /*GAME_STATE_NOT_STARTED*/0)
		{
			if (_oldState > 0 && _backPage != null)
			{
				mafia.sync(0, 0, function()
				{
					window.location.replace(_backPage);
				});
				return;
			}
			
			status = '<table width="100%"><tr><td><table><tr><td>' + l('Event') + ':</td>';
			if (user.manager)
			{
				status += '<td><button class="icon" onclick="eventForm.show()"><img src="images/create.png" class="icon"></button></td>';
			}
			status += '<td><select id="events" onchange="mafia.ui.eventChange(false)"></select></td></td></tr></table></tr><tr><td align="left">' + l('Rules') + ': <select id="rules" onchange="mafia.ui.rulesChange()">';
			var sRules = mafia.sRules();
			for (var i in sRules)
			{
				var rules_id = sRules[i];
				var name = club.rules[rules_id].name;
				if (name.length == 0)
				{
					name = l('DefRules');
				}
				status += _option(rules_id, game.rules_id, name);
			}
			status += '</select></td></tr></table>';
			
			clockHtml = '<table width="100%"><tr><td align="right">' + l('Lang') + ': <select id="lang" onchange="mafia.ui.langChange()"></select></td></tr>';
			clockHtml += '</select></td></tr><tr><td align="right"><table><tr><td>' + l('Moder') + ':</td><td><button id="reg-moder" class="icon" onclick="mafia.ui.register(10)"><img src="images/user.png" class="icon"></button></td><td><select id="player10" onchange="mafia.ui.playerChange(10)"></select></td></tr></table></td></tr></table></td></tr></table>';
			
			for (var i = 0; i < 10; ++i)
			{
				$('#btns-' + i).hide();
				$('#name' + i).removeClass();
				$('#num' + i).removeClass();
				$('#warn' + i).html('').removeClass();
				$('#panel' + i).html('').removeClass();
				$('#control' + i).html('').removeClass();
				$('#r' + i).removeClass().addClass('day-alive');
			}
			$('#r-1').removeClass().addClass('day-empty');
			$('#head').removeClass().addClass('day-empty');
			onStatus = mafia.ui.fillEvents;
			$('#info').html('');
			$('#noms').html('');
		}
		else if (game.gamestate == /*GAME_STATE_END*/25)
		{
			mafia.ui.fillUsers();
			n = (mafia.playersCount(false) > 0);
			var dStyle = n ? 'night-' : 'day-';
			var eStyle = dStyle + 'empty';
			$('#r-1').removeClass().addClass(eStyle);
			$('#head').removeClass().addClass(eStyle);
			for (var i = 0; i < 10; ++i)
			{
				var player = game.players[i];
				$('#warn' + i).html('');
				$('#btns-' + i).hide();
				
				$('#r' + i).removeClass().addClass(dStyle + 'alive');
				$('#num' + i).removeClass();
				html = '<center>';
				switch (player.role)
				{
				case /*ROLE_SHERIFF*/1:
					html += l('sheriff');
					break
				case /*ROLE_MAFIA*/2:
					html += l('mafia');
					break
				case /*ROLE_DON*/3:
					html += l('don');
					break
				default:
					html += '';
					break
				}
				$('#panel' + i).html(html + '</center>').removeClass();
				
				html = '';
				if ((mafia.gameRules.flags & /*RULES_BEST_PLAYER*/0x100) != 0)
				{
					html = '<button class="day-vote" onclick="mafia.bestPlayer(' + i + ')"';
					if (i == game.best_player)
					{
						html += ' checked';
					}
					html += '> ' + l('BestPlayer', i + 1) + '</button>';
				}
				$('#control' + i).html(html).removeClass();
				
				html = '';
				if ((mafia.gameRules.flags & /*RULES_BEST_MOVE*/0x200) != 0)
				{
					html = '<button class="day-vote" onclick="mafia.bestMove(' + i + ')"';
					if (i == game.best_move)
					{
						html += ' checked';
					}
					html += '> ' + l('BestMove', i + 1) + '</button>';
				}
				$('#warn' + i).html(html);
			}
			status = '<h3>' + (n ? l('MafWin') : l('CivWin')) + '</h3>' + l('Finish');
			
			html = '';
			if ((mafia.gameRules.flags & /*RULES_BEST_PLAYER*/0x100) != 0)
			{
				html = '<button class="day-vote" onclick="mafia.bestPlayer(-1)"';
				if (game.best_player < 0 || game.best_player > 9)
					html += ' checked';
				html += '> ' + l('NoBest') + '</button>';
			}
			$('#control-1').html(html);
			
			html = '';
			if ((mafia.gameRules.flags & /*RULES_BEST_MOVE*/0x200) != 0)
			{
				html = '<button class="day-vote" onclick="mafia.bestMove(-1)"';
				if (game.best_move < 0 || game.best_move > 9)
					html += ' checked';
				html += '> ' + l('NoBest') + '</button>';
			}
			$('#noms').html(html);
			
			$('#info').html('');
		}
		else
		{
			var dStyle = mafia.isNight() ? 'night-' : 'day-';
			var eStyle = dStyle + 'empty';
			var info = 'Day';
			$('#r-1').removeClass().addClass(eStyle);
			$('#head').removeClass().addClass(eStyle);
			for (var i = 0; i < 10; ++i)
			{
				var player = game.players[i];
				switch (player.warnings)
				{
					case 0:
						$('#warn' + i).html('');
						break;
					case 1:
						$('#warn' + i).html(l('WarningText'));
						break;
					default:
						$('#warn' + i).html(l('WarningsText', player.warnings));
						break;
				}
				if (player.state != /*PLAYER_STATE_ALIVE*/0)
				{
					$('#btns-' + i).hide();
				}
				else
				{
					$('#btns-' + i).show();
				}
				
				$('#r' + i).removeClass().addClass(dStyle + ((player.state == /*PLAYER_STATE_ALIVE*/0) ? 'alive' : 'dead'));
				$('#num' + i).removeClass();
				$('#panel' + i).html('').removeClass();
				$('#control' + i).html('').removeClass();
			}
			
			var p, n;
			switch (game.gamestate)
			{
				case /*GAME_STATE_NIGHT0_START*/1:
					status = l('AssignRoles');
					n = [];
					for (var i = 0; i < 10; ++i)
					{
						p = game.players[i];
						var r = p.role;
						$('#panel' + i).html(
							'<button class="night-char" id="role-' + i + '-0" onclick="mafia.setPlayerRole(' + i + ', 0)"><img class="role-icon" src="images/civ.png"></button>' +
							'<button class="night-char" id="role-' + i + '-1" onclick="mafia.setPlayerRole(' + i + ', 1)" title="' + l('sheriff') + '"><img class="role-icon" src="images/sheriff.png"></button>' +
							'<button class="night-char" id="role-' + i + '-2" onclick="mafia.setPlayerRole(' + i + ', 2)" title="' + l('mafia') + '"><img class="role-icon" src="images/maf.png"></button>' +
							'<button class="night-char" id="role-' + i + '-3" onclick="mafia.setPlayerRole(' + i + ', 3)" title="' + l('don') + '"><img class="role-icon" src="images/don.png"></button>');
						$('#control' + i).html(
							'<select id="role-' + i + '" onchange="mafia.ui.setRole(' + i + ')"><option value="0"></option><option value="1">' + l('sheriff') + '</option><option value="2">' + l('mafia') + '</option><option value="3">' + l('don') + '</option></select>');
						$('#role-' + i + '-' + r).attr('checked', '');
						$('#role-' + i).val(r);
						if (p.id != 0 && (club.players[p.id].flags & /*U_FLAG_IMMUNITY*/1024))
						{
							n.push(p);
						}
					}
					if (n.length > 0)
					{
						html = '<table class="bordered" width="100%">';
						for (var i = 0; i < n.length; ++i)
						{
							p = n[i];
							html += '<tr><td width="24" align="center">' + (p.number + 1) + '</td><td>' + mafia.userTitle(p.id) + '</td></tr>';
						}
						html += '</table>';
					}
					info = 'Night0';
					$('#control-1').html('<button class="day-vote" onclick="mafia.generateRoles()">' + l('GenRoles') + '</button>');
					mafia.ui.fillUsers();
					break;
					
				case /*GAME_STATE_NIGHT0_ARRANGE*/2:
					status = l('Arrange');
					for (var i = 0; i < 10; ++i)
					{
						p = game.players[i];
						$('#panel' + i).html(
							'<button class="night-char" id="arr-' + i + '-x" onclick="mafia.arrangePlayer(' + i + ', -1)">x</button>' +
							'<button class="night-char" id="arr-' + i + '-0" onclick="mafia.arrangePlayer(' + i + ', 0)">1</button>' +
							'<button class="night-char" id="arr-' + i + '-1" onclick="mafia.arrangePlayer(' + i + ', 1)">2</button>' +
							'<button class="night-char" id="arr-' + i + '-2" onclick="mafia.arrangePlayer(' + i + ', 2)">3</button>');
						var str = p.has_immunity ? l('Immun') : '';
						$('#control' + i).html(
							'<select id="arr-' + i + '" onchange="mafia.ui.arrangePlayer(' + i + ')" style="width:120px;">' +
							'<option value="-1">' + str + '</option>' +
							'<option value="0">' + l('ArrNight', 1) + '</option>' +
							'<option value="1">' + l('ArrNight', 2) + '</option>' +
							'<option value="2">' + l('ArrNight', 3) + '</option>' +
							'<option value="3">' + l('ArrNight', 4) + '</option>' +
							'<option value="4">' + l('ArrNight', 5) + '</option>' +
							'</select>');
						if (p.arranged >= 0)
						{
							$('#arr-' + i + '-' + p.arranged).attr('checked', '');
						}
						$('#arr-' + i).val(p.arranged);
						
						if (p.role == /*ROLE_MAFIA*/2)
						{
							$('#num' + i).addClass('night-mark');
						}
						else if (p.role == /*ROLE_DON*/3)
						{
							$('#r' + i).removeClass().addClass('night-mark');
						}
					}
					st = mafia.gameRules.st_reg;
					spt = mafia.gameRules.spt_reg;
					info = 'Night0';
					break;
					
				case /*GAME_STATE_DAY_START*/3:
					status = l('GoodMorning'); 
					if (game.round != 0)
					{
						if (game.player_speaking >= 0)
						{
							$('#r' + game.player_speaking).removeClass().addClass('day-mark');
							p = game.players[game.player_speaking];
							if (p.is_male)
							{
								status +=
									' ' + l('NightKill', mafia.playerTitle(p.number), l('KilledMale')) +
									' ' + l('LastSpeech', l('He'), l('his'));
							}
							else
							{
								status +=
									' ' + l('NightKill', mafia.playerTitle(p.number), l('KilledFemale')) +
									' ' + l('LastSpeech', l('She'), l('her'));
							}
							st = mafia.gameRules.st_killed;
							spt = mafia.gameRules.spt_killed;
							if (mafia.gameRules.flags & /*RULES_FLAG_NIGHT_KILL_CAN_NOMINATE*/16)
							{
								_votingRButtons(game);
							}
						}
						else
						{
							status += ' ' + l('NobodyKilled');
						}
					}
					
					if (mafia.gameRules.flags & /*RULES_FLAG_FREE_ROUND*/2)
					{
						status += ' ' + l('NextFree');
					}
					else
					{
						status += ' ' + l('NextFloor', mafia.playerTitle(mafia.nextPlayer(-1)));
					}
					break;
					
				case /*GAME_STATE_DAY_GUESS3*/21:
					p = game.players[game.player_speaking];
					status = l('DayGuess', mafia.playerTitle(p.number));
					$('#r' + game.player_speaking).removeClass().addClass('day-mark');
					for (var i = 0; i < 10; ++i)
					{
						p = game.players[i];
						var c = $('#control' + i);
						html = '<button class="day-vote" onclick="mafia.guess(' + i + ')"';
						if (mafia.isGuessed(i))
						{
							html += ' checked';
						}
						html += '> ' + l('guess', i + 1) + '</button>';
						c.html(html);
					}
					$('#control-1').html('<button class="day-vote" onclick="mafia.noGuess()">' + l('noGuess') + '</button>');
					break;
					
				case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
					p = game.players[game.player_speaking];
					if (p.mute != game.round)
					{
						status = l('Speaking', mafia.playerTitle(p.number));
						st = mafia.gameRules.st_reg;
						spt = mafia.gameRules.spt_reg;
					}
					else if (_shortSpeech || ((mafia.gameRules.flags & /*RULES_MUTE_CRIT*/0x4000) && mafia.playersCount() <= 4))
					{
						status = l('SpeakingShort', mafia.playerTitle(p.number));
						st = mafia.gameRules.st_def;
						spt = mafia.gameRules.spt_def;
					}
					else
					{
						var he, his, him;
						if (p.is_male)
						{
							he = l('he');
							his = l('his');
							him = l('him_');
						}
						else
						{
							he = l('she');
							his = l('her');
							him = l('her_');
						}
						status = l('MissingSpeech', mafia.playerTitle(p.number), he, his);
						clockHtml = '<button class="warn3" onclick="mafia.ui.shortSpeech()">' + l('LetSpeak', him) +'</button><button class="warn3" onclick="mafia.postponeMute()">' + l('PostponeBan') + '</button>';
					}

					n = mafia.nextPlayer(game.player_speaking);
					if (n >= 0)
					{
						status += ' ' + l('NextFloor', mafia.playerTitle(n));
					}
					else
					{
						status += ' ' + l('NextVoting');
					}
					
					_votingRButtons(game);
					
					$('#r' + game.player_speaking).removeClass().addClass('day-mark');
					break;
					
				case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
					p = game.players[game.player_speaking];
					$('#r' + game.player_speaking).removeClass().addClass('day-mark');
					if (mafia.isKillingThisDay())
					{
						if (p.is_male)
							status = l('DayKill', mafia.playerTitle(p.number), l('KilledMale')) + ' ' + l('LastSpeech', l('He'), l('his'));
						else
							status = l('DayKill', mafia.playerTitle(p.number), l('KilledFemale')) + ' ' + l('LastSpeech', l('She'), l('her'));
						st = mafia.gameRules.st_killed;
						spt = mafia.gameRules.spt_killed;
					}
					else
					{
						status = l('DayKill', mafia.playerTitle(p.number), l('distrusted')) + ' ' + l('Speaking', p.is_male ? l('He') : l('She'));
						st = mafia.gameRules.st_def;
						spt = mafia.gameRules.spt_def;
					}
					
					n = mafia.votingWinners();
					p = game.current_nominant + 1;
					if (p < n.length)
					{
						status += ' ' + l('NextFloor', mafia.playerTitle(n[p]));
					}
					else
					{
						status += ' ' + l('StartNight');
					}
					break;
					
				case /*GAME_STATE_VOTING*/8:
					if (voting.canceled > 0)
					{
						status = l('VotingCanceled') + ' ' + l('StartNight');
					}
					else if (game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2)
					{
						status = l('DoSimpVoting');
						n = voting.nominants;
						for (var i = 0; i < n.length; ++i)
						{
							var p = n[i].player_num;
							var k = mafia.isKillingThisDay() ? (game.players[p].is_male ? l('KilledMale') : l('KilledFemale')) : l('distrusted');
							if (mafia.votingWinner(i))
								$('#control' + n[i].player_num).html('<button class="day-vote" onclick="mafia.votingWinner(' + i + ', 0)" checked>' + k + '</button>');
							else
								$('#control' + n[i].player_num).html('<button class="day-vote" onclick="mafia.votingWinner(' + i + ', 1)">' + k + '</button>');
						}
					}
					else
					{
						var ch_state = '';
						n = voting.nominants;
						p = game.current_nominant;
						var num = n[p].player_num;
						for (var i = 0; i < n.length; ++i)
						{
							$('#panel' + n[i].player_num).html('<center>' + n[i].count + '</center>').addClass('day-mark');
						}
						$('#r' + num).removeClass().addClass('day-mark');
						if (p + 1 < n.length)
						{
							status = l('Voting',
								mafia.playerTitle(num),
								mafia.playerTitle(n[p+1].player_num));
						}
						else
						{
							status = l('VotingLast', mafia.playerTitle(num));
							ch_state = ' disabled'
						}
						
						n = voting.votes;
						for (var i = 0; i < 10; ++i)
						{
							p = game.players[i];
							if (p.state == /*PLAYER_STATE_ALIVE*/0)
							{
								// console.log("." + i + '.' + " (current_nominant=" +  game.current_nominant + "; n[" + i + "]=" + n[i]);
								if (n[i] == game.current_nominant)
								{
									$('#control' + i).html('<button class="day-vote" onclick="mafia.vote(' + i + ', false)" checked>' + l('vote', i + 1, num + 1) + '</button>');
								}
								else if (n[i] > game.current_nominant)
								{
									$('#control' + i).html('<button class="day-vote" onclick="mafia.vote(' + i + ', true)" ' + ch_state + '>' + l('vote', i + 1, num + 1) + '</button>');
								}
							}
						}
					}
					break;
					
				case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
					if (voting.canceled > 0)
					{
						status = l('VotingCanceled') + ' ' + l('StartNight');
					}
					else
					{
						n = mafia.votingWinners();
						if (mafia.playersCount() == 4 && n.length == 2 && (mafia.gameRules.flags & /*RULES_FLAG_NO_CRASH_4*/8) != 0)
						{
							status = l('NoOneKilled');
						}
						else if (!mafia.isKillingThisDay())
						{
							status = l('MultipleDistrust', n.length) + ' ';
							if (n.length > 0)
							{
								status += l('NextFloor', mafia.playerTitle(n[0]));
							}
							else
							{
								status += ' ' + l('StartNight');
							}
						}
						else if (voting.voting_round > 0)
						{
							if (mafia.playersCount() == 3)
							{
								status = l('ThreeVoters', n.length);
							}
							else
							{
								status = l('AllOrNoOne', n.length);
								clockHtml = 
									'<button id="ka"' + (mafia.votingKillAll() ? ' checked' : '') + 
									' class="warn3" onclick="mafia.votingKillAll(true)">' + l('All') + 
									'</button><button id="kn"' + (mafia.votingKillAll() ? '' : 'checked') + 
									' class="warn3" onclick="mafia.votingKillAll(false)">' + l('Nobody') + '</button>';
							}
						}
						else
						{
							status = l('RepeatVoting', n.length) + ' ';
							if (n.length > 0)
							{
								status += l('NextFloor', mafia.playerTitle(n[0]));
							}
							else
							{
								status += ' ' + l('NextVoting');
							}
						}
					}
					break;
					
				case /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10:
					if (voting.canceled > 0)
					{
						status = l('VotingCanceled') + ' ' + l('StartNight');
					}
					else
					{
						n = voting.nominants;
						for (var i in n)
						{
							$('#num' + n[i].player_num).removeClass().addClass('day-mark');
						}
						$('#r' + game.player_speaking).removeClass().addClass('day-mark');
						st = mafia.gameRules.st_def;
						spt = mafia.gameRules.spt_def;
						
						status = l('Speaking', mafia.playerTitle(game.player_speaking)) + ' ';
						p = game.current_nominant + 1;
						if (p < n.length)
						{
							status += l('NextFloor', mafia.playerTitle(n[p].player_num));
						}
						else if (mafia.isKillingThisDay())
						{
							status += l('NextVoting');
						}
						else
						{
							status += l('StartNight');
						}
					}
					break;
				case /*GAME_STATE_NIGHT_START*/11:
					status = l('NightStart');
					info = 'Night';
					break;
				case /*GAME_STATE_NIGHT_SHOOTING*/12:
					var shots = mafia.shots();
					status = l('Shooting');
					html = ')"><option value="-1"></option>';
					for (var i = 0; i < 10; ++i)
					{
						p = game.players[i];
						if (p.state == /*PLAYER_STATE_ALIVE*/0)
						{
							html += '<option value="' + i + '">' + (i+1);
							if (p.id != 0)
								html += ': ' + mafia.userTitle(p.id);
							html += '</option>';
							
							var str = '<button class="night-char" onclick="mafia.shoot(' + i + ')">x</button>';
							for (var j in shots)
							{
								str += '<button class="night-char"';
								if (shots[j] == i)
								{
									str += ' checked';
								}
								str += ' onclick="mafia.shoot(' + i + ', ' + j + ')">' + (parseInt(j)+1) + '</button>';
							}
							$('#panel' + i).html(str);
						}
					}
					html += '</select>';
					for (var i in shots)
					{
						$('#control' + i).html('<select id="shot' + i + '" onchange="mafia.ui.shoot(' + i + html);
						$('#shot' + i).val(shots[i]);
					}
					info = 'Night';
					break;
				case /*GAME_STATE_NIGHT_DON_CHECK*/13:
					if (mafia.canDonCheck())
					{
						status = l('DonCheck');
						n = true;
						for (var i = 0; i < 10; ++i)
						{
							p = game.players[i];
							if (p.don_check == game.round)
							{
								var a = (p.role == /*ROLE_SHERIFF*/1) ? 'yes' : 'no';
								$('#control' + i).html('<button class="night-vote" onclick="mafia.donCheck(' + i + ')" checked> ' + l(a) + '</button>');
								n = false;
							}
							else if (p.don_check < 0 && p.role < /*ROLE_MAFIA*/2)
							{
								$('#control' + i).html('<button class="night-vote" onclick="mafia.donCheck(' + i + ')"> ' + l('Check', i + 1) + '</button>');
							}
						}
						$('#control-1').html('<button class="day-vote" onclick="mafia.donCheck(-1)"' + (n ? ' checked' : '') + '> ' + l('NoCheck') + '</button>');
					}
					else
					{
						status = l('NoDon');
					}
					info = 'Night';
					break;
					
				case /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15:
					if (mafia.canSheriffCheck())
					{
						status = l('SheriffCheck');
						n = true;
						for (var i = 0; i < 10; ++i)
						{
							p = game.players[i];
							if (p.sheriff_check == game.round)
							{
								var a = (p.role >= /*ROLE_MAFIA*/2) ? 'yes' : 'no';
								$('#control' + i).html('<button class="night-vote" onclick="mafia.sheriffCheck(' + i + ')" checked> ' + l(a) + '</button>');
								n = false;
							}
							else if (p.sheriff_check < 0 && p.role != /*ROLE_SHERIFF*/1)
							{
								$('#control' + i).html('<button class="night-vote" onclick="mafia.sheriffCheck(' + i + ')"> ' + l('Check', i + 1) + '</button>');
							}
						}
						$('#control-1').html('<button class="day-vote" onclick="mafia.sheriffCheck(-1)"' + (n ? ' checked' : '') + '> ' + l('NoCheck') + '</button>');
					}
					else
					{
						status = l('NoSheriff');
					}
					info = 'Night';
					break;
					
				case /*GAME_STATE_DAY_FREE_DISCUSSION*/20:
					status = l('FreeDisc');
					st = mafia.gameRules.st_free;
					spt = mafia.gameRules.spt_free;
					break;
			}
			
			$('#info').html(l(info, game.round + 1));
			if (voting != null && voting.canceled <= 0 && voting.nominants.length > 0)
			{
				$('#noms').html('<button class="noms" onclick="mafia.ui.showNominants()">' + l('ShowNoms') + '</button>');
			}
			else
			{
				$('#noms').html('');
			}
		}
		
		if (st > 0)
		{
			timer.show(st, spt, reset);
		}
		else
		{
			timer.hide(reset, clockHtml);
		}
		
		$('#status').html(status);
		if (onStatus != null)
		{
			onStatus();
		}
		mafia.ui.updateButtons();
		_oldState = game.gamestate;
	}
	
	this.showNominants = function()
	{
		var game = mafia.data().game;
		var voting = mafia.curVoting();
		var n = '<table class="bordered" width="100%">';
		for (var i = 0; i < voting.nominants.length; ++i)
		{
			p = game.players[voting.nominants[i].player_num];
			n += '<tr><td width="24" align="center">' + (p.number + 1) + '</td><td>' + mafia.userTitle(p.id) + '</td></tr>';
		}
		n += '</table>';
		dlg.info(n, l('Noms'));
	}

	this.fillEvents = function()
	{
		var timestamp = mafia.time();
		var data = mafia.data();
		var club = data.club;
		var game = data.game;
		var user = data.user;
		var str = '';
		var sEvents = mafia.sEvents();
		for (var i in sEvents)
		{
			var event_id = sEvents[i];
			var event = club.events[event_id];
			var start = parseInt(event.start_time);
			var end = start + parseInt(event.duration);
			if (start <= timestamp && end + user.manager * 28800 > timestamp)
			{
				var d = new Date(start * 1000);
				var n = event.name;
				if (event_id != 0)
				{
					n = d.getDate() + "/" + (d.getMonth() + 1) + "/" + d.getFullYear() + ": " + n;
					if (end + user.manager <= timestamp)
					{
						n += ' (' + l('EvOver') + ')';
					}
				}
				str += _option(event_id, game.event_id, n);
			}
		}
		$('#events').html(str);
		mafia.ui.eventChange(true);
	}
	
	this.eventChange = function(init)
	{
		var data = mafia.data();
		var club = data.club;
		var game = data.game;
		var event_id = $('#events').val();
		if (typeof event_id == 'undefined')
			event_id = game.event_id;
			
		var event = club.events[event_id];
		var user = data.user;
		
		mafia.eventId(event_id);
		
		if (!init)
		{
			mafia.setRules(event.rules_id);
		}

		var html = "";
		if ((event.langs - 1) & event.langs)
		{
			html += _option(0, game.lang, "");
		}
		if (event.langs & /*ENGLISH*/1)
		{
			html += _option(/*ENGLISH*/1, game.lang, l('Eng'));
		}
		if (event.langs & /*RUSSIAN*/2)
		{
			html += _option(/*RUSSIAN*/2, game.lang, l('Rus'));
		}
		_enable($('#lang').html(html), true);
		_enable($('#rules').val(game.rules_id), true);
		
		var sReg = mafia.sReg(event.id);
		if (event.flags & /*EVENT_FLAG_ALL_MODERATE*/8)
		{
			$('#reg-moder').show();
			html = _option(0, game.moder_id, "");
			for (var i in sReg)
			{
				var user_id = sReg[i];
				html += _option(user_id, game.moder_id, mafia.userTitle(user_id));
			}
		}
		else
		{
			$('#reg-moder').hide();
			html = _option(user.id, user.id, user.name);
		}
		_enable($('#player10').html(html), true);
		
		mafia.ui.fillUsers();
	}
	
	this.fillUsers = function()
	{
		var data = mafia.data();
		var club = data.club;
		var game = data.game;
		var event = club.events[game.event_id];
		var sReg = mafia.sReg(game.event_id);
		
		if (game.gamestate != /*GAME_STATE_END*/25)
		{
			html = _option(0, -1, "");
			for (var i in sReg)
			{
				var user_id = sReg[i];
				var u = club.players[user_id];
				if ((u.flags & /*U_PERM_PLAYER*/1) && (game.moder_id != user_id || (game.gamestate == /*GAME_STATE_NOT_STARTED*/0 && (event.flags & /*EVENT_FLAG_ALL_MODERATE*/8))))
				{
					html += _option(user_id, -1, mafia.userTitle(user_id));
				}
			}
		
			for (var i = 0; i < 10; ++i)
			{
				$('#name' + i).html(
					'<table class="invis"><tr>' +
						'<td><button id="reg-' + i + '" class="icon" onclick="mafia.ui.register(' + i + ')"><img src="images/user.png" class="icon"></button></td>' +
						'<td><button id="reg-new-' + i + '" class="icon" onclick="newUserForm.show(' + i + ')"><img src="images/create.png" class="icon"></button></td>' +
						'<td><select id="player' + i + '" onchange="mafia.ui.playerChange(' + i + ')"></select></td>' +
					'</tr></table>');
				$('#player' + i).html(html).val(game.players[i].id);
				if (event.id == 0)
					$('#reg-new-' + i).hide();
				else
					$('#reg-new-' + i).show();
			}
		}
		else for (var i = 0; i < 10; ++i)
		{
			$('#name' + i).html(mafia.userTitle(game.players[i].id));
		}
			
		if (event.id != 0 || window.navigator.userAgent.indexOf('MSIE') >= 0)
			$('#demo').hide();
		else
			$('#demo').show();
	}

	this.langChange = function()
	{
		mafia.setLang($('#lang').val());
	}

	this.rulesChange = function()
	{
		mafia.setRules($('#rules').val());
	}

	this.playerChange = function(num)
	{
		var pid = $('#player' + num).val();
		var n = mafia.player(num, pid);
		if (n >= 0)
		{
			$('#player' + n).val(-1);
		}
	}

	this.register = function(num)
	{
		regForm.show(num);
	}

	this.setRole = function(num)
	{
		mafia.setPlayerRole(num, $("#role-" + num).val());
	}

	this.arrangePlayer = function(num)
	{
		mafia.arrangePlayer(num, $("#arr-" + num).val());
	}

	this.shoot = function(num)
	{
		mafia.shoot(parseInt($('#shot' + num).val()), num);
	}
	
	this.restart = function()
	{
		dlg.yesNo(l('Restart'), null, null, mafia.restart);
	}

	this.next = function()
	{
		if (mafia.canNext())
		{
			var d = mafia.data();
			var g = d.game;
			if (g.gamestate == /*GAME_STATE_NOT_STARTED*/0)
			{
				var e = d.club.events[g.event_id];
				var now = mafia.time();
				var end = parseInt(e.start_time) + parseInt(e.duration);
				if (end < now)
				{
					var html =
						'<table class="dialog_form" width="100%"><tr><td colspan="2">' + l('EventExp') +
						'</td></tr><tr><td width="80">' + l('Extend') +
						'</td><td><select id="ext">';
					for (var i = 1; i <= 12; ++i)
					{
						html += '<option value="' + (i * 3600) + '">' + l('MoreHours', i) + '</option>';
					}
					for (var i = 1; i <= 5; ++i)
					{
						html += '<option value="' + (i * 86400) + '">' + l('MoreDays', i) + '</option>';
					}
					html += '</select></td></tr></table>';
					
					
					dlg.yesNo(html, null, null, function ()
					{
						mafia.extendEvent(e.id, $('#ext').val());
						mafia.next();
					});
					return;
				}
			}
			else if (g.gamestate == /*GAME_STATE_END*/25)
			{
				var html = null;
				if ((mafia.gameRules.flags & /*RULES_BEST_PLAYER*/0x100) != 0 && (g.best_player < 0 || g.best_player > 9))
				{
					html = l('NoBestPlayer')
				}
				else if ((mafia.gameRules.flags & /*RULES_BEST_MOVE*/0x200) != 0 && (g.best_move < 0 || g.best_move > 9))
				{
					html = l('NoBestMove')
				}
				if (html != null)
				{
					dlg.yesNo(html, null, null, function ()
					{
						mafia.next();
					});
					return;
				}
			}
			else if (g.gamestate == /*GAME_STATE_VOTING*/8 && (g.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2))
			{
				var w = mafia.votingWinners().length;
				var c = mafia.playersCount();
				var html = null;
				if (w == 0)
				{
					html = l('ErrEnterKills');
				}
				else if (c % w != 0)
				{
					html = l('ErrKillsNotAliquot', c, w);
				}
				if (html != null)
				{
					dlg.error(html);
					return;
				}
			}
			mafia.next();
		}
		else
		{
			gameStartForm.show(mafia.ui.next);
		}
	}
	
	this.suicide = function(num)
	{
		dlg.yesNo(l('ConfirmSuicide', mafia.playerTitle(num)), null, null, function()
		{
			mafia.suicide(num);
		});
	}
	
	this.kickOut = function(num)
	{
		dlg.yesNo(l('ConfirmKickOut', mafia.playerTitle(num)), null, null, function()
		{
			mafia.kickOut(num);
		});
	}
	
	this.warnPlayer = mafia.warnPlayer;
	this.back = mafia.back;
	this.save = mafia.save;
	
	this.nextRole = function(i)
	{
		var game = mafia.data().game;
		var p = game.players[i];
		var role = p.role + 1;
		if (role > /*ROLE_DON*/3) role = /*ROLE_CIVILIAN*/0;
		mafia.setPlayerRole(i, role);
	}
	
	this.keyUp = function(code)
	{
		var data = mafia.data();
		var game = data.game;
		
//		console.log(code);
		if (code == /*enter*/13)
		{
			mafia.ui.next();
		}
		else if (code == /*del*/46)
		{
			if (mafia.canBack()) mafia.ui.back();
		}
		else switch (game.gamestate)
		{
			case /*GAME_STATE_NOT_STARTED*/0:
				if (code >= /*1*/49 && code <= /*9*/57)
					mafia.ui.register(code - /*1*/49);
				else if (code == /*0*/48)
					mafia.ui.register(9);
				else if (code == /*insert*/45)
					eventForm.show();
				break;
			case /*GAME_STATE_NIGHT0_START*/1:
				if (code >= /*1*/49 && code <= /*9*/57)
					mafia.ui.nextRole(code - /*1*/49);
				else if (code == /*0*/48)
					mafia.ui.nextRole(9);
				break;
		}
	}
	
	this.settings = function()
	{
		settingsForm.show();
	}
	
	this.shortSpeech = function()
	{
		var game = mafia.data().game;
		_shortSpeech = true;
		$('#status').html(l('SpeakingShort', mafia.playerTitle(game.players[game.player_speaking].number)));
		timer.show(mafia.gameRules.st_def, mafia.gameRules.spt_def, true);
	}
	
	this.offline = function()
	{
		dlg.info(l('OfflineText', _mobile ? 'mob-' : ''), l('Offline'));
	}
} // mafia.ui

var nickForm = new function()
{
	var _user;

	this.show = function(user, onOk)
	{
		_user = user;
		var html =
			'<table class="dialog_form" width="100%">' +
			'<tr><td colspan="2">' + l('EnterNick', user.name) + ':</td></tr>' +
			'<tr><td width="90">' + l('Nick') + ':</td><td><select id="nf-nicks" onchange="nickForm.onSelect()"></select> <input id="nf-nick" onkeyup="nickForm.onChange()"></td></tr>' +
			'<tr id="nf-sex"><td>' + l('Gender') + ':</td><td><input type="radio" name="nf-sex" id="nf-male" checked> ' + l('male') + ' <input type="radio" name="nf-sex" id="nf-female"> ' + l('female') + '</td></tr>' +
			'</table><script>$(nickForm.init);</script>';
			
		dlg.okCancel(html, l('Nick'), 500, function()
		{
			var flags = _user.flags;
			if (typeof _user.flags == "undefined")
			{
				flags = $('#nf-male').attr('checked') ? 65 : 1;
			}
			onOk($('#nf-nick').val(), flags);
		});
	}

	this.init = function()
	{
		if (typeof _user.flags != "undefined")
		{
			$("#nf-sex").hide();
		}

		var str = '<option value=""></option><option value="' + _user.name + '">' + _user.name + '</option>';
		var nick = _user.name;
		var l = nick.toLocaleLowerCase()
		var max = 0;
		for (n in _user.nicks)
		{
			var use = parseInt(_user.nicks[n]);
			if (l == n.toLocaleLowerCase())
			{
				n = _user.name;
			}
			else
			{
				str += '<option value="' + n + '">' + n + '</option>';
			}
			
			if (use > max)
			{
				max = use;
				nick = n;
			}
		}
		$('#nf-nick').val(nick);
		$('#nf-nicks').html(str).val(nick);
	}
		
	this.onSelect = function()
	{
		$('#nf-nick').val($('#nf-nicks').val()).focus();
	}

	this.onChange = function()
	{
		$('#nf-nicks').val('');
	}
} // nickForm

var eventForm = new function()
{
	var old_address_value;

	this.show = function()
	{
		var html =
			'<table class="dialog_form" width="100%">' +
			'<tr><td width="160">' + l('EventName') + ':</td><td><input id="form-name"></td></tr>' +
			'<tr><td>' + l('Duration') + ':</td><td>' +
			'<select id="form-duration">' +
			'<option value="3600">1</option>' +
			'<option value="7200">2</option>' +
			'<option value="10800">3</option>' +
			'<option value="14400">4</option>' +
			'<option value="18000">5</option>' +
			'<option value="21600">6</option>' +
			'<option value="25200">7</option>' +
			'<option value="28800">8</option>' +
			'<option value="32400">9</option>' +
			'<option value="36000">10</option>' +
			'<option value="39600">11</option>' +
			'<option value="43200">12</option>' +
			'<option value="86400">24</option>' +
			'<option value="172800">48</option>' +
			'<option value="259200">72</option>' +
			'<option value="345600">96</option>' +
			'<option value="432000">120</option>' +
			'</select> ' + l('hours') + '</td></tr>' +
			'<tr><td>' + l('Address') + ':</td><td>' +
			'<select id="form-addr" onChange="eventForm.addrClick()"></select><div id="form-new_addr_div">' + l('Address') +
			': <input id="form-new_addr" onkeyup="eventForm.addrChange()"><br>' + l('City') +
			': <input id="form-city"><br>' + l('Country') +
			': <input id="form-country">' +
			'</span></td></tr>' +
			'<tr><td>' + l('Price') + ':</td><td><input id="form-price"></td></tr>' +
			'<tr><td>' + l('Rules') + ':</td><td><select id="form-rules"></select></td></tr>' +
			'<tr><td>' + l('Langs') + ':</td><td id="form-langs"></td></tr>' +
			'<tr><td colspan="2">' +
			'<input type="checkbox" id="form-all_mod" checked> ' + l('AllModer') + '</td></tr>' +
			'</table><script>$(eventForm.init);</script>';
			
		dlg.okCancel(html, l('CreateEvent'), 600, function()
		{
			try
			{
				var aid = $("#form-addr").val();
				var f = $('#form-all_mod').attr('checked') ? /*EVENT_FLAG_ALL_MODERATE*/8 : 0;
				var l = 0;
				if ($('#form-en').attr('checked')) l |= /*ENGLISH*/1;
				if ($('#form-ru').attr('checked')) l |= /*RUSSIAN*/2;
				var params =
				{
					name: $('#form-name').val(),
					duration: $('#form-duration').val(),
					price: $('#form-price').val(),
					rules: $('#form-rules').val(),
					langs: l,
					flags: f
				};
				
				if (aid > 0)
				{
					params['addr_id'] = aid;
				}
				else
				{
					params['addr'] = $('#form-new_addr').val();
					params['country'] = $('#form-country').val();
					params['city'] = $('#form-city').val();
				}

				mafia.eventId(mafia.createEvent(params));
				mafia.ui.fillEvents();
			}
			catch (e)
			{	
				handleError(e);
			}
		});
	}

	this.addrChange = function()
	{
		var text = $("#form-new_addr").val();
		if ($("#form-name").val() == old_address_value)
		{
			$("#form-name").val(text);
		}
		old_address_value = text;
	}
	
	this.addrClick = function()
	{
		var text = '';
		if ($("#form-addr").val() <= 0)
		{
			$("#form-new_addr_div").show();
		}
		else
		{
			$("#form-new_addr_div").hide();
			text = $("#form-addr option:selected").text();
		}
		
		if ($("#form-name").val() == old_address_value)
		{
			$("#form-name").val(text);
		}
		old_address_value = text;
	}
	
	this.langCheck = function(lang)
	{
		if (!$('#form-en').attr('checked') && !$('#form-ru').attr('checked'))
		{
			if (lang == 1)
			{
				$('#form-en').attr('checked', true);
			}
			else
			{
				$('#form-ru').attr('checked', true);
			}
		}
	}

	this.init = function()
	{
		old_address_value = "";
	
		var club = mafia.data().club;
		var str = '<option value="0">' + l('NewAddr') + '</option>';
		for (var i = 0; i < club.addrs.length; ++i)
		{
			var a = club.addrs[i];
			str += '<option value="' +  a.id + '"';
			if (i == 0)
			{
				str += ' selected';
			}
			str += '>' + a.name + '</option>';
		}
		$('#form-addr').html(str);
		$('#form-duration').val(21600);
		$('#form-price').val(club.price);
		$('#form-country').val(club.country);
		$('#form-city').val(club.city);
		
		str = "";
		var sRules = mafia.sRules();
		for (var i in sRules)
		{
			var rules_id = sRules[i];
			var name = club.rules[rules_id].name;
			if (name.length == 0)
			{
				name = l('DefRules');
			}
			str += '<option value="' + rules_id + '">' + name + '</option>';
		}
		$('#form-rules').html(str);
		
		str = '';
		if (club.langs & /*ENGLISH*/1)
		{
			str += '<input type="checkbox" id="form-en" onclick="eventForm.langCheck(1)" checked> ' + l('Eng') + ' ';
		}
		if (club.langs & /*RUSSIAN*/2)
		{
			str += '<input type="checkbox" id="form-ru" onclick="eventForm.langCheck(2)" checked> ' + l('Rus') + ' ';
		}
		$('#form-langs').html(str);
		
		eventForm.addrClick();
	}
} // eventForm

var regForm = new function()
{
	var _num;

	this.show = function(num)
	{
		_num = num;
		
		var html = '<table class="dialog_form" width="100%"><tr><td><table class="invis" width="100%"><tr><td><input id="form-name" onkeyup="regForm.nameChange()">';
		html += '</td></tr></table></td></tr><tr><td><table class="reg-list" width="100%">';
		for (var i = 0; i < 10; ++i)
		{
			html += '<tr>';
			for (var j = 0; j < 5; ++j)
			{
				html += '<td width="20%" id="form-u' + (i + j * 10) + '">&nbsp;</td>';
			}
			html += '</tr>';
		}
		html += '</table></td></tr></table><script>$(regForm.init);</script>';
		
		dlg.okCancel(html, l('Register'), 800, function()
		{
			var name = $('#form-name').val();
			if (name == '')
			{
				dlg.error(l('ErrNoUserName'));
			}
			else
			{
				var p = mafia.findPlayer(name);
				if (p != null)
				{
					_register(p.id);
				}
				else
				{
					_regIncomer(name, '', 0, 0);
				}
			}
		});
	}
	
	this.init = function()
	{
		regForm.nameChange();
	}

	this.nameChange = function()
	{
		var club = mafia.data().club;
		var name = $('#form-name').val().toLocaleLowerCase();
		var num = 0;
		if (http.connected())
		{
			for (; num < /*NUM_USERS*/50; ++num)
			{
				$('#form-u' + num).html('&nbsp;');
			}
			
			var w = http.waiter(silentWaiter);
			json.post('api/ops/game.php', { op: 'ulist', club_id: club.id, name: name, num: /*NUM_USERS*/50 }, function(data)
			{
				num = 0;
				for (var i in data.list)
				{
					var p = data.list[i];
					var nick = '';
					for (var nck in p.nicks)
					{
						nick = nck;
						break;
					}
					var h = '<a href="#" onclick="regForm.regIncomer(\'' + p.name + '\', \'' + nick + '\', ' + p.id + ', ' + p.flags  + ')" title="' + p.club + '">' + p.name;
					if (nick != '')
					{
						h += ' (' + nick + ')';
					}
					h += '</a>';
					$('#form-u' + num).html(h);
					++num;
				}
			});
			http.waiter(w);
		}
		else
		{
			function match(q, n)
			{
				return n.indexOf(q) == 0 || n.indexOf(' ' + q) >= 0 || n.indexOf('_' + q) >= 0 || n.indexOf('-' + q) >= 0;
			}
		
			function putPlayer(player, num)
			{
				if (name == '')
				{
					$('#form-u' + num).html('<a href="#" onclick="regForm.register(' + player.id + ')" title="' + player.club + '">' + player.name + '</a>');
					++num;
				}
				else
				{
					var p = player.name.toLocaleLowerCase();
					if (match(name, p))
					{
						$('#form-u' + num).html('<a href="#" onclick="regForm.register(' + player.id + ')" title="' + player.club + '">' + player.name + '</a>');
						if (++num >= /*NUM_USERS*/50) return num;
					}
					for (var nick in player.nicks)
					{
						var n = nick.toLocaleLowerCase();
						if (p != n && match(name, n))
						{
							$('#form-u' + num).html('<a href="#" onclick="regForm.register(' + player.id + ')" title="' + player.club + '">' + player.name + ' (' + nick + ')</a>');
							if (++num >= /*NUM_USERS*/50) return num;
						}
					}
				}
				return num;
			}
		
			var players = club.players;
			var sPlayers = (name == '' ? club.haunters : mafia.sPlayers());
			var mask = /*U_PERM_PLAYER*/1;
			if (_num >= 10)
			{
				mask += /*U_PERM_MODER*/2;
			}
			for (var i in sPlayers)
			{
				var p = players[sPlayers[i]];
				if ((p.flags & mask) != 0)
				{
					num = putPlayer(p, num);
					if (num >= /*NUM_USERS*/50) break;
				}
			}
			
			for (; num < /*NUM_USERS*/50; ++num)
			{
				$('#form-u' + num).html('&nbsp;');
			}
		}
	}
	
	function _register(id)
	{
		var data = mafia.data();
		var game = data.game;
		var club = data.club;
		var players = club.players;
		var event = club.events[game.event_id];
		if (typeof event.reg[id] != "undefined")
		{
			$('#player' + _num).val(id);
			mafia.ui.playerChange(_num);
			if (typeof onSuccess != "undefined")
			{
				onSuccess();
			}
		}
		else
		{
			if (typeof onSuccess != "undefined")
			{
				onSuccess();
			}
			
			nickForm.show(players[id], function(nick)
			{
				try
				{
					mafia.register(nick, id);
					mafia.player(_num, id);
					mafia.ui.eventChange(false);
				}
				catch (e)
				{
					handleError(e);
				}
			});
		}
	}
	
	function _regIncomer(pname, pnick, pid, pflags)
	{
		var data = mafia.data();
		var event = data.club.events[data.game.event_id];
		if (typeof event.reg[pid] != "undefined")
		{
			$('#player' + _num).val(pid);
			mafia.ui.playerChange(_num);
		}
		else
		{
			var pnicks = {};
			if (pnick != '')
			{
				pnicks[pnick] = 1;
			}
			var p =
			{
				name: pname,
				nicks: pnicks
			};
			if (pid != 0)
			{
				p['id'] = pid;
				p['flags'] = pflags;
			}
			
			nickForm.show(p, function(nick, flags)
			{
				try
				{
					pid = mafia.regIncomer(pname, nick, pid, flags);
					mafia.player(_num, pid);
					mafia.ui.eventChange(false);
				}
				catch (e)
				{
					handleError(e);
				}
			});
		}
	}
	
	this.register = function(id)
	{
		var dlgId = dlg.curId();
		_register(id);
		dlg.close(dlgId);
	}
	
	this.regIncomer = function(pname, pnick, pid, pflags)
	{
		var dlgId = dlg.curId();
		_regIncomer(pname, pnick, pid, pflags)
		dlg.close(dlgId);
	}
} // regForm

var newUserForm = new function()
{
	this.show = function(num, error, name, email, sex)
	{
		if (typeof name != 'string')
			name = '';
	
		if (typeof email != 'string')
			email = '';
			
		if (typeof sex == 'undefined')
			sex = true;
			
		var male = sex ? ' checked' : '';
		var female = sex ? '' : ' checked';
	
		var html = '<table class="dialog_form" width="100%">';
		if (typeof error == 'string')
			html += '<tr><td colspan="2"><b>' + error + '</b></td></tr>';
		html +=
			'<tr><td width="140">' + l('UserName') + ':</td><td><input id="form-name" value="' + name + '"></td></tr>' +
			'<tr><td>' + l('Email') + ':</td><td><input id="form-email" value="' + email + '"></td></tr>' +
			'<tr><td>' + l('Gender') + ':</td><td><input type="radio" name="form-sex" id="form-male"' + male + '> ' + l('male') +
			' <input type="radio" name="form-sex" id="form-female"' + female + '> ' + l('female') + 
			'</td></tr></table>';
			
		dlg.okCancel(html, l('CreateUser'), 500, function()
		{
			var name = $("#form-name").val();
			var flags = $("#form-male").attr("checked") ? 65 : 1;
			var email = $("#form-email").val();
			var p =
			{
				name: name,
				flags: flags,
				nicks: {}
			};
			
			function createUser()
			{
				nickForm.show(p, function(nick)
				{
					try
					{
						var id = mafia.createUser(name, nick, email, flags);
						mafia.player(num, id);
						mafia.ui.eventChange(false);
					}
					catch (e)
					{
						handleError(e);
					}
				});
			}
		
			var error = mafia.checkUser(name);
			if (error != null)
			{
				newUserForm.show(num, error, name, email, flags & /*U_FLAG_MALE*/64);
			}
			else if ($("#form-email").val().trim() == '')
			{
				dlg.yesNo(l('EmptyEmail'), null, null, createUser);
			}
			else
			{
				createUser();
			}
		});
	}
} // newUserForm

var selectClubForm = new function()
{
	function _club(id)
	{
		if (mafia.data().club.id != id)
			mafia.sync(parseInt($("#sc-club").val()), 0, function()
			{
				mafia.ui.fillUsers();
			});
	}

	this.show = function()
	{
		var clubs = mafia.data().user.clubs;
		if (clubs == null || clubs.length == 0)
		{
			dlg.error(l('ErrNoClubs'));
		}
		else
		{
			var html = 
				'<table class="dialog_form" width="100%">' +
				'<tr><td width="120">' + l('Club') + ':</td><td><select id="sc-club"></select></td></tr>' +
				'</table><script>selectClubForm.init()</script>';
		
			dlg.info(html, l('SelectClub'), null, function() { _club(parseInt($("#sc-club").val())); });
		}
	}
	
	this.init = function()
	{
		var clubs = mafia.data().user.clubs;
		var str = '';
		var data = mafia.data();
		var id = (data != null) ? data.game.club_id : -1;
		for (i = 0; i < clubs.length; ++i)
		{
			var club = clubs[i];
			str += '<option value="' + club.id + '"' + (club.id == id ? ' selected' : '') + '>' + club.name + '</option>';
		}
		$("#sc-club").html(str);
	}
} // selectClubForm

var settingsForm = new function()
{
	this.show = function()
	{
		var html = 
			'<table class="dialog_form" width="100%">' +
			'<tr><td width="200">' + l('SaveLocal') + ':</td><td><select id="l-autosave"><option value="0">' + l('off') + '</option><option value="-1">' + l('OnGameEnd') + '</option><option value="60">' + l('1min') + '</option><option value="30">' + l('30sec') + '</option><option value="20">' + l('20sec') + '</option><option value="10">' + l('10sec') + '</option></select></td></tr>' +
			'<tr><td>' + l('Sync') + ':</td><td><select id="g-autosave"><option value="0">' + l('off') + '</option><option value="-1">' + l('OnGameEnd') + '</option><option value="1800">' + l('30min') + '</option><option value="600">' + l('10min') + '</option><option value="300">' + l('5min') + '</option><option value="120">' + l('2min') + '</option><option value="60">' + l('1min') + '</option></select></td></tr>' +
			'<tr><td>' + l('TStart') + ':</td><td><select id="t-start"><option value="1">' + l('on') + '</option><option value="0">' + l('off') + '</option></select></td></tr>' +
			'<tr><td>' + l('TSounds') + ':</td><td><select id="t-sound"><option value="1">' + l('on') + '</option><option value="0">' + l('off') + '</option></select></td></tr>' +
			'<tr><td>' + l('TBlinking') + ':</td><td><select id="t-blink"><option value="1">' + l('on') + '</option><option value="0">' + l('off') + '</option></select></td></tr>';
		if (mafia.gameRules.flags & /*RULES_ANY_CLIENT*/0x1000)
			html += '<tr><td>' + l('SimpVoting') + ':</td><td><select id="s-client"><option value="1">' + l('on') + '</option><option value="0">' + l('off') + '</option></select></td></tr>';
		html += '</table><script>settingsForm.init()</script>';
	
		dlg.okCancel(html, l('Settings'), 500, function()
		{
			var flags = 0;
			if ($('#t-start').val() != 0) flags |= /*S_FLAG_START_TIMER*/0x2;
			if ($('#t-sound').val() == 0) flags |= /*S_FLAG_NO_SOUND*/0x4;
			if ($('#t-blink').val() == 0) flags |= /*S_FLAG_NO_BLINKING*/0x8;
			if ($('#s-client').val() != 0) flags |= /*S_FLAG_SIMPLIFIED_CLIENT*/0x1;
			mafia.settings($('#l-autosave').val(), $('#g-autosave').val(), flags);
		});
	}
	
	this.init = function()
	{
		var s = mafia.data().user.settings;
		var f = s.flags;
		$('#l-autosave').val(s.l_autosave);
		$('#g-autosave').val(s.g_autosave);
		$('#t-start').val((f & /*S_FLAG_START_TIMER*/0x2) ? 1 : 0);
		$('#t-sound').val((f & /*S_FLAG_NO_SOUND*/0x4) ? 0 : 1);
		$('#t-blink').val((f & /*S_FLAG_NO_BLINKING*/0x8) ? 0 : 1);
		$('#s-client').val((f & /*S_FLAG_SIMPLIFIED_CLIENT*/0x1) ? 1 : 0);
	}
} // settingsForm

var gameStartForm = new function()
{
	this.show = function(onOk)
	{
		var data = mafia.data();
		var club = data.club;
		var game = data.game;
		var event = club.events[game.event_id];
		
		var html = '<table class="dialog_form" width="100%">';
		if (game.moder_id == 0)
		{
			var sReg = mafia.sReg(event.id);
			html += '<tr><td width="200">' + l('Moder') + ':</td><td><select id="form-moder" onchange="gameStartForm.moder()"><option value="0" selected></option>';
			for (var i in sReg)
			{
				var user_id = sReg[i];
				html += '<option value="' + user_id + '">' + mafia.userTitle(user_id) + '</option>';
			}
			html += '</select></td></tr>';
		}
		
		if (game.lang == 0)
		{
			var langs = event.langs;
			if ((langs & 3) == 0) langs = club.langs;
			if ((langs & 3) == 0) langs = 3;
			
			html += '<tr><td width="200">' + l('Lang') + ':</td><td><select id="form-lang" onchange="gameStartForm.lang()"><option value="0"></option>';
			if (langs & /*ENGLISH*/1)
			{
				html += '<option value="' + /*ENGLISH*/1 + '">' + l('Eng') + '</option>';
			}
			if (langs & /*RUSSIAN*/2)
			{
				html += '<option value="' + /*RUSSIAN*/2 + '">' + l('Rus') + '</option>';
			}
			html += '</select></td></tr>';
		}
		
		if (game.rules_id <= 0)
		{
			var sRules = mafia.sRules();
			html += '<tr><td width="200">' + l('Rules') + ':</td><td><select id="form-rules" onchange="gameStartForm.rules()"><option value="0"></option>';
			for (var i in sRules)
			{
				var rules_id = sRules[i];
				var name = club.rules[rules_id].name;
				if (name.length == 0)
				{
					name = l('DefRules');
				}
				status += '<option value="' + rules_id + '">' + name + '</option>';
			}
			status += '</select></td></tr>';
		}
		html += '</table><script>gameStartForm.init()</script>';
		
		dlg.okCancel(html, l('PleaseEnter'), 500, onOk);
	}
	
	this.moder = function()
	{
		mafia.player(10, $('#form-moder').val());
		mafia.ui.eventChange(false);
		gameStartForm.init();
	}
	
	this.lang = function()
	{
		mafia.setLang($('#form-lang').val());
		gameStartForm.init();
	}
	
	this.rules = function()
	{
		mafia.setRules($('#form-rules').val());
		gameStartForm.init();
	}
	
	this.init = function()
	{
		$('dlg-ok').button("option", "disabled", !mafia.canNext());
	}
} // gameStartForm


setInterval(timer.tick, 1000);
