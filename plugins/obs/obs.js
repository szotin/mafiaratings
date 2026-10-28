//  Shows the game that is being played right now on the pages used as browser sources in OBS Studio.
//  The page skeleton is created by obs.php. This script asks the site for the game state every
//  couple of seconds and refreshes the parts of the skeleton that have changed.

var obs = new function()
{
	var NO_LOGO = 'images/aml_logo.png'; // the picture shown instead of a logo that we do not have
	var SEATS = 10; // a table has ten seats whether anybody is sitting at them or not

	var options;   // the options passed by obs.php
	var game;      // the last game state we got from the site
	var offline;   // true when the site did not answer the last request
	var hideRoles; // true when the game being played keeps its roles off the overlays

	this.start = function(pageOptions)
	{
		options = pageOptions;
		game = null;
		offline = false;
		settings();
		setInterval(settings, options.settingsInterval);
		refresh();
	}

	// Whether this table is to show anything at all. It is set on the site, not in the address
	// of the source, so it is asked for again every few seconds and a stream can clear the
	// table and bring it back without anybody going to the machine OBS is running on.
	//
	// Nothing else stops while the page is bare: the game is still followed and the cards are
	// still turned over underneath, so switching it back on puts up what is happening then
	// rather than what was happening when it went away.
	function settings()
	{
		fetch(options.settingsUrl + '&' + new URLSearchParams(options.params)).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(answer)
			{
				if (answer && answer.stream)
				{
					document.body.classList.toggle('hidden-source', answer.stream.hideGlyphs ? true : false);
				}
			}).
			catch(function(error) { /* the site is not answering: leave the page as it stands */ });
	}

	function refresh()
	{
		// The game state is taken from our own site, so the page works the same way everywhere it is deployed.
		fetch('../../api/get/current_game.php?' + new URLSearchParams(options.params)).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(data) { game = data.game; offline = false; }).
			catch(function(error) { offline = true; }).
			then(function() { show(); setTimeout(refresh, options.retryDelay); });
	}

	function show()
	{
		var g = game ? game : { players: [], round: 0, phase: 'night', state: 'notStarted' };

		// The game itself says whether its roles are shown, and the moderator can turn that over
		// in the middle of it, so it is read afresh every time rather than settled at start-up.
		hideRoles = (g.hideRoles === true);

		showGamestats(g);
		showReferee(g.moderator);
		showRound(g);
		showPlayers(g);
	}

	//--------------------------------------------------------------------------------------
	// the info bar
	//--------------------------------------------------------------------------------------
	function showGamestats(game)
	{
		var gamestats = document.getElementById('gamestats');
		if (!gamestats)
		{
			return;
		}

		var players = game.players ? game.players : [];
		gamestats.className = 'gamestats' + (hideRoles ? ' hide-roles' : '') + (offline ? ' offline' : '');

		showSection('legacy', playersByNumbers(players, game.legacy));
		showSection('sheriff-check', playersCheckedBy(players, 'checkedBySheriff'));
		showSection('don-check', playersCheckedBy(players, 'checkedByDon'));
		showSection('nominees', playersByNumbers(players, game.nominees));
	}

	function showSection(name, players)
	{
		var section = document.getElementById(name);
		var html = '';
		for (var i = 0; i < players.length; ++i)
		{
			html += '<div class="element role-' + (players[i].role ? players[i].role : 'none') + '">' + players[i].number + '</div>';
		}

		section.className = 'section ' + name + (players.length > 0 ? ' data' : ' empty');
		setHtml(section.getElementsByClassName('elements')[0], html);
	}

	function playersByNumbers(players, numbers)
	{
		var result = [];
		for (var i = 0; numbers && i < numbers.length; ++i)
		{
			var player = players[numbers[i] - 1];
			if (player)
			{
				result.push(player);
			}
		}
		return result;
	}

	// Returns the players checked by the sheriff or by the don in the order they were checked.
	function playersCheckedBy(players, checkedBy)
	{
		var result = [];
		for (var i = 0; i < players.length; ++i)
		{
			if (players[i][checkedBy])
			{
				result.push(players[i]);
			}
		}
		result.sort(function(player1, player2) { return player1[checkedBy] - player2[checkedBy]; });
		return result;
	}

	//--------------------------------------------------------------------------------------
	// the referee and the round
	//--------------------------------------------------------------------------------------
	function showReferee(moderator)
	{
		if (!document.getElementById('referee'))
		{
			return;
		}

		document.getElementById('referee-photo').src = moderator && moderator.photoUrl ? moderator.photoUrl : NO_LOGO;
		document.getElementById('referee-name').textContent = moderator && moderator.name ? moderator.name : options.labels.referee;
	}

	function showRound(game)
	{
		var round = document.getElementById('round');
		if (!round)
		{
			return;
		}

		// The tour is the number of the game in the tournament. There is nothing to show when it is unknown.
		round.style.display = game.tour ? '' : 'none';
		if (!game.tour)
		{
			return;
		}

		var logo = game.tournament ? game.tournament.iconUrl : (game.club ? game.club.iconUrl : null);
		document.getElementById('round-logo').src = logo ? logo : NO_LOGO;

		var stages = options.labels.stages;
		var stage = stages[game.stage > 0 && game.stage < stages.length ? game.stage : 0];
		document.getElementById('round-name').textContent = stage + ' ' + game.tour;
	}

	//--------------------------------------------------------------------------------------
	// the players
	//--------------------------------------------------------------------------------------
	function showPlayers(game)
	{
		var div = document.getElementById('players');
		if (!div)
		{
			return;
		}

		// The table is laid out even when there is no game at all - an empty row of cards is what
		// the browser source is sized and placed against in OBS Studio, long before anybody sits
		// down. A seat with nobody on it is the same thing as a player we do not know.
		var players = game.players ? game.players : [];
		var count = Math.max(players.length, SEATS);

		// The cards are created once and then reused, otherwise they would never finish flipping.
		while (div.childElementCount > count)
		{
			div.removeChild(div.lastElementChild);
		}
		while (div.childElementCount < count)
		{
			div.appendChild(createPlayerCard());
		}

		for (var i = 0; i < count; ++i)
		{
			showPlayer(div.children[i], players[i] ? players[i] : { number: i + 1 });
		}
	}

	// A card has two sides and turns another half circle every time something worth showing
	// happens to it - somebody sits down, a role is handed out, the seat empties again. The two
	// sides are the same markup; what is written on them is what differs. Whatever we write goes
	// on the side that is facing away right now, so it is already right when it comes around.
	var CARD_FACE =
		'<div class="player-bg"></div>' +
		'<div class="player-photo">' +
			'<div class="player-role"></div>' +
			'<div class="player-marks"><div class="player-fouls"></div><div class="player-mark"><span></span></div></div>' +
			'<div class="player-name-bar"><div class="player-number"></div><div class="player-name"></div></div>' +
		'</div>';

	function createPlayerCard()
	{
		var card = document.createElement('div');
		card.className = 'player-card';
		card.innerHTML =
			'<div class="player-card-inner">' +
				'<div class="card-face">' + CARD_FACE + '</div>' +
				'<div class="card-face turned-away">' + CARD_FACE + '</div>' +
			'</div>';
		card.obsTurns = 0;
		card.obsLook = null;
		return card;
	}

	// Gives the card another half circle. The two half circles alternate, so turning again is
	// always a change of class and the browser always plays the animation - no need to take it
	// off and put it back to make a repeat turn start over.
	function turnCard(card)
	{
		card.obsTurns += 1;
		card.getElementsByClassName('player-card-inner')[0].className =
			'player-card-inner ' + (card.obsTurns % 2 ? 'to-back' : 'to-front');
	}

	function showPlayer(card, player)
	{
		// A player who never uploaded a photo still has a face: the site gives them its default
		// icon. So a seat stays face down only while there is nobody on it.
		var known = (player.id > 0);
		var role = !hideRoles && player.role ? player.role : '';

		// Sitting down and being handed a role are the two things the card turns over for. Each
		// of them gives it another half circle, always in the same direction.
		var look = known ? role : 'empty';
		if (card.obsLook !== look)
		{
			// Nothing to turn over on the very first look - the card is simply dealt as it is.
			if (card.obsLook !== null)
			{
				turnCard(card);
			}
			card.obsLook = look;
		}

		// An even number of half circles leaves the first side facing us, an odd one the second.
		// Writing to that side only is what keeps a turn honest: the side rotating away keeps the
		// old look until it is gone, and the one coming around is already right.
		var face = card.getElementsByClassName('card-face')[card.obsTurns % 2];
		face.className = 'card-face' + (card.obsTurns % 2 ? ' turned-away' : '') +
			(role != '' ? ' role-' + role : '') + (known && !player.hasPhoto ? ' no-photo' : '');
		face.getElementsByClassName('player-name')[0].textContent = player.name ? player.name : '';
		face.getElementsByClassName('player-photo')[0].style.backgroundImage =
			known ? 'url(' + (player.hasPhoto ? player.photoUrl : player.iconUrl) + ')' : '';
		showPlayerMark(face.getElementsByClassName('player-mark')[0], player);
		showPlayerFouls(face.getElementsByClassName('player-fouls')[0], player);

		// The number belongs to the seat rather than to the player, so both sides carry it.
		var numbers = card.getElementsByClassName('player-number');
		for (var i = 0; i < numbers.length; ++i)
		{
			numbers[i].textContent = player.number;
		}

		// What happens to a player during the game is not worth a turn - it shows up where they
		// already are.
		var state = '';
		if (player.state == 'dead')
		{
			state += ' dead death-' + player.deathType + ' round-' + player.deathRound;
		}
		if (player.isSpeaking)
		{
			state += ' speaking';
		}
		if (player.warnings > 0)
		{
			state += ' warnings-' + Math.min(player.warnings, 4);
		}
		card.className = 'player-card' + state;
	}

	// The top right corner of the card shows either the warnings of a living player, or the way a
	// dead one died. One cross is a player the referee removed; two is one who took their team
	// down with them.
	function showPlayerMark(mark, player)
	{
		var dead = (player.state == 'dead');
		var text = '';
		if (player.warnings > 0 && (!dead || player.deathType == 'warnings'))
		{
			text = new Array(player.warnings + 1).join('!');
		}
		else if (dead && (player.deathType == 'kickOut' || player.deathType == 'techFouls'))
		{
			text = 'X';
		}
		else if (dead && player.deathType == 'oppositeTeamWins')
		{
			text = 'XX';
		}

		// Voting and shooting are marked by a picture set in obs.css, so the mark stays empty but visible.
		var picture = dead && (player.deathType == 'voting' || player.deathType == 'shooting');

		mark.style.display = !options.hideWarnings && (text != '' || picture) ? '' : 'none';
		mark.getElementsByTagName('span')[0].textContent = text;
	}

	// The technical fouls, beside the warnings rather than across the card from them: the other
	// corner is where the role is. They are counted apart from the warnings and cost a player
	// the game at two, so they are a mark of their own - and they stay up after the player is
	// gone, whichever way they went.
	function showPlayerFouls(fouls, player)
	{
		var count = player.techFouls ? player.techFouls : 0;
		fouls.style.display = !options.hideWarnings && count > 0 ? '' : 'none';
		// A cross apiece, the mark the game pages use for a technical foul.
		fouls.textContent = new Array(count + 1).join('✘');
	}

	//--------------------------------------------------------------------------------------
	// utilities
	//--------------------------------------------------------------------------------------
	// Replaces the content only when it really changes, otherwise the animations would restart every time.
	function setHtml(element, html)
	{
		if (element.obsHtml !== html)
		{
			element.obsHtml = html;
			element.innerHTML = html;
		}
	}
}
