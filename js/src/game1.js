var version = "1.0"; // It must exactly match the value of GAME_CURRENT_VERSION in include/game.php
var game; // All vars here can be used by UI code, but it is strongly recommended to use them for reading only. If changes are absolutely needed, make sure gameDirty(...) is called after that.
var log; // array of games in the previous times - it is used to return back in time.
var lastSaved; // index in the log array of the last record that is saved to the server.
var regs; // array of players registered for the event
var langs; // array of languages allowed in the event
var _isDirty = false; // signals if the game needs to be saved
var _runSaving = true; // signals if it's a good time to save current game
var _connectionState = 0; // 0 when connected, 1 when connecting, 2 when disconnected, 3 when error
var _connectionListener; // this function is called when connection status is changed. Parameter is 0 when connected, 1 when connecting, 2 when disconnected, 3 when error
var _errorListener; // parameter type is: 0 - getting game failed; 1 - saving game failed; 2 - version mismatch: must do hard page reload.
var _lastDirtyTime = null; // game time at which function dirty was called last time.
var _gameOnChange; // this function is called every time game changes. Parameter flags is a bit combination of: 

var statusWaiter = new function()
{
	function setState(state)
	{
		if (_connectionState != state)
		{
			_connectionState = state;
			if (_connectionListener)
			{
				_connectionListener(state);
			}
			return true;
		}
		return false;
	}

	// returning false cancels the operation
	this.start = function()
	{
		return setState(1);
	}
	
	this.success = function()
	{
		setState(http.connected() ? 0 : 2);
	}
	
	this.error = function(message, onError)
	{
		setState(http.connected() ? 3 : 2);
		if (onError)
		{
			onError(message);
		}
	}
	
	this.info = function(message, title, onClose)
	{
		console.log(message);
		onClose();
	}
	
	this.update = function()
	{
		setState(http.connected() ? 0 : 2);
	}
} // statusWaiter

function _gameCutArray(arr)
{
	while (arr.length > 0 && arr[arr.length - 1] == null)
	{
		arr.pop();
	}
	return arr.length;
}

function gameInit(eventId, tableNum, roundNum, gameOnChange, errorListener, connectionListener, onSuccess)
{
	_connectionListener = connectionListener;
	_errorListener = errorListener;
	_gameOnChange = gameOnChange;
	json.post('api/ops/game.php', { op: 'get_current', lod: 1, event_id: eventId, table: tableNum, round: roundNum }, function(data)
	{
		game = data.game;
		if (!isSet(game.version))
		{
			game.version = version;
		}
		
		if (eventId <= 0)
		{
			if (isSet(game.eventId) && game.eventId > 0)
			{
				console.log('Changed eventId from ' + game.eventId + ' to 0.');
				game.eventId = 0;
			}
			if (game.version != version)
			{
				console.log('Version mismatch the game version is  ' + game.version + '; software version is ' + version);
			}
		}
		else if (game.version != version)
		{
			if (errorListener)
			{
				errorListener(2, l('ErrVersion', version, game.version), data);
			}
			else
			{
				window.location.reload(true);
			}
		}
		
		log = data.log;
		lastSaved = log.length;
		regs = data.regs;
		langs = data.langs;
		
		// If the game exists in the local storage, we use it instead of the server one.
		// It can exist only when saving the game failed last time.
		if (typeof localStorage == "object")
		{
			let str = localStorage['game'];
			if (typeof str != "undefined" && str != null)
			{
				let g = jQuery.parseJSON(str);
				if (g.round == roundNum + 1 && g.table == tableNum + 1)
				{					
					game = g;
				}
			}
		}
		
		// Save the game every second
		setInterval(gameSave, 1000);
		
		if (onSuccess)
		{
			onSuccess(data);
		}
		_gameOnChange(true);
	}, 
	function (message, data)
	{
		_errorListener(0, message, data);
		return false; // no error dialog
	});
}

function gameSave()
{
	if (_isDirty && _runSaving)
	{
		let gameStr = JSON.stringify(game);
		if (_connectionState != 1) // 1 means that the other request is not finished yet
		{
			let w = http.waiter(statusWaiter);
			// console.log('Saving');
			// console.log(lastSaved);
			// console.log(log.slice(lastSaved));
			json.post('api/ops/game.php', { op: 'set_current', event_id: game.eventId, table: game.table - 1, round: game.round - 1, game: JSON.stringify(game), logIndex: lastSaved, log: JSON.stringify(log.slice(lastSaved))}, 
			function() // success
			{
				// The game is not needed in the local storage any more because the server has it.
				delete localStorage['game'];
				_isDirty = false;
				lastSaved = log.length;
			},
			function(message) // error
			{
				// Save it to the local storage if server is not accessible
				if (typeof localStorage == "object")
				{
					localStorage['game'] = gameStr;
				}
				_errorListener(1, message);
			});
			http.waiter(w);
		}
	}
}

function gamePushState()
{
	log.push(structuredClone(game));
}

// Call this after each change in the game. It sets the flag that makes the game to be saved
function gameDirty()
{
//	console.log(JSON.stringify(game, undefined, 2));
	let resetTimer = false;
	_isDirty = true;
	if (game.time == null)
	{
		if (_lastDirtyTime != null)
		{
			_lastDirtyTime = null;
			resetTimer = true;
		}
	}
	else if (_lastDirtyTime == null || gameCompareTimes(_lastDirtyTime, game.time, true) != 0)
	{
		_lastDirtyTime = structuredClone(game.time);
		resetTimer = true;
	}
	_gameOnChange(resetTimer);
}

// Cancels the game and deletes the server record. All game data will be lost
function gameCancel()
{
	json.post('api/ops/game.php', { op: 'cancel_current', event_id: game.eventId, table: game.table - 1, round: game.round - 1 }, function()
	{
		goTo({round:undefined, demo:undefined});
	});
}

// For players who were shot this is stooting time.
// For players who were voted out this is voting to them time
// For players who are not dead - end of the game
// If unknown - null
// num - 0 to 9.
// If includingSpeech is true it returns final speech time instead of voting/shooting time.
function _gameGetPlayerDeathTime(num, includingSpeech)
{
	let deathTime = null;
	let player = game.players[num];
	if (!isSet(player.death))
	{
		deathTime = { 'time': 'end' }
		if (isSet(game.time))
			deathTime.round = game.time.round;
	}
	else if (isSet(player.death.time))
	{
		deathTime = structuredClone(player.death.time);
	}
	else if (isSet(player.death.type))
	{
		if (player.death.type == 'day')
		{
			deathTime = { 'round': player.death.round };
			if (includingSpeech)
			{
				deathTime.time = 'day kill speaking';
				deathTime.speaker = num + 1;
			}
			else
			{
				deathTime.votingRound = 0;
				for (let i = 0; i < 10; ++i)
				{
					let p = game.players[i];
					if (isSet(p.voting) && p.voting.length > deathTime.round && isArray(p.voting[deathTime.round]))
					{
						deathTime.votingRound = p.voting[deathTime.round].length - 1;
						break;
					}
				}
				
				if (deathTime.votingRound == 0)
				{
					deathTime.time = 'voting';
					deathTime.nominee = num + 1;
				}
				else
				{
					deathTime.time = 'voting kill all';
				}
			}
		}
		else if (player.death.type == 'night')
		{
			deathTime = { 'round': player.death.round, 'time': (includingSpeech ? 'night kill speaking' : 'shooting') };
		}
		else if (player.death.type == 'warnings')
		{
			deathTime = structuredClone(player.warnings[3]);
		}
	}
	return deathTime;
}

function gameWhoSpeaksFirst(round)
{
	if (!isSet(round))
	{
		round = game.time.round;
	}
	
	// todo: support mafclub rules
	let candidate = 0;
	if (round > 0)
	{
		candidate = gameWhoSpeaksFirst(round - 1) + 1;
		if (candidate >= 10)
		{
			candidate = 0;
		}
	}
	let dayStart = { "round": round, "time": 'night kill speaking' };
	let i = 0;
	for (; i < 10; ++i)
	{
		if (gameCompareTimes(_gameGetPlayerDeathTime(candidate), dayStart) > 0)
		{
			break;
		}
		else if (++candidate >= 10)
		{
			candidate = 0;
		}
	}
	if (i >= 10)
	{
		return -1;
	}
	return candidate;
}

// time is a string specifying rough time of the game.
function _gameTimeToInt(time)
{
	switch (time)
	{
	case 'start':
		return 0;
	case 'arrangement':
		return 1;
	case 'relaxed sitting':
		return 2;
	case 'night start':
		return 3;
	case 'shooting':
		return 4;
	case 'don':
		return 5;
	case 'sheriff':
		return 6;
	case 'night kill speaking':
		return 7;
	case 'speaking':
		return 8;
	case 'voting start':
		return 9;
	case 'voting':
		return 10;
	case 'voting kill all':
		return 11;
	case 'day kill speaking':
		return 12;
	}
	return 13;
}

// returns: -1 if num1 was nomimaned earlier; 1 if num2; 0 if none of them was nominated, or they are the same player
// num1 and num2 are 1 based. The range is 1-10.
function _gameWhoWasNominatedEarlier(round, num1, num2)
{
	if (num1 != num2)
	{
		let speaksFirst = gameWhoSpeaksFirst(round);
		let i = speaksFirst;
		do
		{
			let p = game.players[i];
			if (isSet(p.nominating) && round < p.nominating.length)
			{
				let n = p.nominating[round];
				if (n == num1)
				{
					return -1;
				}
				if (n == num2)
				{
					return 1;
				}
			}
			
			++i;
			if (i >= 10)
			{
				i = 0;
			}
		} while (i != speaksFirst);
	}
	return 0;
}

// returns <0 if time1 < time2; >0 if time1 > time2; 0 if time1 == time2
// When roughly is true it does not consider order fiels. All times with the same game state are considered the same.
// For example suppose when player 7 speaks in round 2, player 3 gets a warning, then player 4 takes a warning, then player 10 is removed from the game.
// If roughly is false or missing, all four events will appear in order 1. 7 speaks, then 3 gets an order, etc...
// If roughly is true, all four events are considered to happen at the same time - when 7 is speaking.
function gameCompareTimes(time1, time2, roughly)
{
	if (!isSet(time2))
	{
		return isSet(time1) ? 1 : 0;
	}
	if (!isSet(time1))
	{
		return -1;
	}
	
	let round1 = isSet(time1.round) ? time1.round : 0;
	let round2 = isSet(time2.round) ? time2.round : 0;
	if (round1 != round2)
	{
		return round1 - round2;
	}
		
	let t1 = isSet(time1.time) ? time1.time : 'start';
	let t2 = isSet(time2.time) ? time2.time : 'start';
	if (t1 != t2)
	{
		return _gameTimeToInt(t1) - _gameTimeToInt(t2);
	}
		
	let result = 0;
	switch (t1)
	{
	case 'speaking':
		let speaksFirst = gameWhoSpeaksFirst(round1);
		let speaker1 = (time1.speaker <= speaksFirst ? 10 + time1.speaker : time1.speaker);
		let speaker2 = (time2.speaker <= speaksFirst ? 10 + time2.speaker : time2.speaker);
		result = speaker1 - speaker2;
		break;

	case 'voting':
		if (time1.votingRound != time2.votingRound)
		{
			result = time1.votingRound - time2.votingRound;
		}
		else if (isSet(time1.speaker))
		{
			result = isSet(time2.speaker) ? _gameWhoWasNominatedEarlier(time1.round, time1.speaker, time2.speaker) : (isSet(time2.nominee) ? -1 : 1);
		}
		else if (isSet(time1.nominee))
		{
			result = isSet(time2.nominee) ? _gameWhoWasNominatedEarlier(time1.round, time1.nominee, time2.nominee) : 1;
		}
		else
		{
			result = isSet(time2.nominee) || isSet(time2.speaker) ? -1 : 0;
		}
		break;
			
	case 'day kill speaking':
		result = _gameWhoWasNominatedEarlier(round1, time1.speaker, time2.speaker);
		break;
	}
	
	if (result == 0 && !roughly)
	{
		result = (isSet(time1.order) ? time1.order : -1) - (isSet(time2.order) ? time2.order : -1);
	}
	return result;
}

// Find user registration object for the event.
function gameFindReg(userId)
{
	for (let i in regs)
	{
		if (regs[i].id == userId)
		{
			return regs[i];
		}
	}
	return null;
}

function gameSetPlayer(num, id)
{
	let result = -1;
	if (id != 0)
	{
		if (id == game.moderator.id)
		{
			game.moderator.id = 0;
			if (isSet(game.moderator.name))
			{
				delete game.moderator.name;
			}
			result = 10;
		}
		for (let i = 0; i < 10; ++i)
		{
			let p = game.players[i];
			if (i != num && p.id == id)
			{
				p.id = 0;
				p.name = '';
				result = i;
			}
		}
	}
	
	let r = gameFindReg(id);
	if (num >= 0 && num < 10)
	{
		let p = game.players[num];
		if (r)
		{
			p.id = r.id;
			p.name = r.name;
		}
		else
		{
			p.id = 0;
			p.name = '';
		}
	}
	else if (r)
	{
		game.moderator.id = r.id;
		game.moderator.name = r.name;
	}
	else
	{
		game.moderator.id = 0;
		if (isSet(game.moderator.name))
		{
			delete game.moderator.name;
		}
	}
	gameDirty();
	return result;
}

function gameSetIsRating(isRating)
{
	if (!isRating)
	{
		game.rating = false;
	}
	else
	{
		delete game.rating;
	}
	gameDirty();
}
	
function gameSetLang(lang)
{
	game.language = lang;
	gameDirty();
}

function gameIsNight()
{
	if (!isSet(game.time))
	{
		return false;
	}
	switch (game.time.time)
	{
	case 'start':
	case 'arrangement':
	case 'relaxed sitting':
	case 'night start':
	case 'shooting':
	case 'don':
	case 'sheriff':
		return true;
	case 'end':
		return game.winner == 'maf';
	}
	return false;
}

function gameRandomizeSeats()
{
	for (let i = 0; i < 10; ++i)
	{
		let j = Math.floor(Math.random() * 10);
		if (i != j)
		{
			let p = game.players[i];
			game.players[i] = game.players[j];
			game.players[j] = p;
		}
	}
	gameDirty();
}

function _gameRoleCounts()
{
	const roleCounts = game.players.reduce((counts, player) =>
	{
		let r = isSet(player.role) ? player.role : 'civ';
		counts[r] = (counts[r] || 0) + 1;
		return counts;
	},
	{});
	return roleCounts;
}

function _gameExpectedRoleCount(role)
{
	if (role == 'maf')
		return 2;
	if (role == 'don' || role == 'sheriff')
		return 1;
	return 6;
}

function gameAreRolesSet()
{
	const roleCounts = _gameRoleCounts();
	return roleCounts.civ == 6 && roleCounts.maf == 2 && roleCounts.sheriff == 1 && roleCounts.don == 1;
}

function gameSetRole(num, role)
{
	let player = game.players[num];
	let oldRole = isSet(player.role) ? player.role : 'civ';
	if (role != oldRole)
	{
		const roleCounts = _gameRoleCounts();
		if (roleCounts[role] >= _gameExpectedRoleCount(role) ||
			roleCounts[oldRole] <= _gameExpectedRoleCount(oldRole))
		{
			for (let i = 9; i >= 0; --i)
			{
				let p = game.players[i];
				let r = isSet(p.role) ? p.role : 'civ';
				if (r == role)
				{
					if (oldRole == 'civ')
						delete p.role;
					else
						p.role = oldRole;
					break;
				}
			}
		}
		
		if (player.role == 'civ')
			delete player.role;
		else
			player.role = role;
		
		gameDirty();
	}
}

function gameGenerateRoles()
{
	game.players[0].role = 'sheriff';
	game.players[1].role = 'don';
	game.players[2].role = game.players[3].role = 'maf';
	for (let i = 4; i < 10; ++i)
	{
		delete game.players[i].role;
	}
	for (let i = 0; i < 10; ++i)
	{
		let j = Math.floor(Math.random() * 10);
		if (i != j)
		{
			let r = game.players[i].role;
			if (game.players[j].role)
				game.players[i].role = game.players[j].role;
			else
				delete game.players[i].role;
			if (r)
				game.players[j].role = r;
			else
				delete game.players[j].role;
		}
	}
	_gameCheckEnd();
	gameDirty();
}

function _gameEnd(winner)
{
	game.winner = winner;
	game.time = { 'time': 'end', 'round': game.time.round };
	game.endTime = Math.round((new Date()).getTime() / 1000);
}

function _gameCheckEnd()
{
	if (isSet(game.time) && gameAreRolesSet())
	{
		if (game.time.time == 'end')
		{
			return true;
		}
		
		let redAlive = 0;
		let blackAlive = 0;
		for (let i = 0; i < 10; ++i)
		{
			let p = game.players[i];
			if (!isSet(p.death))
			{
				if (isSet(p.role) && (p.role == 'maf' || p.role == 'don'))
				{
					++blackAlive;
				}
				else
				{
					++redAlive;
				}
			}
		}
		
		if (blackAlive <= 0)
		{
			_gameEnd('civ');
			return true;
		}
		
		if (blackAlive >= redAlive)
		{
			_gameEnd('maf');
			return true;
		}
	}
	return false;
}

function _gameCheckTie()
{
	let round = game.time.round - 2;
	if (gameCompareTimes(game.time, {round: game.time.round, time: 'shooting'}) <= 0)
	{
		--round;
	}
	
	if (round <= 0)
	{
		return false;
	}
	
	let lastTime = {round: round, time: 'shooting'};
	for (let i = 0; i < 10; ++i)
	{
		let p = game.players[i];
		if (isSet(p.death))
		{
			let dt = _gameGetPlayerDeathTime(i);
			if (gameCompareTimes(lastTime, dt) <= 0 && gameCompareTimes(dt, game.time) <= 0)
			{
				return false;
			}
		}
	}
	
	_gameEnd('tie');
	return true;
}

function _gameIncTimeOrder()
{
	if (isSet(game.time.order))
	{
		++game.time.order;
	}
	else
	{
		game.time.order = 1;
	}
}

function _gameOnModKill()
{
	if (!_gameCheckEnd())
	{
		if (isSet(game.time))
		{
			if (game.time.time == 'voting start')
			{
				game.time = { round: game.time.round + 1, time: 'night start' };
			}
			else if (game.time.time == 'voting')
			{
				let cancelVoting = true;
				if (isSet(game.time.nominee))
				{
					let winners = gameGetNominees(game.time.round + 1);
					if (winners.length == 1)
					{
						let noms = gameGetNominees();
						for (let i = 0; i < noms.length; ++i)
						{
							if (noms[i] == game.time.nominee)
							{
								break;
							}
							if (noms[i] == winners[0])
							{
								cancelVoting = false;
								break;
							}
						}
					}
				}
				if (cancelVoting)
				{
					for (let i = 0; i < 10; ++i)
					{
						let p = game.players[i];
						if (isSet(p.nominating) && game.time.round < p.nominating.length)
						{
							p.nominating[game.time.round] = null;
							if (_gameCutArray(p.nominating) == 0)
							{
								delete p.nominating;
							}
						}
						if (isSet(p.voting) && game.time.round < p.voting.length)
						{
							p.voting[game.time.round] = null;
							if (_gameCutArray(p.voting) == 0)
							{
								delete p.voting;
							}
						}
					}
					game.time = { round: game.time.round + 1, time: 'night start' };
				}
			}
		}
	}
}

function gamePlayerWarning(num)
{
	let player = game.players[num];
	if (!isSet(player.death))
	{
		gamePushState();
		_gameIncTimeOrder();
		if (!isSet(player.warnings))
		{
			player.warnings = [];
		}
		player.warnings.push(structuredClone(game.time));
		
		if (player.warnings.length >= 4)
		{
			player.death = { round: game.time.round, type: 'warnings' };
			_gameOnModKill();
		}
		gameDirty();
	}
}

function gamePlayerGiveUp(num)
{
	let player = game.players[num];
	if (!isSet(player.death))
	{
		gamePushState();
		_gameIncTimeOrder();
		player.death = { 'round': game.time.round, 'type': 'giveUp', 'time': structuredClone(game.time) };
		_gameOnModKill();
		gameDirty();
	}
}

function gamePlayerKickOut(num)
{
	let player = game.players[num];
	if (!isSet(player.death))
	{
		gamePushState();
		_gameIncTimeOrder();
		player.death = { 'round': game.time.round, 'type': 'kickOut', 'time': structuredClone(game.time) };
		_gameOnModKill();
		gameDirty();
	}
}

function gamePlayerTeamKickOut(num)
{
	let p = game.players[num];
	if (!isSet(p.death))
	{
		gamePushState();
		_gameIncTimeOrder();
		p.death = { 'round': game.time.round, 'type': 'teamKickOut', 'time': structuredClone(game.time) };
		if (isSet(p.role) && (p.role == 'maf' || p.role == 'don'))
		{
			_gameEnd('civ');
		}
		else
		{
			_gameEnd('maf');
		}
		gameDirty();
	}
}

function gamePlayerRemoveWarning(num)
{
	let player = game.players[num];
	let i = player.warnings.length - 1;
	if (isSet(player.warnings) && i >= 0)
	{
		let w = player.warnings[i];
		if (gameCompareTimes(w, game.time, true) == 0 && --game.time.order == 0)
		{
			delete game.time.order;
		}
		for (let i = 0; i < 10; ++i)
		{
			if (i == num) continue;
			
			let p = game.players[i];
			if (isSet(p.warnings))
			{
				for (j = p.warnings.length - 1; j >= 0; --j)
				{
					let w1 = p.warnings[j];
					if (gameCompareTimes(w, w1, true) == 0 && w1.order > w.order)
					{
						--w1.order;
					}
					else
					{
						break;
					}
				}
			}
			if (isSet(p.death) && isSet(p.death.time) && gameCompareTimes(w, p.death.time, true) == 0 && p.death.time.order > w.order)
			{
				--p.death.time.order;
			}
		}
		
		if (player.warnings.length > 1)
			player.warnings.splice(player.warnings.length - 1, 1);
		else if (player.warnings.length == 1)
		{
			delete player.warnings;
		}
		gameDirty();
	}
}

function gameArrangePlayer(num, night)
{
	if (night > 0)
	{
		for (let i = 0; i < 10; ++i)
		{
			let p = game.players[i];
			if (p.arranged == night)
			{
				delete p.arranged;
			}
		}
		game.players[num].arranged = night;
	}
	else
	{
		delete game.players[num].arranged;
	}
	gameDirty();
}

function gameSetBonus(num, points, title, comment)
{
	if ((points || title) && !comment) // comment must be set if either points or title are set
	{
		return false;
	}
	
	let player = game.players[num];
	if (points)
	{
		if (title)
		{
			player.bonus = [points, title];
		}
		else
		{
			player.bonus = points;
		}
	}
	else if (title)
	{
		player.bonus = title;
	}
	else if (player.bonus)
	{
		delete player.bonus;
	}
	if (comment)
	{
		player.comment = comment;
	}
	else if (player.comment)
	{
		delete player.comment;
	}
	gameDirty();
	return true;
}

function gamePlayersCount()
{
	let count = 0;
	for (let i = 0; i < 10; ++i)
	{
		if (!isSet(game.players[i].death))
			++count;
	}
	return count;
}

function gameNextSpeaker()
{
	let nextSpeaker = -1;
	if (isSet(game.time) && game.time.time == 'speaking')
	{
		let first = gameWhoSpeaksFirst();
		let nextSpeaker = game.time.speaker - 1;
		let p;
		while (true)
		{
			if (++nextSpeaker >= 10)
			{
				nextSpeaker = 0;
			}
			if (nextSpeaker == first)
			{
				break;
			}
			if (!isSet(game.players[nextSpeaker].death))
			{
				return nextSpeaker;
			}
		}
	}
	return -1;
}

function _gameDayKillDeathTime(round)
{
	for (let i = 0; i < 10; ++i)
	{
		let p = game.players[i];
		if (isSet(p.death) && p.death.type == 'day' && p.death.round == round)
		{
			return _gameGetPlayerDeathTime(i);
		}
	}
	return null;
}

function gameIsVotingCanceled()
{
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
		if (isSet(player.death))
		{
			let r = -1;
			if (player.death.type == 'warnings')
			{
				r = player.warnings[3].round;
			}
			else if ((player.death.type == 'giveUp' || player.death.type == 'kickOut'))
			{
				r = player.death.round;
			}

			if (r == game.time.round)
			{
				let d = _gameDayKillDeathTime(r);
				if (d == null || gameCompareTimes(d, _gameGetPlayerDeathTime(i)) >= 0)
				{
					return true;
				}
			}
			else if (r == game.time.round - 1)
			{
				let d = _gameDayKillDeathTime(r);
				if (d != null && gameCompareTimes(d, _gameGetPlayerDeathTime(i)) < 0)
				{
					return true;
				}
			}
		}
	}
	return false;
}

// Returns 0 if the player is not nominated; 1 - if the player is nominated; 2 - if the player is nominated by the currently speaking player.
function gameIsPlayerNominated(num)
{
	++num;
	for (let i = 0; i < 10; ++i)
	{
		let p = game.players[i];
		if (isSet(p.nominating) && game.time.round < p.nominating.length && p.nominating[game.time.round] == num)
		{
			return game.time.time == 'speaking' && game.time.speaker == i + 1 ? 2 : 1;
		}
	}
	return 0;
}

function gameNominatePlayer(num)
{
	if (game.time.time == 'speaking' && !gameIsPlayerNominated(num))
	{
		let p = game.players[game.time.speaker - 1];
		if (!isSet(p.death))
		{
			if (num < 0)
			{
				if (isSet(p.nominating) && game.time.round < p.nominating.length)
				{
					p.nominating[game.time.round] = null;
					if (_gameCutArray(p.nominating) == 0)
					{
						delete p.nominating;
					}
					gameDirty();
				}
			}
			else if (!isSet(game.players[num].death))
			{
				if (!isSet(p.nominating))
				{
					p.nominating = [];
				}
				for (let i = p.nominating.length; i <= game.time.round; ++i)
				{
					p.nominating.push(null);
				}
				p.nominating[game.time.round] = num + 1;
				gameDirty();
			}
		}
	}
}

function gameChangeNomination(num, nomNum)
{
	let p = game.players[num];
	if (nomNum < 0)
	{
		if (isSet(p.nominating) && game.time.round < p.nominating.length)
		{
			p.nominating[game.time.round] = null;
			if (_gameCutArray(p.nominating) == 0)
			{
				delete p.nominating;
			}
			gameDirty();
		}
	}
	else
	{
		++nomNum;
		for (let i = 0; i < 10; ++i)
		{
			let p1 = game.players[i];
			if (isSet(p1.nominating) && game.time.round < p1.nominating.length && p1.nominating[game.time.round] == nomNum)
			{
				if (i == num)
				{
					return;
				}
				p1.nominating[game.time.round] = null;
				while (p1.nominating.length > 0 && p1.nominating[p1.nominating.length - 1] == null)
				{
					p1.nominating.pop();
				}
				if (p1.nominating.length == 0)
				{
					delete p1.nominating;
				}
			}
		}
		
		if (!isSet(p.nominating))
		{
			p.nominating = [];
		}
		for (let i = p.nominating.length; i <= game.time.round; ++i)
		{
			p.nominating.push(null);
		}
		p.nominating[game.time.round] = nomNum;
		gameDirty();
	}
}

// num is 1 to 10 or -1 to -10. Positive values mean that player is left as town, negative - as mafia.
// The function toggles the onRecord state. If the player is already left on record with the same role, the record is removed.
function gameSetOnRecord(num)
{
	let t = game.time.time;
	if (isSet(game.time.speaker))
	{
		let player = game.players[game.time.speaker - 1];
		if (!isSet(player.record))
		{
			player.record = [];
		}
		else if (player.record.length > 0)
		{
			let r = player.record[player.record.length - 1];
			if (
				r.time == t && r.round == game.time.round &&
				(!isSet(r.votingRound) || !isSet(game.time.votingRouns) || r.votingRound == game.time.votingRouns))
			{
				for (let i = 0; i < r.record.length; ++i)
				{
					let n = r.record[i];
					if (n == num)
					{
						r.record.splice(i, 1);
						if (r.record.length == 0)
						{
							player.record.pop();
							if (player.record.length == 0)
							{
								delete player.record;
							}
						}
						gameDirty();
						return;
					}
					else if (n == -num)
					{
						r.record[i] = num;
						gameDirty();
						return;
					}
				}
				r.record.push(num);
				gameDirty();
				return;
			}
		}
		let r = { time: t, round: game.time.round, record: [num]};
		if (isSet(game.time.votingRound))
		{
			r.votingRound = game.time.votingRound;
		}
		player.record.push(r);
		gameDirty();
	}
}

// removes the speaking players on record var if exists for the gameBack function
function _gameRemoveOnRecord()
{
	if (game.time.time == 'speaking')
	{
		let player = game.players[game.time.speaker - 1];
		if (isSet(player.record) && player.record.length > 0)
		{
			let r = player.record[player.record.length - 1];
			if (r.time == game.time.time && r.round == game.time.round)
			{
				player.record.pop();
			}
		}
	}
}

function gameGetNominees(votingRound)
{
	if (!isSet(votingRound))
	{
		votingRound = isSet(game.time.votingRound) ? game.time.votingRound : 0;
	}
	
	let noms = [];
	if (votingRound > 0)
	{
		let votes = [0,0,0,0,0,0,0,0,0,0];
		for (let i = 0; i < 10; ++i)
		{
			let p = game.players[i];
			if (isSet(p.voting) && game.time.round < p.voting.length && p.voting[game.time.round] != null)
			{
				if (isArray(p.voting[game.time.round]))
				{
					if (votingRound <= p.voting[game.time.round].length)
					{
						++votes[p.voting[game.time.round][votingRound - 1] - 1];
					}
				}
				else if (votingRound == 1)
				{
					++votes[p.voting[game.time.round] - 1];
				}
			}
		}
		let max = 0;
		for (const v of votes)
		{
			max = Math.max(v, max);
		}
		if (max > 0)
		{
			let first = gameWhoSpeaksFirst();
			let i = first;
			do
			{
				let p = game.players[i];
				if (isSet(p.nominating) && game.time.round < p.nominating.length)
				{
					let n = p.nominating[game.time.round];
					if (votes[n-1] == max)
					{
						noms.push(n);
					}
				}
				if (++i >= 10)
				{
					i = 0;
				}
			}
			while (i != first);
		}
	}
	else
	{
		let first = gameWhoSpeaksFirst();
		let i = first;
		do
		{
			let p = game.players[i];
			if (isSet(p.nominating) && game.time.round < p.nominating.length && p.nominating[game.time.round] != null)
			{
				noms.push(p.nominating[game.time.round]);
			}
			if (++i >= 10)
			{
				i = 0;
			}
		}
		while (i != first);
	}
	return noms;
}

function gameGetVotingWinners()
{
	for (let player of game.players)
	{
		if (!isSet(player.death) && isSet(player.voting) && game.time.round < player.voting.length)
		{
			let v = player.voting[game.time.round];
			if (isArray(v))
			{
				if (isBool(v[v.length-1]))
					return gameGetNominees(v.length - 1);
				return gameGetNominees(v.length);
			}
			break;
		}
	}
	return gameGetNominees(1);
}

// nominee is a numer of player 1-10
function gameGetVotesCount(nominee)
{
	let count = 0;
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
		if (isSet(player.voting) && game.time.round < player.voting.length)
		{
			let v = player.voting[game.time.round];
			if (
				(isNumber(v) && v == nominee) ||
				(isArray(v) && game.time.votingRound < v.length && v[game.time.votingRound] == nominee))
			{
				++count;
			}
		}
	}
	return count;
}

// voter - player who votes 0-9
// type - 0 - toggle, 1 - vote, -1 unvote
// returns 0 if nothing happens, 1 if voted; -1 if unvoted
function _gameVote(voter, type)
{
	let player = game.players[voter];
	let arr = player.voting;
	let index = game.time.round;
	if (index >= arr.length)
	{
		return 0;
	}
	if (game.time.votingRound > 0)
	{
		arr = arr[index];
		index = game.time.votingRound;
		if (index >= arr.length)
		{
			return 0;
		}
	}
	if (_gameWhoWasNominatedEarlier(game.time.round, arr[index], game.time.nominee) < 0)
	{
		return 0;
	}
	
	if (arr[index] == game.time.nominee)
	{
		if (type <= 0)
		{
			let noms = gameGetNominees();
			if (noms[noms.length-1] != game.time.nominee)
			{
				arr[index] = noms[noms.length-1];
				return -1;
			}
		}
	}
	else if (arr[index] != game.time.nominee && type >= 0)
	{
		arr[index] = game.time.nominee;
		return 1;
	}
	return 0;
}

// voter is 0-9
function gameVote(voter)
{
	if (game.time.time == 'voting' && isSet(game.time.nominee))
	{
		let result = _gameVote(voter, 0);
		if (result != 0)
		{
			// For round 0 if all players are alive and nobody voted yet - next 4 players should also vote
			if (result > 0 && game.time.round == 0)
			{
				let nobodyVoted = true;
				for (let i = 0; i < 10; ++i)
				{
					if (i != voter)
					{
						let player = game.players[i];
						let arr = player.voting;
						let index = game.time.round;
						if (index >= arr.length)
						{
							nobodyVoted = false;
							break;
						}
						if (game.time.votingRound > 0)
						{
							arr = arr[index];
							index = game.time.votingRound;
							if (index >= arr.length)
							{
								nobodyVoted = false;
								break;
							}
						}
						if (_gameWhoWasNominatedEarlier(game.time.round, arr[index], game.time.nominee) <= 0)
						{
							nobodyVoted = false;
							break;
						}
					}
				}
				if (nobodyVoted)
				{
					++voter;
					for (let i = 0; i < 4; ++i)
					{
						if (voter >= 10)
						{
							voter = 0;
						}
						_gameVote(voter, 1);
						++voter;
					}
				}
			}
			gameDirty();
		}
	}
}

// num is 0-9; when num is not set - create voting for all alive players
function _gameCreateVoting(num)
{
	if (game.time.time == 'voting' && isSet(game.time.nominee))
	{
		let noms = gameGetNominees();
		if (isSet(num))
		{
			let player = game.players[num];
			if (!isSet(player.death))
			{
				if (!isSet(player.voting))
				{
					player.voting = [];
				}
				for (let i = player.voting.length; i <= game.time.round; ++i)
				{
					player.voting.push(null);
				}
				if (game.time.votingRound > 0)
				{
					if (!isArray(player.voting[game.time.round]))
					{
						player.voting[game.time.round] = [player.voting[game.time.round]];
					}
					
					let a = player.voting[game.time.round];
					for (let i = a.length; i <= game.time.votingRound; ++i)
					{
						a.push(null);
					}
					a[game.time.votingRound] = noms[noms.length - 1];
				}
				else if (noms.length > (game.time.round > 0 ? 0 : 1))
				{
					player.voting[game.time.round] = noms[noms.length - 1];
				}
			}
		}
		else
		{
			for (let i = 0; i < 10; ++i)
			{
				_gameCreateVoting(i);
			}
		}
	}
}

// vote: true - all vote for nominee; false - nobody votes for nominee
function gameVoteAll(vote)
{
	if (game.time.time == 'voting' && isSet(game.time.nominee))
	{
		let changed = false;
		for (let i = 0; i < 10; ++i)
		{
			if (_gameVote(i, vote ? 1 : -1) != 0)
			{
				changed = true;
			}
		}
		if (changed)
		{
			gameDirty();
		}
	}
}

function gameVoteToKillAll(voter)
{
	let player = game.players[voter];
	player.voting[game.time.round][game.time.votingRound] = !player.voting[game.time.round][game.time.votingRound];
	gameDirty();
}

function gameAllVoteToKillAll(vote)
{
	for (let player of game.players)
	{
		if (!isSet(player.death))
		{
			player.voting[game.time.round][game.time.votingRound] = vote;
		}
	}		
	gameDirty();
}

// returns an array of shots in the form [[maf1Index, maf1Shot], [maf2Index, maf2Shot], [maf3Index, maf3Shot]] all values 0-9
function gameGetShots()
{
	let result = [];
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
		if (!isSet(player.death) && isSet(player.role) && (player.role == 'maf' || player.role == 'don'))
		{
			let shot = null;
			if (isSet(player.shooting) && game.time.round <= player.shooting.length)
			{
				shot = player.shooting[game.time.round - 1] - 1;
			}
			result.push([i, shot]);
		}
	}
	return result;
}

function gameShoot(target, shooter, noDirty)
{
	if (isSet(shooter))
	{
		let player = game.players[shooter];
		if (!isSet(player.shooting))
		{
			player.shooting = [];
		}
		for (let i = player.shooting.length; i < game.time.round; ++i)
		{
			player.shooting.push(null);
		}
		player.shooting[game.time.round - 1] = parseInt(target) + 1;
		if (!isSet(noDirty))
		{
			gameDirty();
		}
	}
	else
	{
		for (let i = 0; i < 10; ++i)
		{
			let player = game.players[i];
			if (!isSet(player.death) && isSet(player.role) && (player.role == 'maf' || player.role == 'don'))
			{
				gameShoot(target, i, true);
			}
		}
		gameDirty();
	}
}

// Returns player index 0-9 killed in this round. 
// -1 if nobody is killed.
function gameGetNightKill()
{
	let killedIndex = -1;
	for (let s of gameGetShots())
	{
		if (s[1] == null)
		{
			return -1;
		}
		if (killedIndex < 0)
		{
			killedIndex = s[1];
		}
		else if (killedIndex != s[1])
		{
			return -1;
		}
	}
	return killedIndex;
}

// returns true if players voted to kill all in this round
function _gameKillAll()
{
	let killAll = 0;
	for (const player of game.players)
	{
		if (!isSet(player.death))
		{
			if (player.voting[game.time.round][game.time.votingRound])
			{
				++killAll;
			}
			else
			{
				--killAll;
			}
		}
	}
	return killAll > 0;
}

function _gameIsAlive(who)
{
	for (player of game.players)
	{
		if (isSet(player.role) && player.role == who)
		{
			if (!isSet(player.death) || (player.death.type == 'night' && player.death.round == game.time.round))
			{
				return true;
			}
		}
	}
	return false;
}

function gameIsDonAlive()
{
	return _gameIsAlive('don');
}

function gameIsSheriffAlive()
{
	return _gameIsAlive('sheriff');
}

// index 0-9
function gameDonCheck(index)
{
	let dirty = false;
	for (let i = 0; i < 10; ++i)
	{
		let p = game.players[i];
		if (i != index && isSet(p.don) && p.don == game.time.round)
		{
			delete p.don;
			dirty = true;
		}
	}
	if (index >= 0)
	{
		let player = game.players[index];
		if (!isSet(player.don))
		{
			player.don = game.time.round;
			dirty = true;
		}
	}
	if (dirty)
	{
		gameDirty();
	}
}

// index 0-9
function gameSheriffCheck(index)
{
	let dirty = false;
	for (let i = 0; i < 10; ++i)
	{
		let p = game.players[i];
		if (i != index && isSet(p.sheriff) && p.sheriff == game.time.round)
		{
			delete p.sheriff;
			dirty = true;
		}
	}
	if (index >= 0)
	{
		let player = game.players[index];
		if (!isSet(player.sheriff))
		{
			player.sheriff = game.time.round;
			dirty = true;
		}
	}
	if (dirty)
	{
		gameDirty();
	}
}

function gameSetLegacy(index)
{
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
		if (isSet(player.death) && player.death.type == 'night' && player.death.round == 1)
		{
			if (++index > 0)
			{
				if (!isSet(player.legacy))
				{
					player.legacy = [];
				}
				let j = 0;
				for (; j < player.legacy.length; ++j)
				{
					if (player.legacy[j] == index)
					{
						break;
					}
				}
				if (j < player.legacy.length)
				{
					player.legacy.splice(j, 1);
					if (player.legacy.length == 0)
					{
						delete player.legacy;
					}
				}
				else
				{
					player.legacy.push(index);
					if (player.legacy.length > 3)
					{
						player.legacy.splice(0, player.legacy.length - 3);
					}
				}
				gameDirty();
			}
			else if (isSet(player.legacy))
			{
				delete player.legacy;
				gameDirty();
			}
			break;
		}
	}
}

function gameIsInLegacy(index)
{
	++index;
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
		if (isSet(player.death) && player.death.type == 'night' && player.death.round == 1)
		{
			if (isSet(player.legacy))
			{
				for (let n of player.legacy)
				{
					if (n == index)
					{
						return true;
					}
				}
			}
			break;
		}
	}
	return false;
}

function gameIsPlayerAtTheTable(index)
{
	if (!isSet(game.time))
	{
		return false;
	}

	let player = game.players[index];
	if (!isSet(player.death))
	{
		return true;
	}
	
	if (player.death.round != game.time.round)
	{
		return false;
	}
	
	switch (game.time.time)
	{
	case 'night kill speaking':
	case 'don':
	case 'sheriff':
		if (player.death.type == 'night')
		{
			return true;
		}
		break;
	case 'day kill speaking':
		if (player.death.type == 'day')
		{
			if (game.time.speaker == index + 1)
			{
				return true;
			}
			
			let noms = gameGetNominees();
			for (let n of noms)
			{
				if (n == index + 1)
				{
					return false;
				}
				if (n == game.time.speaker)
				{
					return true;
				}
			}
		}
		break;
	case 'end':
		if (player.death.type == 'night' || player.death.type == 'day')
		{
			let dt = _gameGetPlayerDeathTime(index);
			for (let i = 0; i < 10; ++i)
			{
				if (i != index)
				{
					let p = game.players[i];
					if (isSet(p.death))
					{
						if (gameCompareTimes(_gameGetPlayerDeathTime(i), dt) > 0)
						{
							return false;
						}
					}
				}
			}
			return true;
		}
		break;
	}
	return false;
}

function gameBugReport(txt, onSuccess)
{
	json.post('api/ops/game.php', { op: 'report_bug', event_id: game.eventId, table: game.table - 1, round: game.round - 1, comment: txt}, onSuccess);
}

function gameCanGoNext()
{
	if (isSet(game.time) && game.time.time == 'start' && !gameAreRolesSet())
	{
		return false;
	}
	return true;
}

function gameCanGoBack()
{
	return log.length > 0;
}

function gameHasFeature(letter)
{
	return !isSet(game.features) || game.features.includes(letter);
}

function gameSetFeature(letter, on)
{
	if (!isSet(game.features))
	{
		game.features = 'agsdutclhvknwro';
	}
	
	if (!on)
	{
		let features = game.features.replace(letter, '');
		if (features != game.features)
		{
			game.features = features;
			gameDirty();
		}
	}
	else if (!game.features.includes(letter))
	{
		game.features += letter;
		gameDirty();
	}
}

function gameNext()
{
	if (gameCanGoNext())
	{
		gamePushState();
		if (!isSet(game.time))
		{
			game.time = { time: 'start', round: 0 };
			if (!isSet(game.startTime))
			{
				game.startTime = Math.round((new Date()).getTime() / 1000);
			}
		}
		else 
		{
			if (isSet(game.time.order))
			{
				delete game.time.order;
			}
			switch (game.time.time)
			{
			case 'start':
				game.time.time = 'arrangement';
				break;
			case 'arrangement':
				game.time.time = 'relaxed sitting';
				break;
			case 'relaxed sitting':
				game.time.time = 'speaking';
				game.time.speaker = gameWhoSpeaksFirst() + 1;
				break;
			case 'night kill speaking':
				game.time = { time: 'speaking', round: game.time.round, speaker: (gameWhoSpeaksFirst() + 1) };
				break;
			case 'speaking':
				let first = gameWhoSpeaksFirst();
				do
				{
					if (++game.time.speaker > 10)
					{
						game.time.speaker = 1;
					}
					if (game.time.speaker == first + 1)
					{
						let round = game.time.round;
						if (gameIsVotingCanceled())
						{
							game.time = { round: round + 1, time: 'night start' };
						}
						else
						{
							let noms = gameGetNominees();
							if (noms.length == 0 || (noms.length == 1 && round == 0))
							{
								game.time = { round: round + 1, time: 'night start' };
							}
							else
							{
								game.time = { round: round, time: 'voting start' };
							}
						}
						break;
					}
				}
				while (isSet(game.players[game.time.speaker - 1].death));
				break;
			case 'voting start':
			{
				let noms = gameGetNominees();
				let round = game.time.round;
				if (noms.length == 0 || (noms.length == 1 && round == 0))
				{
					game.time = { round: round + 1, time: 'night start' };
				}
				else
				{
					game.time = { round: round, time: 'voting', votingRound: 0, nominee: noms[0] };
					_gameCreateVoting();
				}
				break;
			}
			case 'voting':
				if (isSet(game.time.nominee))
				{
					let noms = gameGetNominees();
					let i = noms.length;
					for (i = 1; i < noms.length; ++i)
					{
						if (noms[i-1] == game.time.nominee)
						{
							game.time.nominee = noms[i];
							break;
						}
					}
					if (i >= noms.length)
					{
						let winners = gameGetNominees(game.time.votingRound + 1);
						if (winners.length == 1)
						{
							var player = game.players[winners[0] - 1];
							player.death = { type: 'day', round: game.time.round };
							if (!_gameCheckEnd())
							{
								game.time = { time: 'day kill speaking', speaker: winners[0], round: game.time.round };
							}
						}
						else if (game.time.votingRound > 0 && winners.length == noms.length)
						{
							game.time = { time: 'voting kill all', round: game.time.round, votingRound: game.time.votingRound + 1 };
							let noms = gameGetNominees();
							for (let player of game.players)
							{
								if (!isSet(player.death))
								{
									player.voting[game.time.round].push(false);
								}
							}
						}
						else
						{
							delete game.time.nominee;
							game.time.speaker = winners[0];
							++game.time.votingRound;
						}
					}
				}
				else // isSet(game.time.speaker) should always be true
				{
					let noms = gameGetNominees();
					let i = noms.length;
					for (i = 1; i < noms.length; ++i)
					{
						if (noms[i-1] == game.time.speaker)
						{
							game.time.speaker = noms[i];
							break;
						}
					}
					if (i >= noms.length)
					{
						delete game.time.speaker;
						game.time.nominee = noms[0];
						_gameCreateVoting();
					}
				}
				break;
			case 'voting kill all':
				if (_gameKillAll())
				{
					let noms = gameGetNominees();
					for (nom of noms)
					{
						game.players[nom - 1].death = { type: 'day', round: game.time.round };
					}
					if (!_gameCheckEnd())
					{
						game.time = { time: 'day kill speaking', round: game.time.round, speaker: noms[0] };
					}
				}
				else
				{
					game.time = { time: 'night start', round: game.time.round + 1 };
				}
				break;
			case 'day kill speaking':
			{
				noms = gameGetVotingWinners();
				let i = 0;
				for (; i < noms.length; ++i)
				{
					if (noms[i] == game.time.speaker)
					{
						break;
					}
				}
				if (i >= noms.length - 1)
				{
					game.time = { time: 'night start', round: game.time.round + 1 };
				}
				else
				{
					game.time.speaker = noms[i+1];
				}
				break;
			}
			case 'night start':
				game.time.time = 'shooting';
				for (let i = 0; i < 10; ++i)
				{
					let p = game.players[i];
					if (isSet(p.arranged) && p.arranged == game.time.round)
					{
						for (let j = 0; j < 10; ++j)
						{
							let p1 = game.players[j];
							if (!isSet(p1.death) && isSet(p1.role) && (p1.role == 'maf' || p1.role == 'don'))
							{
								gameShoot(i, j, true);
							}
						}
						break;
					}
				}
				break;
			case 'shooting':
			{
				game.time.time = 'don';
				let killed = gameGetNightKill();
				if (killed >= 0)
				{
					game.players[killed].death = { type: 'night', round: game.time.round };
					_gameCheckEnd();
				}
				else
				{
					_gameCheckTie();
				}
				break;
			}
			case 'don':
				game.time.time = 'sheriff';
				break;
			case 'sheriff':
			{
				let killed = gameGetNightKill();
				if (killed >= 0)
				{
					game.time = { time: 'night kill speaking', round: game.time.round, speaker: killed + 1 };
				}
				else
				{
					game.time = { time: 'speaking', round: game.time.round, speaker: gameWhoSpeaksFirst() + 1 };
				}
				break;
			}
			case 'end':
				_runSaving = false; // stop saving current game
				json.post('api/ops/game.php', { op: 'create', json: JSON.stringify(game) }, function()
				{
					delete localStorage['game'];
					goTo({round:undefined, demo:undefined});
				},
				function (message, data)
				{
					_runSaving = true; // resume saving current game in case of error
					return true;
				});
				return; // Bypass gameDirty() - it is not needed any more
			}
		}
		gameDirty();
	}
}

function gameBack()
{
	if (gameCanGoBack())
	{
		let g = null;
		while (log.length > 0 && g == null)
		{
			g = log[log.length-1];
			log.pop();
		}
		if (g != null)
		{
			game = g;
		}
		lastSaved = Math.min(lastSaved, log.length);
		gameDirty();
	}
}
