var game; // All vars here can be used by UI code, but it is strongly recommended to use them for reading only. If changes are absolutely needed, make sure gameDirty(...) is called after that.
var regs; // array of players registered for the event
var langs; // array of languages allowed in the event
var _isDirty = false; // signals if the game needs to be saved
var _connectionState = 0; // 0 when connected, 1 when connecting, 2 when disconnected, 3 when error
var _connectionListener; // this function is called when connection status is changed. Parameter is 0 when connected, 1 when connecting, 2 when disconnected, 3 when error
var _errorListener; // parameter type is: 0 - getting game failed; 1 - saving game failed.
var _gameOnChange; // this function is called every time game changes. Parameter flags is a bit combination of: 
// 1 - players changed 
// 2 - roles changed
// 4 - game time changed
// 8 - rating/non-rating changed
// 16 - language changed
// 32 - moderator changed

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
	_isDirty = true;
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
	gameDirty(2);
}

function gameNext()
{
	if (!isSet(game.time))
	{
		game.time = { time: 'start', round: 0 };
	}
	switch (game.time.time)
	{
	case 'start':
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
	gameDirty(5);
}

function gameBack()
{
	if (isSet(game.time))
	{
		switch (game.time.time)
		{
		case 'start':
			delete game.time;
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
		gameDirty(4);
	}
}
