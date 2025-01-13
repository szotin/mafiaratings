var game; // All vars here can be used by UI code, but it is strongly recommended to use them for reading only. If changes are absolutely needed, make sure gameDirty() is called after that.
var regs; // array of players registered for the event
var langs; // array of languages allowed in the event
var _isDirty = false; // signals if the game needs to be saved
var _connectionState = 0; // 0 when connected, 1 when connecting, 2 when disconnected, 3 when error
var _connectionListener; // this function is called when connection status is changed. Parameter is 0 when connected, 1 when connecting, 2 when disconnected, 3 when error

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

function gameInit(eventId, tableNum, roundNum, onSuccess, onError, connectionListener)
{
	_connectionListener = connectionListener;
	json.post('api/ops/game.php', { op: 'get_current', event_id: eventId, table: tableNum, round: roundNum }, function(data)
	{
		game = data.game;
		regs = data.regs;
		langs = data.langs;
		if (onSuccess)
		{
			onSuccess();
		}
		
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
		
	}, onError);
}

function gameSave()
{
	if (_isDirty)
	{
		var gameStr = JSON.stringify(game);
		if (_connectionState != 1) // 1 means that the other request is not finished yet
		{
			console.log(game);
			var w = http.waiter(statusWaiter);
			json.post('api/ops/game.php', { op: 'set_current', event_id: game.eventId, table: game.table - 1, round: game.round - 1, game: gameStr}, 
			function() // success
			{
				// The game is not needed in the local storage any more because the server has it.
				delete localStorage['game'];
				_isDirty = false;
			},
			function() // error
			{
				// Save it to the local storage if server is not accessible
				if (typeof localStorage == "object")
				{
					localStorage['game'] = gameStr;
				}
			});
			http.waiter(w);
		}
	}
}

// Call this after each change in the game. It sets the flag that makes the game to be saved
function gameDirty()
{
	_isDirty = true;
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
	gameDirty();
	return result;
}
