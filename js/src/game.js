// /*GAME_FLAG_LOG_CORRUPTED*/1
// /*GAME_FLAG_SIMPLIFIED_CLIENT*/2

// /*LOGREC_TYPE_NORMAL*/0
// /*LOGREC_TYPE_MISSED_SPEECH*/1 // deprecated now it's normal - we know that player misses speech by player.mute var
// /*LOGREC_TYPE_WARNING*/2
// /*LOGREC_TYPE_SUICIDE*/3
// /*LOGREC_TYPE_KICK_OUT*/4
// /*LOGREC_TYPE_POSTPONE_MUTE*/5
// /*LOGREC_TYPE_CANCEL_VOTING*/6
// /*LOGREC_TYPE_RESUME_VOTING*/7

// /*EVENT_FLAG_CANCELED*/4
// /*EVENT_FLAG_ALL_MODERATE*/8
// /*EVENT_FLAG_CHAMPIONSHIP*/0x20

// /*ROLE_CIVILIAN*/0
// /*ROLE_SHERIFF*/1
// /*ROLE_MAFIA*/2
// /*ROLE_DON*/3

// /*PLAYER_STATE_ALIVE*/0
// /*PLAYER_STATE_KILLED_NIGHT*/1
// /*PLAYER_STATE_KILLED_DAY*/2

// /*PLAYER_KR_ALIVE*/-1
// /*PLAYER_KR_NORMAL*/0
// /*PLAYER_KR_SUICIDE*/1
// /*PLAYER_KR_WARNINGS*/2
// /*PLAYER_KR_KICKOUT*/3

// /*U_PERM_PLAYER*/1
// /*U_PERM_MODER*/2
// /*U_PERM_MANAGER*/4
// /*U_FLAG_MALE*/64
// /*U_FLAG_IMMUNITY*/1024

// /*S_FLAG_SIMPLIFIED_CLIENT*/0x1
// /*S_FLAG_START_TIMER*/0x2
// /*S_FLAG_NO_SOUND*/0x4
// /*S_FLAG_NO_BLINKING*/0x8

// /*GAME_STATE_NOT_STARTED*/0
// /*GAME_STATE_NIGHT0_START*/1
// /*GAME_STATE_NIGHT0_ARRANGE*/2
// /*GAME_STATE_DAY_START*/3
// /*GAME_STATE_DAY_PLAYER_SPEAKING*/5
// /*GAME_STATE_VOTING_KILLED_SPEAKING*/7
// /*GAME_STATE_VOTING*/8
// /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9
// /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10
// /*GAME_STATE_NIGHT_START*/11
// /*GAME_STATE_NIGHT_SHOOTING*/12
// /*GAME_STATE_NIGHT_DON_CHECK*/13
// /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15
// /*GAME_STATE_MAFIA_WON*/17
// /*GAME_STATE_CIVIL_WON*/18
// /*GAME_STATE_DAY_FREE_DISCUSSION*/20
// /*GAME_STATE_DAY_GUESS3*/21 // deprecated
// /*GAME_STATE_BEST_PLAYER*/22 // deprecated
// /*GAME_STATE_BEST_MOVE*/23 // deprecated
// /*GAME_STATE_END*/25

// /*RULES_FIRST_DAY_VOTING*/8
// /*RULES_FIRST_DAY_VOTING_TO_TALK*/1
// /*RULES_SPLIT_ON_FOUR*/11
// /*RULES_SPLIT_ON_FOUR_PROHIBITED*/1
// /*RULES_KILLED_NOMINATE*/13
// /*RULES_KILLED_NOMINATE_ALLOWED*/1
// /*RULES_ANTIMONSTER*/19
// /*RULES_ANTIMONSTER_NO*/1
// /*RULES_ANTIMONSTER_NOMINATED*/2
// /*RULES_BEST_GUESS*/15
// /*RULES_BEST_GUESS_YES*/0

// /*STATE_CHANGE_FLAG_RESET_TIMER*/1
// /*STATE_CHANGE_FLAG_CLUB_CHANGED*/2
// /*STATE_CHANGE_FLAG_GAME_WAS_ENDED*/4

//------------------------------------------------------------------------------------------
// game data
//------------------------------------------------------------------------------------------
var mafia = new function()
{
	var _version = 0;
	var _lDirty = 0;
	var _gDirty = 0;
	var _data = null;
	var _clubs = null;
	
	var _sortedPlayers;
	var _sortedRegs;
	var _sortedEvents;
	
	var _stateChange = null;
	var _dirtyEvent = null;
	var _failEvent = null;
	var _editing;
	
	var _curEventId = 0;
	var _curUserId = 0;
	
	var _curVoting = null;
	var _voting = null;
	
	var _syncCount = 0;
	var _demoEvent = null;
	
	this.sPlayers = function() { return _sortedPlayers; }
	this.sReg = function(eventId) { return _sortedRegs[eventId]; }
	this.sEvents = function() { return _sortedEvents; }
	
	function _dirty(l, g)
	{
		l = l > 0 ? l : 0;
		g = g > 0 ? g : 0;
		var b = (l > 0) != (_lDirty > 0) || (g > 0) != (_gDirty > 0);
		_lDirty = l;
		_gDirty = g;
		if (b && _dirtyEvent != null)
		{
			_dirtyEvent(l > 0, g > 0);
		}
	}
	
	function _callStateChange(flags)
	{
		if (_stateChange != null)
		{
			_stateChange(flags);
		}
	}
	
	this.load = function(clubId)
	{
		if (typeof localStorage == "object")
		{
			var str = localStorage['data'];
			if (typeof str != "undefined" && str != null)
			{
				var data = jQuery.parseJSON(str);
				if (clubId <= 0 || data.clubId == clubId)
				{
					mafia.data(data);
					_dirty(0, _data.dirty);
				}
			}
		}
	}
	
	this.save = function(forse)
	{
		if (typeof forse != "boolean") forse = false;
		if (_data != null && (_lDirty > 0 || forse))
		{
			if (typeof localStorage == "object")
			{
				_data.dirty = _gDirty;
				localStorage['data'] = JSON.stringify(_data);
			}
			_dirty(0, _gDirty);
		}
	}
	
	this.stateChange = function(e)
	{
		var _e = _stateChange;
		if (typeof e == "function")
		{
			_stateChange = e;
		}
		return _e;
	}
	
	this.editing = function(e)
	{
		var _e = _editing;
		if (typeof e == "boolean")
		{
			_editing = e;
		}
		return _e;
	}
	
	this.failEvent = function(f)
	{
		var _f = _failEvent;
		if (typeof f == "function")
		{
			_failEvent = f;
		}
		return _f;
	}
	
	function dirty() { _dirty(1, _gDirty + 1); }
	this.localDirty = function() { return _lDirty > 0; }
	this.globalDirty = function() { return _gDirty > 0; }
	this.dirtyEvent = function(od)
	{
		var _od = _dirtyEvent;
		if (typeof od == "function")
		{
			_dirtyEvent = od;
		}
		return _od;
	}
	
	this.data = function(d)
	{
		if (typeof d == "object")
		{
			var stateChangeFlags = 0;
			if (_data == null)
			{
				stateChangeFlags = /*STATE_CHANGE_FLAG_RESET_TIMER*/1 + /*STATE_CHANGE_FLAG_CLUB_CHANGED*/2;
			}
			else if (_data.club.id != d.club.id)
			{
				stateChangeFlags = /*STATE_CHANGE_FLAG_CLUB_CHANGED*/2;
			}
			function userSort(a, b) { return players[a].name.toLocaleLowerCase().localeCompare(players[b].name.toLocaleLowerCase()); }
			_data = d;
			var club = d.club;
			var players = club.players;
			var game = d.game;
			_sortedPlayers = [];
			for (var id in players)
			{
				_sortedPlayers.push(id);
				id = parseInt(id);
				if (id < _curUserId)
				{
					_curUserId = id;
				}
			}
			_sortedPlayers.sort(userSort);
			
			club.haunters.sort(userSort);
			
			var events = club.events;
			if (typeof events[0] != "undefined")
			{
				_demoEvent = events[0];
			}
			else if (_demoEvent == null || _demoEvent.club_id != club.id)
			{
				_demoEvent =  
				{
					id: 0,
					club_id: club.id,
					rules_code: club.rules_code,
					name: l("DemoEvent"),
					start_time: 0,
					duration: 4294967295,
					langs: club.langs,
					flags: 0,
					reg: { }
				};
			}
			events[0] = _demoEvent;
			
			var event = events[game.event_id];
			if (typeof event == "undefined")
			{
				game.event_id = 0;
				event = _demoEvent;
			}
			
			for (var i = 0; i < 10; ++i)
			{
				var p = game.players[i];
				var r = event.reg;
				if (p.id != 0)
				{
					var u = players[p.id];
					if (typeof u == "undefined")
					{
						p.id = 0;
					}
					else if (typeof r[p.id] == "undefined")
					{
						r[p.id] = u.name;
					}
				}
			}
			
			_sortedEvents = [];
			_sortedRegs = {};
			for (var id in events)
			{
				_sortedEvents.push(id);
				if (id < _curEventId)
				{
					_curEventId = id;
				}
				
				var reg = events[id].reg;
				var a = [];
				for (var i in reg)
				{
					a.push(i);
				}
				a.sort(function(a, b) { return reg[a].toLocaleLowerCase().localeCompare(reg[b].toLocaleLowerCase()); });
				_sortedRegs[id] = a;
			}
			_sortedEvents.sort(function(a, b) { return events[a].start_time - events[b].start_time; });
			
			if (typeof _data['requests'] == "undefined")
				_data['requests'] = [];
			
			_curVoting = null;
			if (game.votings != null && game.votings.length > 0)
			{
				_curVoting = game.votings[game.votings.length - 1];
			}
			
			if (game.lang == 0 || ((game.lang - 1) & game.lang) != 0)
			{
				if (((event.langs - 1) & event.langs) == 0)
				{
					game.lang = event.langs;
				}
				else if (((club.langs - 1) & club.langs) == 0)
				{
					game.lang = club.langs;
				}
				else
				{
					game.lang = 0;
				}
			}
			
			if (game.moder_id == 0)
			{
				game.moder_id = (event.flags & /*EVENT_FLAG_ALL_MODERATE*/8) ? 0 : _data.user.id;
			}
			
			_callStateChange(stateChangeFlags);
			
			if (typeof _data.fail == 'string' && _failEvent != null)
			{
				_failEvent(_data.fail, false);
			}
		}
		return _data;
	}
	
	this.time = function()
	{
		return Math.round((new Date()).getTime() / 1000);
	}
	
	this.sync = function(clubId, eventId, success)
	{
		++_syncCount;
		if (_syncCount > 1 && _syncCount < 5)
		{
			return;
		}
		_syncCount = 1;
		
		if (typeof clubId != "number")
			clubId = 0;
		if (typeof eventId != "number")
			eventId = 0;
		
		if (_data == null)
		{
			mafia.load(clubId);
		}
	
		request = { op: 'sync' };
		if (_data != null)
		{
			if (clubId <= 0)
			{
				clubId = _data.club.id;
			}
			
			if (eventId <= 0)
			{
				eventId = _data.game.event_id;
			}
			
			if (_gDirty > 0)
			{
				request['game'] = JSON.stringify(_data.game);
				if (_data.requests.length > 0)
				{
					request['data'] = JSON.stringify(_data.requests);
				}
			}
		}
		
		if (clubId > 0)
		{
			request['club_id'] = clubId;
		}
		
		var oldDirty = _gDirty;
		json.post('api/ops/game.php', request, function(data)
		{
			if (typeof data.console == "object")
				for (var i = 0; i < data.console.length; ++i)
					console.log('Sync log: ' + data.console[i]);
				
			if (_version != data.version)
			{
				if (_failEvent != null)
				{
					_failEvent(l('ErrVersion', _version, data.version), true);
				}
				else
				{
					window.location.reload(true);
				}
			}
				
			_dirty(_lDirty, _gDirty - oldDirty);
			_syncCount = 0;
			if (typeof data.club != 'undefined')
			{
				if (typeof data.club.events[eventId] != "undefined")
				{
					data.game.event_id = eventId;
				}
				switch (data.game.gamestate)
				{
					case /*GAME_STATE_MAFIA_WON*/17:
					case /*GAME_STATE_CIVIL_WON*/18:
						data.game.gamestate = /*GAME_STATE_END*/25;
						break;
				}
				mafia.data(data);
			}
			mafia.save(true);
			if (typeof success != "undefined")
			{
				success();
			}
		}, function () { _syncCount = 0; http.connected(false); });
	}
	
	this.player = function(num, id)
	{
		var game = _data.game;
		var club = _data.club;
		var event = club.events[game.event_id];
		var result = -1;
		if (num == 10)
		{
			if (game.gamestate == /*GAME_STATE_NOT_STARTED*/0 && (event.flags & /*EVENT_FLAG_ALL_MODERATE*/8))
			{
				game.moder_id = id;
				if (id != 0)
				{
					for (var i = 0; i < 10; ++i)
					{
						var p = game.players[i];
						if (p.id == id)
						{
							p.id = 0;
							p.nick = "";
							p.warnings = 0;
							result = i;
						}
					}
				}
				dirty();
			}
		}
		else
		{
			var nick = '';
			var is_male = 1;
			var has_immunity = 0;
			if (id != 0)
			{
				var u = club.players[id];
				nick = event.reg[id];
				is_male = (u.flags & /*U_FLAG_MALE*/64) ? 1 : 0;
				has_immunity = (u.flags & /*U_FLAG_IMMUNITY*/1024) ? 1 : 0;
				
				if (id == game.moder_id)
				{
					if (game.gamestate != /*GAME_STATE_NOT_STARTED*/0 || (event.flags & /*EVENT_FLAG_ALL_MODERATE*/8) == 0)
						return -1;
					game.moder_id = 0;
					result = 10;
				}
				for (var i = 0; i < 10; ++i)
				{
					var p = game.players[i];
					if (i != num && p.id == id)
					{
						p.id = 0;
						p.nick = "";
						result = i;
					}
				}
			}
			var p = game.players[num];
			p.id = id;
			p.nick = nick;
			p.is_male = is_male;
			p.has_immunity = has_immunity;
			dirty();
		}
		return result;
	}
	
	this.userTitle = function(pid)
	{
		if (pid == 0) return '';
		var t =  _data.club.players[pid].name;
		var event = _data.club.events[_data.game.event_id];
		if (typeof event != "undefined")
		{
			var nick = event.reg[pid];
			if (typeof nick != "undefined" && nick.toLocaleLowerCase() != t.toLocaleLowerCase())
			{
				t = nick + ' (' + t + ')';
			}
		}
		return t;
	}
	
	this.playerTitle = function(num)
	{
		var pid = _data.game.players[num].id;
		var title = num + 1;
		if (pid > 0)
		{
			var t =  _data.club.players[pid].name;
			var event = _data.club.events[_data.game.event_id];
			if (typeof event != "undefined")
			{
				var nick = event.reg[pid];
				if (typeof nick != "undefined")
				{
					t = nick;
				}
			}
			title += ' (' + t + ')';
		}
		return title;
	}
	
	this.findPlayer = function(name)
	{
		var pl = null;
		var searchInNicks = true;
		name = name.toLocaleLowerCase();
		for (var pid in _data.club.players)
		{
			var p = _data.club.players[pid];
			if (p.name.toLocaleLowerCase() == name)
				return p;
			if (searchInNicks)
			{
				for (var n in p.nicks)
				{
					if (n.toLocaleLowerCase() == name)
					{
						if (pl == null)
						{
							pl = p;
						}
						else
						{
							searchInNicks = false;
							pl = null;
						}
						break;
					}
				}
			}
		}
		return pl;
	}
	
	this.eventId = function(event_id)
	{
		var game = _data.game;
		var old_id = game.event_id;
		if (game.event_id != event_id)
		{
			var club = _data.club;
			var event = club.events[event_id];
			game.event_id = event_id;
			game.moder_id = (event.flags & /*EVENT_FLAG_ALL_MODERATE*/8) ? 0 : _data.user.id;
			game.lang = parseInt(event.langs);
			game.rules_code = event.rules_code;
			if ((game.lang - 1) & game.lang)
			{
				game.lang = 0;
			}
			for (var i = 0; i < 10; ++i)
			{
				var p = game.players[i];
				p.id = 0;
				p.nick = "";
			}
			dirty();
		}
		return old_id;
	}
	
	this.createEvent = function(event)
	{
		if (typeof event.addr_id == "undefined" && $.trim(event.addr).length == 0)
		{
			throw l('ErrNoAddr');
		}
		
		var now = mafia.time();
	
		--_curEventId;
		event['id'] = _curEventId;
		event['action'] = 'new-event';
		event['time'] = mafia.time();
		event['start'] = now;
		_data.requests.push(event);
		
		var club = _data.club;
		var events = club.events;
		events[_curEventId] =
		{
			id: _curEventId,
			rules_code: event.rules_code,
			name: event.name,
			start_time: now,
			langs: event.langs,
			duration: event.duration,
			flags: event.flags,
			reg: {}
		};
		
		// can use a better algorythm to update sEvents, but we don't care - there is not too many events
		_sortedEvents = [];
		for (var id in events)
		{
			_sortedEvents.push(id);
		}
		_sortedEvents.sort(function(a, b) { return events[a].start_time - events[b].start_time; });
		
		_sortedRegs[_curEventId] = [];
		
		return _curEventId;
	}
	
	this.extendEvent = function(id, time)
	{
		var e = _data.club.events[id];
		var end = mafia.time() + parseInt(time);
		e.duration = end - e.start_time;
		
		var req =
		{
			action: 'extend-event',
			time: mafia.time(),
			id: id,
			duration: e.duration
		};
		_data.requests.push(req);
		dirty();
	}
	
	this.register = function(nick, user_id)
	{
		var event = _data.club.events[_data.game.event_id];
		if (typeof event != "undefined")
		{
			var reg = event.reg;
			if (typeof reg[user_id] == "undefined")
			{
				reg[user_id] = nick;
				
				var sReg = _sortedRegs[event.id];
				sReg.push(user_id);
				sReg.sort(function(a, b) { return reg[a].toLocaleLowerCase().localeCompare(reg[b].toLocaleLowerCase()); });
				
				var eventId = _data.game.event_id;
				if (eventId != 0)
				{
					var reg =
					{
						action: 'reg',
						time: mafia.time(),
						id: user_id,
						'nick': nick,
						event: _data.game.event_id
					};
					_data.requests.push(reg);
				}
				dirty();
			}
		}
	}
	
	this.regIncomer = function(name, nick, user_id, flags)
	{
		var club = _data.club;
		var event = club.events[_data.game.event_id];
		if (typeof event != "undefined")
		{
			if (typeof user_id == "undefined" || user_id == 0)
			{
				user_id = (--_curUserId);
				flags = 65;
			}
			
			var players = club.players;
			if (typeof players[user_id] == "undefined")
			{
				players[user_id] =
				{
					'id': user_id,
					'name': name,
					'flags': flags,
					'nicks': {}
				}
			}
			
			var reg = event.reg;
			if (typeof reg[user_id] == "undefined")
			{
				reg[user_id] = nick;
				
				var sReg = _sortedRegs[event.id];
				sReg.push(user_id);
				sReg.sort(function(a, b) { return reg[a].toLocaleLowerCase().localeCompare(reg[b].toLocaleLowerCase()); });
				
				var req =
				{
					action: 'reg-incomer',
					time: mafia.time(),
					nick: nick,
					event: _data.game.event_id,
					flags: flags,
					id: user_id
				};
				if (user_id <= 0)
				{
					req['name'] = name;
				}
				_data.requests.push(req);
			}
			dirty();
		}
		return user_id;
	}
	
	this.checkUser = function(name)
	{
		var club = _data.club;
		var event = club.events[_data.game.event_id];
		var players = club.players;
		
		if (event.id == 0)
		{
			return l('ErrDemo');
		}
		
		name = name.trim();
		if (name == '')
		{
			return l('ErrNoName');
		}
		
		var lName = name.toLocaleLowerCase();
		for (var pid in club.players)
		{
			if (club.players[pid].name.toLocaleLowerCase() == lName)
			{
				return l('ErrUserExists', name);
			}
		}
		return null;
	}
	
	this.createUser = function(name, nick, email, flags)
	{
		var club = _data.club;
		var event = club.events[_data.game.event_id];
		var players = club.players;
		
		name = name.trim();
		email = email.trim();
		
		var error = mafia.checkUser(name);
		if (error != null)
		{
			throw error;
		}
		
		var user_id = (--_curUserId);
		players[user_id] =
		{
			'id': user_id,
			'name': name,
			'flags': flags,
			'nicks': {}
		};
		
		var reg = event.reg;
		reg[user_id] = nick;
			
		var sReg = _sortedRegs[event.id];
		sReg.push(user_id);
		sReg.sort(function(a, b) { return reg[a].toLocaleLowerCase().localeCompare(reg[b].toLocaleLowerCase()); });
		
		var req =
		{
			action: 'new-user',
			time: mafia.time(),
			name: name,
			nick: nick,
			email: email,
			event: event.id,
			flags: flags,
			id: user_id
		};
		_data.requests.push(req);
		dirty();
		return user_id;
	}
	
	this.canBack = function()
	{
		return _data.game.log != null && _data.game.log.length > 0;
	}
	
	this.canNext = function()
	{
		var game = _data.game;
		return game.gamestate != /*GAME_STATE_NOT_STARTED*/0 || (game.moder_id != 0 && game.lang != 0);
	}
	
	this.canRestart = function()
	{
		return _data.game.gamestate > /*GAME_STATE_NOT_STARTED*/0;
	}
	
	this.getRule = function(ruleNum)
	{
		return _data.game.rules_code.substr(ruleNum, 1);
	}
	
	this.rulesCode = function(code)
	{
		if (typeof code == "undefined")
		{
			return _data.game.rules_code;
		}
		
		if (_data.game.rules_code != code)
		{
			_data.game.rules_code = code;
			dirty();
		}
	}
	
	this.setLang = function(lang)
	{
		if ((lang - 1) & lang)
		{
			lang = 0;
		}
		if (_data.game.lang != lang)
		{
			_data.game.lang = lang;
			dirty();
		}
	}
	
// =================================================== The game
	function _getNomIndex(num)
	{
		for (var i = 0; i < _curVoting.nominants.length; ++i)
		{
			if (_curVoting.nominants[i].player_num == num)
			{
				return i;
			}
		}
		return -1;
	}
	
	function _duplicateVoting(forward)
	{
		var game = _data.game;
		var prev;
		if (forward)
		{
			prev = _curVoting;
			_curVoting =
			{
				round: _curVoting.round,
				nominants: [],
				voting_round: _curVoting.voting_round,
				multiple_kill: false,
				canceled: 0
			};
			if (game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2)
			{
				_curVoting.votes = null;
			}
			else
			{
				_curVoting.votes = [-1, -1, -1, -1, -1, -1, -1, -1, -1, -1];
			}
			
			for (var i = 0; i < prev.nominants.length; ++i)
			{
				var n = prev.nominants[i];
				var p = game.players[n.player_num];
				if (p.state == /*PLAYER_STATE_ALIVE*/0)
				{
					_addNominant(n.player_num, n.nominated_by);
				}
				else if ((game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2) == 0)
				{
					for (var j = 0; j < 10; ++j)
					{
						var v = prev.votes[j];
						if (v >= i) --v;
						if (v >= 0) _vote(j, v);
					}
				}
			}
			
			game.votings.push(_curVoting);
		}
		else if (game.votings.length > 1)
		{
			prev = game.votings[game.votings.length - 2];
			if (_curVoting.round == prev.round && _curVoting.voting_round == prev.voting_round)
			{
				game.votings.pop();
				_curVoting = prev;
			}
		}
	}
	
	function _onPlayerStateChange(player, reason)
	{
		var game = _data.game;

		// check if canceling/uncanceling voting needed
		switch (reason)
		{
			case /*PLAYER_KR_WARNINGS*/2:
			case /*PLAYER_KR_KICKOUT*/3:
			case /*PLAYER_KR_SUICIDE*/1:
				switch (game.gamestate)
				{
					case /*GAME_STATE_DAY_START*/3:
					case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
					case /*GAME_STATE_VOTING*/8:
					case /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10:
					case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
						if (player.state != /*PLAYER_STATE_ALIVE*/0)
						{
							_curVoting.canceled += 2;
							var nomIdx = _getNomIndex(player.number);
							var antimonster = mafia.getRule(/*RULES_ANTIMONSTER*/19);
							if (
								antimonster == /*RULES_ANTIMONSTER_NO*/1 ||
								(antimonster == /*RULES_ANTIMONSTER_NOMINATED*/2 && nomIdx < 0))
							{
								if ((_curVoting.canceled & 1) == 0)
									_duplicateVoting(true);
							}
						}
						else
						{
							if (_curVoting.canceled <= 1)
								_duplicateVoting(false);
							_curVoting.canceled -= 2;
						}
						break;
				}
				break;
		}

		// update player counts
		if (player.role >= /*ROLE_MAFIA*/2)
		{
			if (player.state != /*PLAYER_STATE_ALIVE*/0)
			{
				if (typeof game.shooting[game.round] != 'undefined')
				{
					delete game.shooting[game.round][player.number];
				}
			}
			else if (typeof game.shooting[game.round] != 'undefined')
			{
				var arranged = -1;
				for (var i = 0; i < 10; ++i)
				{
					if (game.players[i].arranged == game.round)
					{
						arranged = i;
						break;
					}
				}
				game.shooting[game.round][player.number] = arranged;
			}
		}

		if (player.state != /*PLAYER_STATE_ALIVE*/0)
		{
			var maf_count = 0;
			var civ_count = 0;
			for (var i = 0; i < 10; ++i)
			{
				var p = game.players[i];
				if (p.state == /*PLAYER_STATE_ALIVE*/0)
				{
					if (p.role <= /*ROLE_SHERIFF*/1)
						++civ_count;
					else
						++maf_count;
				}
			}
		
			// check if the game is over
			if (maf_count <= 0 || civ_count <= maf_count)
			{
				game.gamestate = /*GAME_STATE_END*/25;
			}
		}
	}
	
	function _killPlayer(num, isNight, reason, round)
	{
		var player = _data.game.players[num];
		if (player.state == /*PLAYER_STATE_ALIVE*/0)
		{
			if (isNight)
			{
				player.state = /*PLAYER_STATE_KILLED_NIGHT*/1;
			}
			else
			{
				player.state = /*PLAYER_STATE_KILLED_DAY*/2;
			}
			player.kill_round = round;
			player.kill_reason = reason;
			_onPlayerStateChange(player, reason);
		}
	}

	function _resurrectPlayer(num)
	{
		try
		{
			var player = _data.game.players[num];
			if (player.state != /*PLAYER_STATE_ALIVE*/0)
			{
				var reason = player.kill_reason;
				player.state = /*PLAYER_STATE_ALIVE*/0;
				player.kill_round = -1;
				player.kill_reason = /*PLAYER_KR_ALIVE*/-1;
				_onPlayerStateChange(player, reason);
			}
		}
		catch(e)
		{
			handleError(e);
		}
	}
	
	function _addNominant(num, by)
	{
		var game = _data.game;
		if (typeof num == "undefined")
		{
			num = game.current_nominant;
			by = game.player_speaking;
		}
		else if (typeof by == "undefined")
		{
			by = game.player_speaking;
		}
	
		if (_curVoting.canceled <= 0)
		{
			var player = game.players[num];
			if (player.state == /*PLAYER_STATE_ALIVE*/0)
			{
				for (var i in _curVoting.nominants)
				{
					var nom = _curVoting.nominants[i];
					if (nom.player_num == num)
					{
						return false;
					}
				}
				
				var nominant =
				{
					player_num: num,
					nominated_by: by,
					count: 0
				};
				
				var index = _curVoting.nominants.length;
				_curVoting.nominants.push(nominant);
				if (_data.game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2)
					_voting = null;
				else
					for (var i = 0; i < 10; ++i)
					{
						_vote(i, index);
					}
				return true;
			}
		}
		return false;
	}
	
	function _removeNominant()
	{
		if (_curVoting.canceled <= 0)
		{
			var idx = _curVoting.nominants.length - 1
			var n = _curVoting.nominants[idx].player_num;
			_curVoting.nominants.splice(idx, 1);
			if ((_data.game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2) == 0)
			{
				var v = _curVoting.votes;
				for (var i = 0; i < 10; ++i)
				{
					if (v[i] > idx)
					{
						--v[i];
					}
					else if (v[i] == idx)
					{
						_vote(i, -1);
					}
				}
			}
		}
	}
	
	function _vote(num, nominantIndex)
	{
		if (_curVoting.canceled <= 0 && (_data.game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2) == 0)
		{
			var oldNominant = _curVoting.votes[num];
			if (oldNominant >= 0 && oldNominant < _curVoting.nominants.length)
			{
				--_curVoting.nominants[oldNominant].count;
			}

			var player = _data.game.players[num];
			if (player.state != /*PLAYER_STATE_ALIVE*/0)
			{
				_curVoting.votes[num] = -1;
			}
			else
			{
				if (nominantIndex < 0 || nominantIndex >= _curVoting.nominants.length)
				{
					nominantIndex = _curVoting.nominants.length - 1;
				}
				_curVoting.votes[num] = nominantIndex;
				if (nominantIndex >= 0)
				{
					++_curVoting.nominants[nominantIndex].count;
				}
			}
			_voting = null;
		}
	}
	
	this.getNominationIndex = function(num)
	{
		return _getNomIndex(num);
	}
	
	this.isNominated = function(num)
	{
		return _getNomIndex(num) >= 0;
	}
	
	this.votingWinners = function()
	{
		if (_voting == null || _voting.round != _curVoting.round || _voting.voting_round != _curVoting.voting_round)
		{
			_voting = { winners: [], round: _curVoting.round, voting_round: _curVoting.voting_round };
			if (_data.game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2)
			{
				if (_curVoting.nominants.length == 1)
				{
					_curVoting.nominants[0].count = 1;
				}
				for (var i in _curVoting.nominants)
				{
					var nominant = _curVoting.nominants[i];
					if (nominant.count != 0)
					{
						_voting.winners.push(nominant.player_num);
					}
				}
			}
			else
			{
				var maxCount = 0;
				for (var i in _curVoting.nominants)
				{
					var nominant = _curVoting.nominants[i];
					if (nominant.count != 0)
					{
						if (nominant.count > maxCount)
						{
							maxCount = nominant.count;
							_voting.winners = [nominant.player_num];
						}
						else if (nominant.count == maxCount)
						{
							_voting.winners.push(nominant.player_num);
						}
					}
				}
			}
		}
		return _voting.winners;
	}
		
	function _assignRole(role)
	{
		var game = _data.game;
		while (true)
		{
			var i = Math.floor(Math.random() * 10);
			var p = game.players[i];
			if (p.role == /*ROLE_CIVILIAN*/0)
			{
				p.role = role;
				return;
			}
		}
	}
	
	this.whoMurdered = function()
	{
		var game = _data.game;
		var shots = mafia.shots();

		var num = -1;
		for (var p in shots)
		{
			var pNum = shots[p];
			if (pNum < 0)
			{
				return -1;
			}

			if (num < 0)
			{
				num = pNum;
			}
			else if (num != pNum)
			{
				return -1;
			}
		}
		return num;
	}
	
	function _newVoting(round)
	{
		var game = _data.game;
		var vround = 0;
		var noms = null;
		if (_curVoting != null && _curVoting.round == round)
		{
			noms = mafia.votingWinners();
			vround = 1;
		}
	
		_curVoting = 
		{
			round: round,
			nominants: [],
			voting_round: vround,
			multiple_kill: false,
			canceled: 0
		};
		if (game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2)
		{
			_curVoting.votes = null;
		}
		else
		{
			_curVoting.votes = [-1, -1, -1, -1, -1, -1, -1, -1, -1, -1];
		}
		
		if (noms != null)
		{
			for (var n in noms)
			{
				_addNominant(noms[n], -1);
			}
		}
		game.votings.push(_curVoting);
	}
	
	function _gameStep(flags, logRec)
	{
		//console.log(logRec);
		if (typeof logRec != "undefined")
		{
			_data.game.log.push(logRec);
		}

		if (!_editing)
		{
			_data.game.end_time = mafia.time();
		}
		dirty();
		_callStateChange(flags);
	}
	
	this.playersCount = function(t)
	{
		var game = _data.game;
		var count = 0;
		for (var i = 0; i < 10; ++i)
		{
			var p = game.players[i];
			if (p.state == /*PLAYER_STATE_ALIVE*/0)
			{
				if (typeof t == "undefined" || (t && p.role <= /*ROLE_SHERIFF*/1) || (!t && p.role > /*ROLE_SHERIFF*/1))
					++count;
			}
		}
		return count;
	}
	
	this.generateRoles = function(forse)
	{
		if (typeof forse == "undefined") forse = true;
		
		var changed = false;
		var game = _data.game;
		var m = 0;
		var d = 0;
		var s = 0;
		for (var i = 0; i < 10; ++i)
		{
			var p = game.players[i];
			var c = false;
			switch (p.role)
			{
			case /*ROLE_SHERIFF*/1:
				if (forse || s == 1)
					p.role = /*ROLE_CIVILIAN*/0;
				else
					++s;
				break;
			case /*ROLE_MAFIA*/2:
				if (forse || m == 2)
					p.role = /*ROLE_CIVILIAN*/0;
				else
					++m;
				break;
			case /*ROLE_DON*/3:
				if (forse || d == 1)
					p.role = /*ROLE_CIVILIAN*/0;
				else
					++d;
				break;
			default:
				p.role = /*ROLE_CIVILIAN*/0;
				break;
			}
		}
		while (s++ < 1) _assignRole(/*ROLE_SHERIFF*/1);
		while (m++ < 2) _assignRole(/*ROLE_MAFIA*/2);
		while (d++ < 1) _assignRole(/*ROLE_DON*/3);
		
		if (forse)
		{
			dirty();
			_callStateChange(0);
		}
	}
	
	this.next = function()
	{
		var game = _data.game;
		var logRec = 
		{
			type: /*LOGREC_TYPE_NORMAL*/0,
			round: game.round,
    		gamestate: game.gamestate,
    		player_speaking: game.player_speaking, 
    		current_nominant: game.current_nominant,
    		player: -1
		};
		
		switch (game.gamestate)
		{
			case /*GAME_STATE_NOT_STARTED*/0:
				if (!mafia.canNext())
				{
					throw l("ErrGameNotReady");
				}

				if (_data.user.settings.flags & /*S_FLAG_SIMPLIFIED_CLIENT*/0x1)
				{
					game.flags |= /*GAME_FLAG_SIMPLIFIED_CLIENT*/2;
				}
				else
				{
					game.flags &= ~/*GAME_FLAG_SIMPLIFIED_CLIENT*/2;
				}
				
				mafia.generateRoles(false);

				game.gamestate = /*GAME_STATE_NIGHT0_START*/1;
				game.round = 0;
				game.player_speaking = -1;
				game.current_nominant = -1;
				game.table_opener = 0;
				
				game.votings = [];
				_newVoting(0);
				
				game.shooting = [];
				game.log = [];

				if (!_editing)
				{
					game.start_time = mafia.time();
				}
				break;

			case /*GAME_STATE_NIGHT0_START*/1:
				game.gamestate = /*GAME_STATE_NIGHT0_ARRANGE*/2;
				break;

			case /*GAME_STATE_NIGHT0_ARRANGE*/2:
				game.gamestate = /*GAME_STATE_DAY_START*/3;
				break;

			case /*GAME_STATE_DAY_START*/3:
				if (game.current_nominant >= 0)
				{
					_addNominant();
					game.current_nominant = -1;
				}
				game.gamestate = /*GAME_STATE_DAY_PLAYER_SPEAKING*/5;
				game.player_speaking = mafia.nextPlayer(-1);
				break;
				
			case /*GAME_STATE_DAY_FREE_DISCUSSION*/20:
				game.gamestate = /*GAME_STATE_DAY_PLAYER_SPEAKING*/5;
				game.player_speaking = mafia.nextPlayer(-1);
				break;

			case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
				if (game.current_nominant >= 0)
				{
					_addNominant();
				}

				game.current_nominant = -1;
				game.player_speaking = mafia.nextPlayer(game.player_speaking);
				if (game.player_speaking < 0)
				{
					if (_curVoting.canceled > 0)
					{
						game.gamestate = /*GAME_STATE_NIGHT_START*/11;
						_newVoting(game.round + 1);
					}
					else switch(_curVoting.nominants.length)
					{
						case 0:
							game.gamestate = /*GAME_STATE_NIGHT_START*/11;
							_newVoting(game.round + 1);
							break;

						case 1:
							if (mafia.getRule(/*RULES_FIRST_DAY_VOTING*/8) != /*RULES_FIRST_DAY_VOTING_TO_TALK*/1 && game.round == 0)
							{
								game.gamestate = /*GAME_STATE_NIGHT_START*/11;
								_newVoting(game.round + 1);
							}
							else
							{
								game.gamestate = /*GAME_STATE_VOTING_KILLED_SPEAKING*/7;
								game.current_nominant = 0;
								game.player_speaking = mafia.votingWinners()[0];
								if (mafia.isKillingThisDay())
								{
									_killPlayer(game.player_speaking, false, /*PLAYER_KR_NORMAL*/0, game.round);
								}
							}
							break;

						default:
							game.current_nominant = 0;
							game.player_speaking = _curVoting.nominants[0].player_num;
							game.gamestate = /*GAME_STATE_VOTING*/8;
							break;
					}
				}
				break;

			case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
				if (++game.current_nominant >= mafia.votingWinners().length)
				{
					game.gamestate = /*GAME_STATE_NIGHT_START*/11;
					_newVoting(game.round + 1);
					game.player_speaking = -1;
					game.current_nominant = -1;
				}
				else
				{
					game.player_speaking = mafia.votingWinners()[game.current_nominant];
				}
				break;

			case /*GAME_STATE_VOTING*/8:
				if (_curVoting.canceled > 0)
				{
					game.gamestate = /*GAME_STATE_NIGHT_START*/11;
					_newVoting(game.round + 1);
					game.player_speaking = -1;
					game.current_nominant = -1;
				}
				else if ((game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2) == 0 && game.current_nominant < _curVoting.nominants.length - 1)
				{
					game.player_speaking = _curVoting.nominants[++game.current_nominant].player_num;
				}
				else switch (mafia.votingWinners().length)
				{
				case 0:
					return;
				case 1:
					game.gamestate = /*GAME_STATE_VOTING_KILLED_SPEAKING*/7;
					game.current_nominant = 0;
					game.player_speaking = mafia.votingWinners()[0];
					if (mafia.isKillingThisDay())
					{
						_killPlayer(game.player_speaking, false, /*PLAYER_KR_NORMAL*/0, game.round);
					}
					break;
				default:
/*					
					Find out how commenting this out affects simplified voting?
					console.log(mafia.playersCount());
					console.log(mafia.votingWinners().length);
					if (mafia.playersCount() % mafia.votingWinners().length != 0)
						return;*/
					game.gamestate = /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9;
					game.current_nominant = -1;
					game.player_speaking = -1;
					break;
				}
				break;

			case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
				/*_assert_(mafia.votingWinners().length > 1);*/
				if (_curVoting.voting_round == 0)
				{
					if (!mafia.isKillingThisDay())
					{
						// round0 - nobody is killed they all are speaking
						game.current_nominant = 0;
						if (game.current_nominant >= mafia.votingWinners().length)
						{
							game.gamestate = /*GAME_STATE_NIGHT_START*/11;
							_newVoting(game.round + 1);
							game.player_speaking = -1;
							game.current_nominant = -1;
						}
						else
						{
							game.gamestate = /*GAME_STATE_VOTING_KILLED_SPEAKING*/7;
							game.player_speaking = mafia.votingWinners()[game.current_nominant];
						}
					}
					else if (_curVoting.canceled > 0 || (mafia.playersCount() == 4 && mafia.getRule(/*RULES_SPLIT_ON_FOUR*/11) == /*RULES_SPLIT_ON_FOUR_PROHIBITED*/1))
					{
						// A special case: 4 players, multiple winners - no second voting. Nobody is killed.
						game.gamestate = /*GAME_STATE_NIGHT_START*/11;
						_newVoting(game.round + 1);
						game.player_speaking = -1;
						game.current_nominant = -1;
					}
					else
					{
						// vote again
						_newVoting(game.round);
						game.current_nominant = 0;
						if (game.current_nominant >= _curVoting.nominants.length)
						{
							game.gamestate = /*GAME_STATE_VOTING*/8;
						}
						else
						{
							game.gamestate = /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10;
						}
						game.player_speaking = _curVoting.nominants[game.current_nominant].player_num;
					}
				}
				else if (!_curVoting.multiple_kill || mafia.playersCount() == 3)
				{
					// 3 players is a special case. They can't be all killed, so we ignore multiple_kill flag.
					game.gamestate = /*GAME_STATE_NIGHT_START*/11;
					_newVoting(game.round + 1);
				}
				else
				{
					game.gamestate = /*GAME_STATE_VOTING_KILLED_SPEAKING*/7;
					game.current_nominant = 0;
					var winners = mafia.votingWinners();
					game.player_speaking = winners[0];
					var count = winners.length;
					for (var i = 0; i < count; ++i)
					{
						_killPlayer(winners[i], false, /*PLAYER_KR_NORMAL*/0, game.round);
					}
				}
				break;

			case /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10:
				if (_curVoting.canceled > 0)
				{
					game.gamestate = /*GAME_STATE_NIGHT_START*/11;
					_newVoting(game.round + 1);
				}
				else
				{
					if (++game.current_nominant >= _curVoting.nominants.length)
					{
						game.gamestate = /*GAME_STATE_VOTING*/8;
						game.current_nominant = 0;
						game.player_speaking = -1;
					}
					game.player_speaking = _curVoting.nominants[game.current_nominant].player_num;
				}
				break;

			case /*GAME_STATE_NIGHT_START*/11:
				game.gamestate = /*GAME_STATE_NIGHT_SHOOTING*/12;
				break;

			case /*GAME_STATE_NIGHT_SHOOTING*/12:
				game.player_speaking = mafia.whoMurdered();
				game.gamestate = /*GAME_STATE_NIGHT_DON_CHECK*/13;
				if (game.player_speaking >= 0)
				{
					_killPlayer(game.player_speaking, true, /*PLAYER_KR_NORMAL*/0, game.round);
				}
				break;

			case /*GAME_STATE_NIGHT_DON_CHECK*/13:
				game.gamestate = /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15;
				break;

			case /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15:
				game.gamestate = /*GAME_STATE_DAY_START*/3;
				++game.round;
				game.current_nominant = -1;
				game.table_opener = mafia.nextPlayer(game.table_opener);
				break;

			case /*GAME_STATE_BEST_PLAYER*/22: // deprecated
			case /*GAME_STATE_BEST_MOVE*/23: // deprecated
				game.gamestate = /*GAME_STATE_END*/25;
				break;
				
			case /*GAME_STATE_END*/25:
				if (mafia.playersCount(false) <= 0)
				{
					game.gamestate = /*GAME_STATE_CIVIL_WON*/18;
				}
				else
				{
					game.gamestate = /*GAME_STATE_MAFIA_WON*/17;
				}
				mafia.submit();
				
			case /*GAME_STATE_MAFIA_WON*/17:
			case /*GAME_STATE_CIVIL_WON*/18:
				return;
		}
		_gameStep(/*STATE_CHANGE_FLAG_RESET_TIMER*/1, logRec);
	}

	this.back = function()
	{
		var game = _data.game;
		var logNum = game.log.length - 1;
		if (logNum >= 0)
		{
			var game = _data.game;
			var logRec = game.log[logNum];
			
			var stepFlags = 0;
			if (logRec.type <= /*LOGREC_TYPE_MISSED_SPEECH*/1)
				stepFlags |= /*STATE_CHANGE_FLAG_RESET_TIMER*/1;
			if (game.gamestate == /*GAME_STATE_END*/25)
				stepFlags |= /*STATE_CHANGE_FLAG_GAME_WAS_ENDED*/4;

			if (logRec.type == /*LOGREC_TYPE_WARNING*/2)
			{
				var player = game.players[logRec.player];
				if (player.warnings >= 4)
				{
					player.warnings = 3;
					_resurrectPlayer(logRec.player);
				}
				else if (player.warnings == 3)
				{
					player.warnings = 2;
					player.mute = -1;
				}
				else if (player.warnings > 0)
				{
					--player.warnings;
				}
			}
			else if (logRec.type == /*LOGREC_TYPE_SUICIDE*/3 || logRec.type == /*LOGREC_TYPE_KICK_OUT*/4)
			{
				_resurrectPlayer(logRec.player);
			}
			else if (logRec.type == /*LOGREC_TYPE_POSTPONE_MUTE*/5)
			{
				var player = game.players[logRec.player];
				player.mute = logRec.round;
			}
			else if (logRec.type == /*LOGREC_TYPE_CANCEL_VOTING*/6)
			{
				_curVoting.canceled = 0;
			}
			else if (logRec.type == /*LOGREC_TYPE_RESUME_VOTING*/7)
			{
				if (logRec.player > 0)
					_curVoting.canceled = logRec.player;
				else
					_duplicateVoting(false);
			}
			else
			{
				switch (logRec.gamestate)
				{
					case /*GAME_STATE_DAY_START*/3:
						switch (game.gamestate)
						{
							case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
								if (logRec.current_nominant >= 0 && _curVoting.nominants.length > 0)
								{
									_removeNominant();
								}
								break;
							case /*GAME_STATE_DAY_FREE_DISCUSSION*/20:
								if (logRec.current_nominant >= 0 && _curVoting.nominants.length > 0)
								{
									_removeNominant();
								}
								break;
							/*default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;

					case /*GAME_STATE_DAY_FREE_DISCUSSION*/20:
						if (logRec.current_nominant >= 0 && _curVoting.nominants.length > 0)
						{
							_removeNominant();
						}
						break;

					case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
						if (logRec.type == /*LOGREC_TYPE_MISSED_SPEECH*/1)
						{
							game.players[logRec.player_speaking].mute = -1;
						}
						switch (game.gamestate)
						{
							case /*GAME_STATE_NIGHT_START*/11:
								if (game.votings.length > 1)
								{
									game.votings.pop();
									_curVoting = game.votings[game.votings.length - 1];
								}
								break;
							case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
							case /*GAME_STATE_MAFIA_WON*/17:
							case /*GAME_STATE_CIVIL_WON*/18:
							case /*GAME_STATE_END*/25:
							case /*GAME_STATE_BEST_PLAYER*/22:
							case /*GAME_STATE_BEST_MOVE*/23:
								if (mafia.isKillingThisDay())
								{
									var winners = mafia.votingWinners();
									/*_assert_(winners.length == 1);*/
									_resurrectPlayer(winners[0]);
								}
								break;
						}
						if (logRec.current_nominant >= 0 && _curVoting.nominants.length > 0)
						{
							_removeNominant();
						}
						break;

					case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
						switch (game.gamestate)
						{
							case /*GAME_STATE_NIGHT_START*/11:
								if (game.votings.length > 1)
								{
									game.votings.pop();
									_curVoting = game.votings[game.votings.length - 1];
								}
								break;
							case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
								break;
							/*default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;

					case /*GAME_STATE_VOTING*/8:
						switch (game.gamestate)
						{
							case /*GAME_STATE_NIGHT_START*/11:
								if (game.votings.length > 1)
								{
									game.votings.pop();
									_curVoting = game.votings[game.votings.length - 1];
								}
								break;
							case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
							case /*GAME_STATE_MAFIA_WON*/17:
							case /*GAME_STATE_CIVIL_WON*/18:
							case /*GAME_STATE_END*/25:
							case /*GAME_STATE_BEST_PLAYER*/22:
							case /*GAME_STATE_BEST_MOVE*/23:
								if (mafia.isKillingThisDay())
								{
									/*_assert_(winners.length == 1);*/
									_resurrectPlayer(mafia.votingWinners()[0]);
								}
								break;
							/*case /GAME_STATE_VOTING/8:
							case /GAME_STATE_VOTING_MULTIPLE_WINNERS/9:
								break;
							default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;

					case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
						switch (game.gamestate)
						{
							case /*GAME_STATE_NIGHT_START*/11:
							case /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10:
								if (game.votings.length > 1)
								{
									game.votings.pop();
									_curVoting = game.votings[game.votings.length - 1];
								}
								break;
							case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
							case /*GAME_STATE_MAFIA_WON*/17:
							case /*GAME_STATE_CIVIL_WON*/18:
							case /*GAME_STATE_END*/25:
							case /*GAME_STATE_BEST_PLAYER*/22:
							case /*GAME_STATE_BEST_MOVE*/23:
								/*_assert_(_curVoting.canceled <= 0);*/
								if (mafia.isKillingThisDay())
								{
									var winners = mafia.votingWinners();
									/*_assert_(count > 1);*/
									for (var i = 0; i < winners.length; ++i)
									{
										_resurrectPlayer(winners[i]);
									}
								}
								break;
							/*default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;

					case /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10:
						switch (game.gamestate)
						{
							case /*GAME_STATE_NIGHT_START*/11:
								if (game.votings.length > 1)
								{
									game.votings.pop();
									_curVoting = game.votings[game.votings.length - 1];
								}
								break;
							/*case /GAME_STATE_VOTING_NOMINANT_SPEAKING/10:
							case /GAME_STATE_VOTING/8:
								break;
							default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;

					case /*GAME_STATE_NIGHT_SHOOTING*/12:
						switch (game.gamestate)
						{
							case /*GAME_STATE_NIGHT_DON_CHECK*/13:
							case /*GAME_STATE_MAFIA_WON*/17:
							case /*GAME_STATE_CIVIL_WON*/18:
							case /*GAME_STATE_END*/25:
							case /*GAME_STATE_BEST_PLAYER*/22:
							case /*GAME_STATE_BEST_MOVE*/23:
								if (game.player_speaking >= 0)
								{
									_resurrectPlayer(game.player_speaking);
								}
								for (var i = 0; i < 10; ++i)
								{
									if (game.players[i].don_check == game.round)
									{
										game.players[i].don_check = -1;
									}
								}
								break;
							/*default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;
				
					case /*GAME_STATE_NIGHT_DON_CHECK*/13:
						switch (game.gamestate)
						{
							case /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15:
								for (var i = 0; i < 10; ++i)
								{
									if (game.players[i].sheriff_check == game.round)
									{
										game.players[i].sheriff_check = -1;
									}
								}
								break;
							/*case /GAME_STATE_NIGHT_DON_CHECK/14:
								break;
							default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;

					case /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15:
						switch (game.gamestate)
						{
							case /*GAME_STATE_DAY_START*/3:
								game.table_opener = mafia.prevPlayer(game.table_opener);
								break;
							/*case /GAME_STATE_NIGHT_SHERIFF_CHECK/16:
								break;
							default:
								_fail_(l("ErrBrokenLog", game.gamestate, logRec.gamestate));
								break;*/
						}
						break;
				}

				if (game.gamestate == /*GAME_STATE_NIGHT_SHOOTING*/12)
				{
					while (game.shooting.length > game.round)
					{
						game.shooting.pop();
					}
				}
			}

			game.round = logRec.round;
			game.gamestate = logRec.gamestate;
			game.player_speaking = logRec.player_speaking;
			game.current_nominant = logRec.current_nominant;
			game.log.pop();

			_gameStep(stepFlags);
		}
	}

	this.isNight = function()
	{
		switch (_data.game.gamestate)
		{
			case /*GAME_STATE_NIGHT0_START*/1:
			case /*GAME_STATE_NIGHT0_ARRANGE*/2:
			case /*GAME_STATE_NIGHT_START*/11:
			case /*GAME_STATE_NIGHT_SHOOTING*/12:
			case /*GAME_STATE_NIGHT_DON_CHECK*/13:
			case /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15:
				return true;
		}
		return false;
	}

	this.nextPlayer = function(num)
	{
		var game = _data.game;
		if (num < 0)
		{
			num = game.table_opener;
			var player = game.players[num];
			while (player.state != /*PLAYER_STATE_ALIVE*/0)
			{
				++num;
				if (num >= 10)
				{
					num = 0;
				}
				player = game.players[num];
			}
			return num;
		}

		while (true)
		{
			++num;
			if (num >= 10)
			{
				num = 0;
			}

			if (num == game.table_opener)
			{
				return -1;
			}

			var player = game.players[num];
			if (player.state == /*PLAYER_STATE_ALIVE*/0)
			{
				return num;
			}
		}
	}

	this.prevPlayer = function(num)
	{
		var game = _data.game;
		if (num < 0)
		{
			num = game.table_opener;
		}
	
		while (true)
		{
			--num;
			if (num < 0)
			{
				num = 9;
			}

			var player = game.players[num];
			if (player.state == /*PLAYER_STATE_ALIVE*/0)
			{
				return num;
			}
		}
	}
	
	this.guess = function(num)
	{
		var game = _data.game;
		if (num < 0 || num >= 10) num = -1;
		if (game.guess3 == null)
		{
			game.guess3 = [-1, -1, -1];
		}
		if (game.guess3.length < 3)
		{
			game.guess3.push(num);
		}
		else
		{
			game.guess3[0] = game.guess3[1];
			game.guess3[1] = game.guess3[2];
			game.guess3[2] = num;
		}
		dirty();
		_callStateChange(0);
	}
	
	this.isGuessed = function(num)
	{
		var game = _data.game;
		if (game.guess3 != null)
		{
			for (var i = 0; i < game.guess3.length && i < 3; ++i)
			{
				if (game.guess3[i] == num)
				{
					return true;
				}
			}
		}
		return false;
	}
	
	this.noGuess = function()
	{
		var game = _data.game;
		if (game.guess3 != null)
		{
			for (var i = 0; i < game.guess3.length; ++i)
			{
				game.guess3[i] = -1;
			}
		}
		dirty();
		_callStateChange(0);
	}
	
	this.bestPlayer = function(num)
	{
		var game = _data.game;
		if (num < 0 || num >= 10) num = -1;
		game.best_player = num;
		dirty();
		_callStateChange(0);
	}
	
	this.bestMove = function(num)
	{
		var game = _data.game;
		if (num < 0 || num >= 10) num = -1;
		game.best_move = num;
		dirty();
		_callStateChange(0);
	}
	
	this.vote = function(num, v)
	{
		var game = _data.game;
		var player = game.players[num];
		if (player.state == /*PLAYER_STATE_ALIVE*/0)
		{
			_vote(num, v ? game.current_nominant : -1);
			dirty();
			_callStateChange(0);
		}
	}

	this.canDonCheck = function()
	{
		var game = _data.game;
		for (var i in game.players)
		{
			var player = game.players[i];
			if (player.role == /*ROLE_DON*/3)
			{
				if (player.state == /*PLAYER_STATE_ALIVE*/0)
				{
					return true;
				}
				else if (
					player.state == /*PLAYER_STATE_KILLED_NIGHT*/1 &&
					player.kill_round == game.round &&
					player.kill_reason == /*PLAYER_KR_NORMAL*/0)
				{
					return true;
				}
				break;
			}
		}
		return false;
	}

	this.canSheriffCheck = function()
	{
		var game = _data.game;
		for (var i in game.players)
		{
			var player = game.players[i];
			if (player.role == /*ROLE_SHERIFF*/1)
			{
				if (player.state == /*PLAYER_STATE_ALIVE*/0)
				{
					return true;
				}
				else if (
					player.state == /*PLAYER_STATE_KILLED_NIGHT*/1 &&
					player.kill_round == game.round &&
					player.kill_reason == /*PLAYER_KR_NORMAL*/0)
				{
					return true;
				}
				break;
			}
		}
		return false;
	}

	this.setPlayerRole = function(num, role)
	{
		var game = _data.game;
		if (game.gamestate == /*GAME_STATE_NIGHT0_START*/1)
		{
			var player = game.players[num];
			if (role != player.role)
			{
				for (var i = 9; i >= 0; --i)
				{
					var p = game.players[i];
					if (p.role == role)
					{
						p.role = player.role;
						break;
					}
				}
				player.role = role;
				_gameStep(0);
			}
		}
	}
	
	this.isKillingThisDay = function()
	{
		return _data.game.round > 0 || mafia.getRule(/*RULES_FIRST_DAY_VOTING*/8) != /*RULES_FIRST_DAY_VOTING_TO_TALK*/1;
	}
	
	this.warnPlayer = function(num)
	{
		var game = _data.game;
		switch (game.gamestate)
		{
			case /*GAME_STATE_MAFIA_WON*/17:
			case /*GAME_STATE_CIVIL_WON*/18:
			case /*GAME_STATE_BEST_PLAYER*/22:
			case /*GAME_STATE_BEST_MOVE*/23:
			case /*GAME_STATE_END*/25:
				return;
		}

		var player = game.players[num];
		if (player.state == /*PLAYER_STATE_ALIVE*/0)
		{
			var logRec =
			{
				type: /*LOGREC_TYPE_WARNING*/2,
				round: game.round,
				gamestate: game.gamestate,
				player_speaking: game.player_speaking,
				current_nominant: game.current_nominant,
				player: num
			};
		
			switch (++player.warnings)
			{
				case 3:
					switch (game.gamestate)
					{
						case /*GAME_STATE_NOT_STARTED*/0:
						case /*GAME_STATE_NIGHT0_START*/1:
						case /*GAME_STATE_NIGHT0_ARRANGE*/2:
						case /*GAME_STATE_DAY_START*/3:
						case /*GAME_STATE_DAY_FREE_DISCUSSION*/20:
							player.mute = game.round;
							break;
						
						case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
							player.mute = game.round;
							if (game.player_speaking >= game.table_opener)
							{
								if (num <= game.player_speaking && num >= game.table_opener)
								{
									++player.mute;
								}
							}
							else if (num <= game.player_speaking || num >= game.table_opener)
							{
								++player.mute;
							}
							break;
						
						default:
							player.mute = game.round + 1;
							break;
					}
					break;
				case 4:
					_killPlayer(num, mafia.isNight(), /*PLAYER_KR_WARNINGS*/2, game.round);
					break;
			}
			
			_gameStep(0, logRec);
		}
	}

	this.nominatePlayer = function(num)
	{
		var game = _data.game;
		if (game.gamestate == /*GAME_STATE_DAY_PLAYER_SPEAKING*/5 || (game.gamestate == /*GAME_STATE_DAY_START*/3 && mafia.getRule(/*RULES_KILLED_NOMINATE*/13) == /*RULES_KILLED_NOMINATE_ALLOWED*/1))
		{   
			if (num < 0 || game.players[num].state == /*PLAYER_STATE_ALIVE*/0)
			{
				game.current_nominant = num;
				_gameStep(0);
			}
		}
	}

	this.suicide = function(num)
	{
		var game = _data.game;
		switch (game.gamestate)
		{
			case /*GAME_STATE_MAFIA_WON*/17:
			case /*GAME_STATE_CIVIL_WON*/18:
			case /*GAME_STATE_BEST_PLAYER*/22:
			case /*GAME_STATE_BEST_MOVE*/23:
			case /*GAME_STATE_END*/25:
				return;
		}
		
		var logRec =
		{
			type: /*LOGREC_TYPE_SUICIDE*/3,
			round: game.round,
			gamestate: game.gamestate,
			player_speaking: game.player_speaking,
			current_nominant: game.current_nominant,
			player: num
		};
		_killPlayer(num, mafia.isNight(), /*PLAYER_KR_SUICIDE*/1, game.round);
		_gameStep(0, logRec);
	}

	this.kickOut = function(num)
	{
		var game = _data.game;
		switch (game.gamestate)
		{
			case /*GAME_STATE_MAFIA_WON*/17:
			case /*GAME_STATE_CIVIL_WON*/18:
			case /*GAME_STATE_BEST_PLAYER*/22:
			case /*GAME_STATE_BEST_MOVE*/23:
			case /*GAME_STATE_END*/25:
				return;
		}

		var logRec = 
		{
			type: /*LOGREC_TYPE_KICK_OUT*/4,
			round: game.round,
			gamestate: game.gamestate,
			player_speaking: game.player_speaking,
			current_nominant: game.current_nominant,
			player: num
		};
		_killPlayer(num, mafia.isNight(), /*PLAYER_KR_KICKOUT*/3, game.round);
		_gameStep(0, logRec);
	}

	this.donCheck = function(num)
	{
		var game = _data.game;
		if (game.gamestate == /*GAME_STATE_NIGHT_DON_CHECK*/13)
		{
			for (var i = 0; i < 10; ++i)
			{
				var player = game.players[i];
				if (player.don_check == game.round)
				{
					player.don_check = -1;
				}
			}
		
			if (num >= 0)
			{
				game.players[num].don_check = game.round;
			}
			game.current_nominant = num;
			_gameStep(0);
		}
	}

	this.sheriffCheck = function(num)
	{
		var game = _data.game;
		if (game.gamestate == /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15)
		{
			for (var i = 0; i < 10; ++i)
			{
				var player = game.players[i];
				if (player.sheriff_check == game.round)
				{
					player.sheriff_check = -1;
				}
			}
		
			if (num >= 0)
			{
				game.players[num].sheriff_check = game.round;
			}
			game.current_nominant = num;
			_gameStep(0);
		}
	}

	this.arrangePlayer = function(num, round)
	{
		var game = _data.game;
		if (game.gamestate == /*GAME_STATE_NIGHT0_ARRANGE*/2)
		{
			var player = game.players[num];
			if (player.arranged != round)
			{
				if (round >= 0)
				{
					for (var i = 0; i < 10; ++i)
					{
						var p = game.players[i];
						if (p.arranged == round)
						{
							p.arranged = -1;
						}
					}
				}
				player.arranged = round;
				_gameStep(0);
			}
		}
	}
	
	this.shots = function()
	{
		var game = _data.game;
		if (typeof game.shooting[game.round] == 'undefined')
		{
			var shots = {};
			var arranged = -1;
			for (var i = 0; i < 10; ++i)
			{
				var p = game.players[i];
				if (p.state == /*PLAYER_STATE_ALIVE*/0)
				{
					if (p.arranged == game.round)
					{
						arranged = i;
					}
					if (p.role >= /*ROLE_MAFIA*/2)
					{
						shots[i] = arranged;
					}
				}
			}
			for (var i in shots)
			{
				shots[i] = arranged;
			}
			game.shooting[game.round] = shots;
		}
		return game.shooting[game.round];
	}

	this.shoot = function(num, shooterNum)
	{
		var game = _data.game;
		if (game.gamestate == /*GAME_STATE_NIGHT_SHOOTING*/12)
		{
			var shots = mafia.shots();
			if (typeof shooterNum != 'undefined')
			{
				shots[shooterNum] = num;
			}
			else for (var i in shots)
			{
				shots[i] = num;
			}
			
			dirty();
			_callStateChange(/*STATE_CHANGE_FLAG_RESET_TIMER*/1);
		}
	}
	
	function _newGame(id)
	{
		var club = _data.club;
		var lang = club.langs;
		var user = _data.user;
		var rules = _data.club.rules_code;
		var event = club.events[_data.game.event_id];
		var moder_id = (event.flags & /*EVENT_FLAG_ALL_MODERATE*/8) ? 0 : user.id;
		if (typeof id == "undefined")
		{
			id = 0;
		}
		
		if (typeof event != "undefined")
		{
			lang = event.langs;
			if ((event.flags & /*EVENT_FLAG_ALL_MODERATE*/8) == 0)
			{
				mid = user.id;
			}
			rules = event.rules_code;
		}
		if (((lang - 1) & lang) != 0)
		{
			lang = 0;
		}
	
		var game =
		{
			id: id,
			club_id: club.id,
			user_id: user.id,
			moder_id: moder_id,
			lang: lang,
			event_id: _data.game.event_id,
			start_time: 0,
			end_time: 0,
			players: [],
			gamestate: 0,
			round: null,
			player_speaking: null,
			table_opener: null,
			current_nominant: null,
			votings: null,
			shooting: null,
			log: null,
			flags: 0,
			best_player: -1,
			best_move: -1,
			guess3: null,
			rules_code: rules
		}
		
		for (var i = 0; i < 10; ++i)
		{
			game.players.push(
			{
				id: 0, number: i, nick: "", is_male: 1, has_immunity: 0, role: 0, warnings: 0, state: 0,
				kill_round: -1, kill_reason: -1, arranged: -1, don_check: -1, sheriff_check: -1, mute: -1
			});
		}
		return game;
	}

	this.restart = function()
	{
		var game = _data.game;
		game.best_player = -1;
		game.best_move = -1;
		game.guess3 = null;
		if (!_editing)
		{
			game.start_time = game.end_time = 0;
		}
		game.gamestate = /*GAME_STATE_NOT_STARTED*/0;
		game.round = game.player_speaking = game.table_opener = game.current_nominant = game.votings = game.shooting = game.log = null;
		game.flags = 0;
		for (var i = 0; i < 10; ++i)
		{
			var p = game.players[i];
			p.mute = -1;
			p.role = p.warnings = p.state = 0;
			p.kill_round = p.kill_reason = p.arranged = p.don_check = p.sheriff_check = -1;
		}
		_curVoting = null;
		
		dirty();
		_callStateChange(/*STATE_CHANGE_FLAG_RESET_TIMER*/1);
	}

	this.votingKillAll = function(killAll)
	{
		var m = _curVoting.multiple_kill;
		if (typeof killAll != 'undefined')
		{
			var game = _data.game;
			if (game.gamestate == /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9)
			{
				_curVoting.multiple_kill = killAll;
				_callStateChange(/*STATE_CHANGE_FLAG_RESET_TIMER*/0);
			}
		}
		return m;
	}

	this.curVoting = function()
	{
		return _curVoting;
	}
	
	this.postponeMute = function()
	{
		var game = _data.game;
		var p = game.players[game.player_speaking];
		if (p.mute == game.round)
		{
			++p.mute;
			var logRec = 
			{
				type: /*LOGREC_TYPE_POSTPONE_MUTE*/5,
				round: game.round,
				gamestate: game.gamestate,
				player_speaking: game.player_speaking,
				current_nominant: game.current_nominant,
				player: game.player_speaking
			};
			_gameStep(/*STATE_CHANGE_FLAG_RESET_TIMER*/1, logRec);
		}
	}
	
	this.toggleVoting = function()
	{
		if (_curVoting != null)
		{
			var game = _data.game;
			var logRec = 
			{
				round: game.round,
				gamestate: game.gamestate,
				player_speaking: game.player_speaking,
				current_nominant: game.current_nominant,
				player: -1
			};
			if (_curVoting.canceled > 0)
			{
				logRec['type'] = /*LOGREC_TYPE_RESUME_VOTING*/7;
				if (_curVoting.canceled > 1)
					_duplicateVoting(true);
				logRec['player'] = _curVoting.canceled;
				_curVoting.canceled = 0;
			}
			else
			{
				logRec['type'] = /*LOGREC_TYPE_CANCEL_VOTING*/6;
				_curVoting.canceled = 1;
			}
			_gameStep(/*STATE_CHANGE_FLAG_RESET_TIMER*/1, logRec);
		}
	}
	
	this.submit = function()
	{
		var game = _data.game;
		if (game.gamestate >= /*GAME_STATE_MAFIA_WON*/17 && game.gamestate <= /*GAME_STATE_CIVIL_WON*/18)
		{
			_data.game = _newGame();
			_curVoting = null;
			if (game.event_id != 0)
			{
				var req =
				{
					action: 'submit-game',
					time: mafia.time(),
					game: game
				};
				_data.requests.push(req);
				
				for (var i = 0; i < 10; ++i)
				{
					var p = game.players[i];
					if (p.id != 0)
					{
						if (p.state == /*PLAYER_STATE_KILLED_NIGHT*/1 && p.kill_round == 0 && p.kill_reason == /*PLAYER_KR_NORMAL*/0)
						{
							_data.club.players[p.id].flags |= /*U_FLAG_IMMUNITY*/1024;
						}
						else
						{
							_data.club.players[p.id].flags &= ~/*U_FLAG_IMMUNITY*/1024;
						}
					}
				}
			}
			dirty();
			
			if (_data.user.settings.g_autosave != 0)
			{
				if (http.connected())
					mafia.sync();
				else
					mafia.save();
			}
			else if (_data.user.settings.l_autosave != 0)
			{
				mafia.save();
			}
			
			_callStateChange(/*STATE_CHANGE_FLAG_RESET_TIMER*/1);
		}
	}
	
	this.settings = function(lAutosave, gAutosave, flags)
	{
		var s = _data.user.settings;
		lAutosave = Math.floor(lAutosave / 10) * 10;
		gAutosave = Math.floor(gAutosave / 60) * 60;
		if (s.flags != flags || s.l_autosave != lAutosave || s.g_autosave != gAutosave)
		{
			s.flags = flags;
			s.l_autosave = lAutosave;
			s.g_autosave = gAutosave;
			var req =
			{
				action: 'settings',
				l_autosave: lAutosave,
				g_autosave: lAutosave,
				flags: flags
			};
			_data.requests.push(req);
			dirty();
		}
	}
	
	this.votingWinner = function(num, c)
	{
		if (num >= 0 && num < _curVoting.nominants.length && (_data.game.flags & /*GAME_FLAG_SIMPLIFIED_CLIENT*/2))
		{
			var _c = _curVoting.nominants[num].count;
			if (typeof c == "number")
			{
				_curVoting.nominants[num].count = c;
				_voting = null;
				_gameStep(0);
			}
			return _c;
		}
		return 0;
	}
}
