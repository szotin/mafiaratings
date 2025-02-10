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

function _gameCutArray(arr)
{
	while (arr.length > 0 && arr[arr.length - 1] == null)
	{
		arr.pop();
	}
	return arr.length;
}

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
			let str = localStorage['game'];
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
		let gameStr = JSON.stringify(game);
		if (_connectionState != 1) // 1 means that the other request is not finished yet
		{
			let w = http.waiter(statusWaiter);
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
						deathTime.votingRound = p.voting[deathTime.round].length;
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

function _gameWhoSpeaksFirst(round)
{
	// todo: support mafclub rules
	let candidate = 0;
	if (round > 0)
	{
		candidate = _gameWhoSpeaksFirst(round - 1) + 1;
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
	case 'night start':
		return 2;
	case 'shooting':
		return 3;
	case 'don':
		return 4;
	case 'sheriff':
		return 5;
	case 'night kill speaking':
		return 6;
	case 'speaking':
		return 7;
	case 'voting':
		return 8;
	case 'voting kill all':
		return 9;
	case 'day kill speaking':
		return 10;
	}
	return 11;
}

// returns: -1 if num1 was nomimaned earlier; 1 if num2; 0 if none of them was nominated, or they are the same player
// num1 and num2 are 1 based. The range is 1-10.
function _whoWasNominatedEarlier(round, num1, num2)
{
	if (num1 != num2)
	{
		let speaksFirst = _gameWhoSpeaksFirst(round);
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
	let round2 = isSet(time2.round) ? time1.round : 0;
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
		let speaksFirst = _gameWhoSpeaksFirst(round1);
		let speaker1 = (time1.speaker < speaksFirst ? 9 + time1.speaker : time1.speaker);
		let speaker2 = (time2.speaker < speaksFirst ? 9 + time2.speaker : time2.speaker);
		result = speaker1 - speaker2;
		break;

	case 'voting':
		if (time1.votingRound != time2.votingRound)
		{
			result = time1.votingRound - time2.votingRound;
		}
		else if (isSet(time1.nominee))
		{
			result = isSet(time2.nominee) ? _whoWasNominatedEarlier(time1.round, time1.nominee, time2.nominee) : (isSet(time2.speaker) ? -1 : 1);
		}
		else if (isSet(time1.speaker))
		{
			result = isSet(time2.speaker) ? _whoWasNominatedEarlier(time1.round, time1.speaker, time2.speaker) : 1;
		}
		else
		{
			result = isSet(time2.nominee) || isSet(time2.speaker) ? -1 : 0;
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
	let result = -1;
	game.moderator = { id: userId };
	if (userId != 0)
	{
		for (let i = 0; i < 10; ++i)
		{
			let p = game.players[i];
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
	gameDirty(1);
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
	let player = game.players[num];
	let dirtyFlags = 64;
	
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
	let p = game.players[num];
	
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
		gameDirty(64);
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
	gameDirty(4);
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
	gameDirty(4);
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
		let first = _gameWhoSpeaksFirst(game.time.round);
		let nextSpeaker = game.time.speaker - 1;
		let p;
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
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
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
	for (let i = 0; i < 10; ++i)
	{
		let p = game.players[i];
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
		let p = game.players[game.time.speaker - 1];
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
			for (let i = p.nominating.length; i <= game.time.round; ++i)
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
	let p = game.players[num];
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
		gameDirty(4);
	}
}

// num is 1 to 10 or -1 to -10. Positive values mean that player is left as town, negative - as mafia.
// The function toggles the onRecord state. If the player is already left on record with the same role, the record is removed.
function gameSetOnRecord(num)
{
	let t = game.time.time;
	if (t == 'speaking')
	{
		let player = game.players[game.time.speaker - 1];
		if (!isSet(player.record))
		{
			player.record = [];
		}
		else if (player.record.length > 0)
		{
			let r = player.record[player.record.length - 1];
			if (r.time == t && r.round == game.time.round)
			{
				for (let i = 0; i < r.record.length; ++i)
				{
					let n = r.record[i];
					if (n == num)
					{
						r.record.splice(i, 1);
						gameDirty(4);
						return;
					}
					else if (n == -num)
					{
						r.record[i] = num;
						gameDirty(4);
						return;
					}
				}
				r.record.push(num);
				gameDirty(4);
				return;
			}
		}
		player.record.push({ time: t, round: game.time.round, record: [num]});
		gameDirty(4);
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
			for (let i = 0; i < 10; ++i)
			{
				if (votes[i] == max)
				{
					noms.push(i + 1);
				}
			}
		}
	}
	else
	{
		let first = _gameWhoSpeaksFirst(game.time.round);
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

// voter is 0-9
function gameVote(voter)
{
	if (game.time.time == 'voting' && isSet(game.time.nominee))
	{
		let player = game.players[voter];
		let arr = player.voting;
		let index = game.time.round;
		if (game.time.votingRound > 0)
		{
			arr = arr[index];
			index = game.time.votingRound;
		}
		
		if (arr[index] == game.time.nominee)
		{
			let noms = gameGetNominees();
			if (noms[noms.length-1] != game.time.nominee)
			{
				arr[index] = noms[noms.length-1];
				gameDirty(4);
			}
		}
		else
		{
			arr[index] = game.time.nominee;
			gameDirty(4);
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
					if (!isArray(v))
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
			if (!isSet(game.splitting))
			{
				game.splitting = [true];
			}
			for (let i = game.splitting.length; i <= game.time.round; ++i)
			{
				game.splitting.push(false);
			}
			
			for (let i = 0; i < 10; ++i)
			{
				_gameCreateVoting(i);
			}
		}
	}
}

// num is 0-9
function _gameDeleteVoting(num)
{
	if (game.time.time == 'voting' && isSet(game.time.nominee))
	{
		let noms = gameGetNominees();
		if (isSet(num))
		{
			let player = game.players[num];
			if (isSet(player.voting) && game.time.round < player.voting.length)
			{
				let v = player.voting[game.time.round];
				if (isArray(v) && game.time.votingRound < v.length)
				{
					v[game.time.votingRound] = null;
					switch (_gameCutArray(v))
					{
					case 0:
						player.voting[game.time.round] = null;
						if (_gameCutArray(player.voting) == 0)
						{
							delete player.voting;
						}
						break;
					case 1:
						player.voting[game.time.round] = v[0];
						break;
					}
				}
				else if (isNumber(v))
				{
					player.voting[game.time.round] = null;
					if (_gameCutArray(player.voting) == 0)
					{
						delete player.voting;
					}
				}
			}
		}
		else
		{
			if (game.time.votingRound == 0 && isSet(game.splitting))
			{
				while (game.time.round < game.splitting.length)
				{
					game.splitting.pop();
				}
				if (game.splitting.length == 0)
				{
					delete game.splitting;
				}
			}
			
			for (let i = 0; i < 10; ++i)
			{
				_gameDeleteVoting(i);
			}
		}
	}
}

function gameSetSplitting(s)
{
	if (!isSet(game.splitting))
	{
		game.splitting = [true];
	}
	for (let i = game.splitting.length; i <= game.time.round; ++i)
	{
		game.splitting.push(false);
	}
	game.splitting[game.time.round] = s;
	gameDirty(4);
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
			let first = _gameWhoSpeaksFirst(game.time.round);
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
						else if (noms.length == 1)
						{
							game.time = { round: round, time: 'day kill speaking', speaker: noms[0] };
						}
						else
						{
							game.time = { round: round, time: 'voting', votingRound: 0, nominee: noms[0] };
							_gameCreateVoting();
						}
					}
					break;
				}
			}
			while (isSet(game.players[game.time.speaker - 1].death));
			break;
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
						game.time = { time: 'voting kill all', round: game.time.round, votingRound: game.time.votingRound };
					}
					else
					{
						delete game.time.nominee;
						game.time.speaker = winners[0];
					}
				}
			}
			else // isSet(game.time.speaker) should always be true
			{
				let winners = gameGetNominees(game.time.votingRound + 1);
				let i = winners.length;
				for (i = 1; i < winners.length; ++i)
				{
					if (winners[i-1] == game.time.speaker)
					{
						game.time.speaker = winners[i];
						break;
					}
				}
				if (i >= winners.length)
				{
					delete game.time.speaker;
					game.time.nominee = noms[0];
					++game.time.votingRound;
					_gameCreateVoting();
				}
			}
			break;
		case 'voting kill all':
			break;
		case 'day kill speaking':
			break;
		case 'night start':
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
			
			for (let i = 0; i < 10; ++i)
			{
				let player = game.players[i];
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
				_gameRemoveOnRecord();
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
							for (let i = 0; i < 10; ++i)
							{
								let p = game.players[i];
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
				if (isSet(game.time.nominee))
				{
					let noms = gameGetNominees();
					let i = noms.length;
					if (i > 0 && noms[0] != game.time.nominee)
					{
						for (i = 1; i < noms.length; ++i)
						{
							if (noms[i] == game.time.nominee)
							{
								game.time.nominee = noms[i - 1];
								break;
							}
						}
					}
					if (i == noms.length)
					{
						_gameDeleteVoting();
						if (--game.time.votingRound >= 0)
						{
							delete game.time.nominee;
							game.time.speaker = noms[noms.length - 1];
						}
						else
						{
							let s = _gameWhoSpeaksFirst();
							if (s == 0) s = 10;
							game.time = { round: game.time.round, time: 'speaking', speaker: s };
						}
					}
				}
				else  // isSet(game.time.speaker) should always be true
				{
					let winners = gameGetNominees(game.time.votingRound + 1);
					let i = winners.length;
					if (i > 0 && winners[0] != game.time.speaker)
					{
						for (i = 1; i < winners.length; ++i)
						{
							if (winners[i] == game.time.speaker)
							{
								game.time.speaker = winners[i - 1];
								break;
							}
						}
					}
					if (i == winners.length)
					{
						let noms = gameGetNominees();
						delete game.time.speaker;
						game.time.nominee = noms[noms.length - 1];
					}
				}
				break;
			case 'voting kill all':
				break;
			case 'day kill speaking':
				break;
			case 'night start':
				break;
			case 'shooting':
				break;
			case 'don':
				break;
			case 'sheriff':
				break;
			case 'end':
				let maxDeathTime = null;
				let num = -1;
				for (let i = 0; i < 10; ++i)
				{
					let t = _gameGetPlayerDeathTime(i, true);
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
			for (let i = 0; i < 10; ++i)
			{
				let p = game.players[i];
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
