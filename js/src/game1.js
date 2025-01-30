var game; // All vars here can be used by UI code, but it is strongly recommended to use them for reading only. If changes are absolutely needed, make sure gameDirty(...) is called after that.
var regs; // array of players registered for the event
var langs; // array of languages allowed in the event
var _isDirty = false; // signals if the game needs to be saved
var _connectionState = 0; // 0 when connected, 1 when connecting, 2 when disconnected, 3 when error
var _connectionListener; // this function is called when connection status is changed. Parameter is 0 when connected, 1 when connecting, 2 when disconnected, 3 when error
var _errorListener; // parameter type is: 0 - getting game failed; 1 - saving game failed.
var _lastDirtyTime = null; // game time at which function dirty was called last time.
var _gameOnChange; // this function is called every time game changes. Parameter flags is a bit combination of: 
// 1 - players changed 
// 2 - roles changed
// 4 - game time changed
// 8 - rating/non-rating changed
// 16 - language changed
// 32 - moderator changed
// 64 - warnings to players changed
// 128 - gametime has changed. It is 1 when there is a real change in gametime. It is used to decide if the timer has to be reset.

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

function gameInit(eventId, tableNum, roundNum, gameOnChange, errorListener, connectionListener)
{
	_connectionListener = connectionListener;
	_errorListener = errorListener;
	_gameOnChange = gameOnChange;
	json.post('api/ops/game.php', { op: 'get_current', event_id: eventId, table: tableNum, round: roundNum }, function(data)
	{
		game = data.game;
		regs = data.regs;
		langs = data.langs;
		_gameOnChange(0xffff); // all flags are set
		
		// If the game exists in the local storage, we use it instead of the server one.
		// It can exist only when saving the game failed last time.
		if (typeof localStorage == "object")
		{
			var str = localStorage['game'];
			if (typeof str != "undefined" && str != null)
			{
				game = jQuery.parseJSON(str);
			}
		}
		
		// Save the game every second
		setInterval(gameSave, 1000);
		
	}, 
	function (message, data)
	{
		_errorListener(0, message, data);
		return false; // no error dialog
	});
}

function gameSave()
{
	if (_isDirty)
	{
		var gameStr = JSON.stringify(game);
		if (_connectionState != 1) // 1 means that the other request is not finished yet
		{
			var w = http.waiter(statusWaiter);
			json.post('api/ops/game.php', { op: 'set_current', event_id: game.eventId, table: game.table - 1, round: game.round - 1, game: gameStr}, 
			function() // success
			{
				// The game is not needed in the local storage any more because the server has it.
				delete localStorage['game'];
				_isDirty = false;
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

// Call this after each change in the game. It sets the flag that makes the game to be saved
// Flags are for _gameOnChange function see the meainin in the beginning of the file where _gameOnChange is described
function gameDirty(flags)
{
	console.log(JSON.stringify(game, undefined, 2));
//	console.table(game.players);
	_isDirty = true;
	if (game.time == null)
	{
		if (_lastDirtyTime != null)
		{
			_lastDirtyTime = null;
			flags |= 128; // game time changed
		}
	}
	else if (_lastDirtyTime == null || gameCompareTimes(_lastDirtyTime, game.time, true) != 0)
	{
		_lastDirtyTime = structuredClone(game.time);
		flags |= 128; // game time changed
	}
	_gameOnChange(flags);
}

// Cancels the game and deletes the server record. All game data will be lost
function gameCancel()
{
	json.post('api/ops/game.php', { op: 'cancel_current', event_id: game.eventId, table: game.table - 1, round: game.round - 1 }, function()
	{
		goTo({round:undefined});
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
	var deathTime = null;
	var player = game.players[num];
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
				deathTime.time = 'voting';
				deathTime.nominant = num + 1;
				deathTime.votingRound = 0;
				for (var i = 0; i < 10; ++i)
				{
					var p = game.players[i];
					if (isSet(p.voting) && p.voting.length > deathTime.round && isArray(p.voting[deathTime.round]))
					{
						deathTime.votingRound = p.voting[deathTime.round].length;
						break;
					}
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

function _gameWhoSpeaksFirst(round)
{
	// todo: support mafclub rules
	var candidate = 0;
	if (round > 0)
	{
		candidate = _gameWhoSpeaksFirst(round - 1) + 1;
		if (candidate >= 10)
		{
			candidate = 0;
		}
	}
		
	var dayStart = { "round": round, "time": 'night kill speaking' };
	for (var i = 0; i < 10; ++i)
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
	case 'shooting':
		return 2;
	case 'don':
		return 3;
	case 'sheriff':
		return 4;
	case 'night kill speaking':
		return 5;
	case 'speaking':
		return 6;
	case 'voting':
		return 7;
	case 'day kill speaking':
		return 8;
	}
	return 9;
}

// returns: -1 if num1 was nomimaned earlier; 1 if num2; 0 if none of them was nominated, or they are the same player
// num1 and num2 are 1 based. The range is 1-10.
function _whoWasNominatedEarlier(round, num1, num2)
{
	if (num1 != num2)
	{
		var speaksFirst = _gameWhoSpeaksFirst(round);
		var i = speaksFirst;
		do
		{
			var p = game.players[i];
			if (isSet(p.nominating) && round < p.nominating.length)
			{
				var n = p.nominating[round];
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
	
	var round1 = isSet(time1.round) ? time1.round : 0;
	var round2 = isSet(time2.round) ? time1.round : 0;
	if (round1 != round2)
	{
		return round1 - round2;
	}
		
	var t1 = isSet(time1.time) ? time1.time : 'start';
	var t2 = isSet(time2.time) ? time2.time : 'start';
	if (t1 != t2)
	{
		return _gameTimeToInt(t1) - _gameTimeToInt(t2);
	}
		
	var result = 0;
	switch (t1)
	{
	case 'speaking':
		var speaksFirst = _gameWhoSpeaksFirst(round1);
		var speaker1 = (time1.speaker < speaksFirst ? 9 + time1.speaker : time1.speaker);
		var speaker2 = (time2.speaker < speaksFirst ? 9 + time2.speaker : time2.speaker);
		result = speaker1 - speaker2;
		break;

	case 'voting':
		if (time1.votingRound != time2.votingRound)
		{
			result = time1.votingRound - time2.votingRound;
		}
		else if (isSet(time1.nominant))
		{
			if (!isSet(time2.nominant))
			{
				result = isSet(time2.speaker) ? -1 : 1;
			}
			result = _whoWasNominatedEarlier(round1, time1.nominant, time2.nominant);
		}
		else if (isSet(time1.speaker))
		{
			if (!isSet(time2.speaker))
			{
				result = 1;
			}
			result = _whoWasNominatedEarlier(round1, time1.speaker, time2.speaker);
		}
		else
		{
			result = isSet(time2.nominant) || isSet(time2.speaker) ? -1 : 0;
		}
		break;
			
	case 'day kill speaking':
		result = _whoWasNominatedEarlier(round1, time1.speaker, time2.speaker);
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
	for (var i in regs)
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
	var result = -1;
	if (id != 0)
	{
		if (id == game.moderator.id)
		{
			game.moderator.id = 0;
			result = 10;
		}
		for (var i = 0; i < 10; ++i)
		{
			var p = game.players[i];
			if (i != num && p.id == id)
			{
				p.id = 0;
				p.name = '';
				result = i;
			}
		}
	}
	
	var r = gameFindReg(id);
	var p = game.players[num];
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
	
	gameDirty(1);
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
	gameDirty(8);
}
	
function gameSetLang(lang)
{
	game.language = lang;
	gameDirty(16);
}

function gameSetModerator(userId)
{
	var result = -1;
	game.moderator = { id: userId };
	if (userId != 0)
	{
		for (var i = 0; i < 10; ++i)
		{
			var p = game.players[i];
			if (p.id == userId)
			{
				p.id = 0;
				p.name = "";
				result = i;
			}
		}
	}
	gameDirty(32);
	return result;
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
	for (var i = 0; i < 10; ++i)
	{
		var j = Math.floor(Math.random() * 10);
		if (i != j)
		{
			var p = game.players[i];
			game.players[i] = game.players[j];
			game.players[j] = p;
		}
	}
	gameDirty(1);
}

function _gameRoleCounts()
{
	const roleCounts = game.players.reduce((counts, player) =>
	{
		var r = isSet(player.role) ? player.role : 'civ';
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
	var player = game.players[num];
	var oldRole = isSet(player.role) ? player.role : 'civ';
	if (role != oldRole)
	{
		const roleCounts = _gameRoleCounts();
		if (roleCounts[role] >= _gameExpectedRoleCount(role) ||
			roleCounts[oldRole] <= _gameExpectedRoleCount(oldRole))
		{
			for (var i = 9; i >= 0; --i)
			{
				var p = game.players[i];
				var r = isSet(p.role) ? p.role : 'civ';
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
		
		if (gameAreRolesSet() && _gameCheckEnd())
			gameDirty(6);
		else
			gameDirty(2);
	}
}

function gameGenerateRoles()
{
	game.players[0].role = 'sheriff';
	game.players[1].role = 'don';
	game.players[2].role = game.players[3].role = 'maf';
	for (var i = 4; i < 10; ++i)
	{
		delete game.players[i].role;
	}
	for (var i = 0; i < 10; ++i)
	{
		var j = Math.floor(Math.random() * 10);
		if (i != j)
		{
			var r = game.players[i].role;
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
	if (_gameCheckEnd())
		gameDirty(6);
	else
		gameDirty(2);
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
		
		var redAlive = 0;
		var blackAlive = 0;
		for (var i = 0; i < 10; ++i)
		{
			var p = game.players[i];
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

function gamePlayerWarning(num)
{
	var player = game.players[num];
	var dirtyFlags = 64;
	
	_gameIncTimeOrder();
	if (!isSet(player.warnings))
	{
		player.warnings = [];
	}
	player.warnings.push(structuredClone(game.time));
	
	if (player.warnings.length >= 4)
	{
		player.death = { round: game.time.round, type: 'warnings' };
		_gameCheckEnd();
		dirtyFlags |= 4;
	}
	gameDirty(dirtyFlags);
}

function gamePlayerGiveUp(num)
{
	_gameIncTimeOrder();
	game.players[num].death = { 'round': game.time.round, 'type': 'giveUp', 'time': structuredClone(game.time) };
	_gameCheckEnd();
	gameDirty(4);
}

function gamePlayerKickOut(num)
{
	_gameIncTimeOrder();
	game.players[num].death = { 'round': game.time.round, 'type': 'kickOut', 'time': structuredClone(game.time) };
	_gameCheckEnd();
	gameDirty(4);
}

function gamePlayerTeamKickOut(num)
{
	var p = game.players[num];
	
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
	gameDirty(4);
}

function gamePlayerRemoveWarning(num)
{
	var player = game.players[num];
	var i = player.warnings.length - 1;
	if (isSet(player.warnings) && i >= 0)
	{
		var w = player.warnings[i];
		if (gameCompareTimes(w, game.time, true) == 0 && --game.time.order == 0)
		{
			delete game.time.order;
		}
		for (var i = 0; i < 10; ++i)
		{
			if (i == num) continue;
			
			var p = game.players[i];
			if (isSet(p.warnings))
			{
				for (j = p.warnings.length - 1; j >= 0; --j)
				{
					var w1 = p.warnings[j];
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
		gameDirty(64);
	}
}

function gameArrangePlayer(num, night)
{
	if (night > 0)
	{
		for (var i = 0; i < 10; ++i)
		{
			var p = game.players[i];
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
	gameDirty(4);
}

function gameSetBonus(num, points, title, comment)
{
	if ((points || title) && !comment) // comment must be set if either points or title are set
	{
		return false;
	}
	
	var player = game.players[num];
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
	gameDirty(4);
	return true;
}

function gamePlayersCount()
{
	var count = 0;
	for (var i = 0; i < 10; ++i)
	{
		if (!isSet(game.players[i].death))
			++count;
	}
	return count;
}

function gameNextSpeaker()
{
	var nextSpeaker = -1;
	if (isSet(game.time) && game.time.time == 'speaking')
	{
		var first = _gameWhoSpeaksFirst(game.time.round);
		var nextSpeaker = game.time.speaker - 1;
		var p;
		while (1)
		{
			if (++nextSpeaker >= 10)
			{
				nextSpeaker = 0;
			}
			if (nextSpeaker == first)
			{
				break;
			}
			if (!isSet(game.players[nextSpeaker]).death)
			{
				return nextSpeaker;
			}
		}
	}
	return -1;
}

function gameIsVotingCanceled()
{
	for (var i = 0; i < 10; ++i)
	{
		var player = game.players[i];
		if (isSet(player.death))
		{
			if (player.death.type == 'warnings')
			{
				if (player.warnings[3].round == game.time.round)
				{
					return true;
				}
			}
			else if ((player.death.type == 'giveUp' || player.death.type == 'kickOut') && player.death.time.round == game.time.round)
			{
				return true;
			}
		}
	}
	return false;
}

// Returns 0 if the player is not nominated; 1 - if the player is nominated; 2 - if the player is nominated by the currently speaking player.
function gameIsPlayerNominated(num)
{
	++num;
	for (var i = 0; i < 10; ++i)
	{
		var p = game.players[i];
		if (isSet(p.nominating) && p.nominating[game.time.round] == num)
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
		var p = game.players[game.time.speaker - 1];
		if (num < 0)
		{
			if (isSet(p.nominating) && game.time.round < p.nominating.length)
			{
				p.nominating[game.time.round] = null;
				while (p.nominating.length > 0 && p.nominating[p.nominating.length - 1] == null)
				{
					p.nominating.pop();
				}
				if (p.nominating.length == 0)
				{
					delete p.nominating;
				}
				gameDirty(4);
			}
		}
		else
		{
			if (!isSet(p.nominating))
			{
				p.nominating = [];
			}
			for (var i = p.nominating.length; i <= game.time.round; ++i)
			{
				p.nominating.push(null);
			}
			p.nominating[game.time.round] = num + 1;
			gameDirty(4);
		}
	}
}

function gameChangeNomination(num, nomNum)
{
	var p = game.players[num];
	if (nomNum < 0)
	{
		if (isSet(p.nominating) && game.time.round < p.nominating.length)
		{
			p.nominating[game.time.round] = null;
			while (p.nominating.length > 0 && p.nominating[p.nominating.length - 1] == null)
			{
				p.nominating.pop();
			}
			if (p.nominating.length == 0)
			{
				delete p.nominating;
			}
			gameDirty(4);
		}
	}
	else
	{
		++nomNum;
		for (var i = 0; i < 10; ++i)
		{
			var p1 = game.players[i];
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
		for (var i = p.nominating.length; i <= game.time.round; ++i)
		{
			p.nominating.push(null);
		}
		p.nominating[game.time.round] = nomNum;
		gameDirty(4);
	}
}

function gameNext()
{
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
			if (gameAreRolesSet())
			{
				game.time.time = 'arrangement';
			}
			break;
		case 'arrangement':
			game.time.time = 'speaking';
			game.time.speaker = _gameWhoSpeaksFirst(game.time.round) + 1;
			break;
		case 'night kill speaking':
			break;
		case 'speaking':
			do
			{
				if (++game.time.speaker > 10)
				{
					game.time.speaker = 1;
				}
				if (game.time.speaker == _gameWhoSpeaksFirst(game.time.round) + 1)
				{
					game.time.time = 'voting';
					game.time.votingRound = 0;
					break;
				}
			}
			while (isSet(game.players[game.time.speaker - 1].death));
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
			json.post('api/ops/game.php', { op: 'create', json: JSON.stringify(game) }, function()
			{
				goTo({round:undefined});
			});
			break;
		}
	}
	gameDirty(68);
}

function gameBack()
{
	if (isSet(game.time))
	{
		if (isSet(game.time.order))
		{
			if (--game.time.order == 0)
			{
				delete game.time.order;
			}
			
			for (var i = 0; i < 10; ++i)
			{
				var player = game.players[i];
				if (isSet(player.warnings) && player.warnings.length > 0)
				{
					while (player.warnings.length > 0 && gameCompareTimes(player.warnings[player.warnings.length-1], game.time) > 0)
					{
						console.log(player);
						if (player.warnings.length == 4)
						{
							delete player.death;
						}
						player.warnings.pop();
						console.log(player);
					}
					if (player.warnings.length == 0)
					{
						delete player.warnings;
					}
				}
				if (isSet(player.death) && isSet(player.death.time) && gameCompareTimes(player.death.time, game.time) > 0)
				{
					delete player.death;
				}
			}
		}
		else
		{
			switch (game.time.time)
			{
			case 'start':
				delete game.time;
				break;
			case 'arrangement':
				game.time.time = 'start';
				break;
			case 'night kill speaking':
				break;
			case 'speaking':
				gameNominatePlayer(-1);
				do
				{
					if (game.time.speaker == _gameWhoSpeaksFirst(0) + 1)
					{
						delete game.time.speaker;
						if (game.time.round == 0)
						{
							game.time.time = 'arrangement';
						}
						else
						{
							game.time = 'sheriff';
							for (var i = 0; i < 10; ++i)
							{
								var p = game.players[i];
								if (isSet(p.death) && p.death.type == 'night' && p.death.round == game.time.round)
								{
									game.time = 'night kill speaking';
									break;
								}
							}
						}
						break;
					}
					else if (--game.time.speaker <= 0)
					{
						game.time.speaker = 10;
					}
				}
				while (isSet(game.players[game.time.speaker - 1].death));
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
				var maxDeathTime = null;
				var num = -1;
				for (var i = 0; i < 10; ++i)
				{
					var t = _gameGetPlayerDeathTime(i, true);
					if (t != null && t.time != 'end' && (maxDeathTime == null || gameCompareTimes(maxDeathTime, t) < 0))
					{
						maxDeathTime = t;
						num = i;
					}
				}
				if (num >= 0) 
				{
					game.time = maxDeathTime;
					if (game.winner)
					{
						delete game.winner;
					}
					if (game.endTime)
					{
						delete game.endTime;
					}
					if (isSet(maxDeathTime.order))
					{
						gameBack(); // we need to make one more step back to remove the last warning or mod-kill.
						return; // gameDirty is already called by gameBack. No need to call it again.
					}
				}
				break;
			}
			
			// Check if there was an ordered event (like a warning or mod-kill) at this time.
			for (var i = 0; i < 10; ++i)
			{
				var p = game.players[i];
				if (isSet(p.warnings) && p.warnings.length > 0)
				{
					if (gameCompareTimes(p.warnings[p.warnings.length - 1], game.time) > 0)
					{
						game.time = structuredClone(p.warnings[p.warnings.length - 1]);
					}
				}
				if (isSet(p.death) && isSet(p.death.time) && gameCompareTimes(p.death.time, game.time) > 0)
				{
					game.time = structuredClone(p.death.time);
				}
			}
		}
		gameDirty(68);
	}
}
