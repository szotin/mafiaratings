//  Shows the game that is being played right now on the pages used as browser sources in OBS
//  Studio. The page skeleton is created by obs.php; this script asks the site for the game state
//  every second and draws what it got.
//
//  The drawing is the designer's (Mafia_Broadcast_Handoff) - the same visual system the
//  between-games frames are in. Everything here is written as HTML and put in place only where it
//  has changed, so that a card nothing happened to is not touched and the animations on it are
//  not restarted twenty times a minute.

var obs = new function()
{
	var SEATS = 10; // a table has ten seats whether anybody is sitting at them or not

	var options;   // the options passed by obs.php
	var game;      // the last game state we got from the site
	var offline;   // true when the site did not answer the last request
	var hideRoles; // true when the game being played keeps its roles off the overlays
	var dealt;     // how far the roles have been handed out - see dealtTo()
	var shown;     // the voters already on the screen, so that only a new one flies in
	var stacks;    // the nominees whose vote card is already up, so that only a new one rises

	this.start = function(pageOptions)
	{
		options = pageOptions;
		game = null;
		offline = false;
		shown = {};
		settings();
		setInterval(settings, options.settingsInterval);
		refresh();

		// The nicknames are placed by measuring them, and a measurement taken before the font has
		// arrived is a measurement of a different font. The first drawing happens either way, so
		// that the overlay is up at once, and the names are placed again as soon as Onest is in.
		if (document.fonts && document.fonts.ready)
		{
			document.fonts.ready.then(function()
			{
				var players = document.getElementById('players');
				if (players)
				{
					fitNames(players);
				}
			});
		}
	}

	// Whether this table is to show anything at all, and in what language. Both are set on the
	// site rather than in the address of the source, so they are asked for again every few
	// seconds: a stream can clear the table and bring it back, or say the words are to be
	// Russian now, without anybody going to the machine OBS is running on.
	//
	// Nothing else stops while the page is bare: the game is still followed underneath, so
	// switching it back on puts up what is happening then rather than what was happening when it
	// went away.
	//
	// The language is the one thing that cannot be changed where it stands. The words of this
	// page were written into it by the server, so the page is loaded again - which takes a
	// moment and happens only when somebody has actually changed the language.
	function settings()
	{
		fetch(options.settingsUrl + '&' + new URLSearchParams(options.params)).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(answer)
			{
				if (!answer || !answer.stream)
				{
					return;
				}
				document.body.classList.toggle('hidden-source', answer.stream.hideGlyphs ? true : false);
				if (answer.stream.locale && options.locale && answer.stream.locale != options.locale)
				{
					options.locale = answer.stream.locale; // so that one change asks for one reload
					window.location.reload();
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

	//--------------------------------------------------------------------------------------
	// the pictures
	//--------------------------------------------------------------------------------------
	var IC =
	{
		sun: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/></svg>',
		moon: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M20.5 14.6A8.6 8.6 0 0 1 9.4 3.5a8.6 8.6 0 1 0 11.1 11.1z"/></svg>',
		star: '<svg viewBox="-1 -1 26 26"><g fill="currentColor"><path d="M12.00 2.00 L15.05 6.72 L20.66 7.00 L18.10 12.00 L20.66 17.00 L15.05 17.28 L12.00 22.00 L8.95 17.28 L3.34 17.00 L5.90 12.00 L3.34 7.00 L8.95 6.72Z"/><circle cx="12.00" cy="1.60" r="1.7"/><circle cx="21.01" cy="6.80" r="1.7"/><circle cx="21.01" cy="17.20" r="1.7"/><circle cx="12.00" cy="22.40" r="1.7"/><circle cx="2.99" cy="17.20" r="1.7"/><circle cx="2.99" cy="6.80" r="1.7"/></g></svg>',
		pistol: '<svg viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M2 6.2h19.2v4.3h-7.4c0 2.1-1.2 3.3-3.2 3.3h-.9L8.1 19.8H3.5l1.8-9.3H2zM10.1 10.5h2.2c0 1-.6 1.6-1.6 1.6h-.6z"/></svg>',
		hat: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M1.8 16.2c0-1.4 4.2-2.6 10.2-2.6s10.2 1.2 10.2 2.6-4.2 2.6-10.2 2.6S1.8 17.6 1.8 16.2z"/><path fill="currentColor" d="M6.6 14l.8-5.7c.3-1.6 1.8-2.6 3-2 .6.3 1 .4 1.6.4s1-.1 1.6-.4c1.2-.6 2.7.4 3 2l.8 5.7c-1.6-.4-3.4-.6-5.4-.6s-3.8.2-5.4.6z"/><path d="M7 12.2c1.6-.4 3.2-.6 5-.6s3.4.2 5 .6" fill="none" stroke="rgba(0,0,0,.4)" stroke-width="1.3"/></svg>',
		target: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="6.5"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><path d="M12 1.8v4.4M12 17.8v4.4M1.8 12h4.4M17.8 12h4.4"/></svg>',
		thumb: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 10h3.4a.6.6 0 0 1 .6.6v9.8a.6.6 0 0 1-.6.6H3a.6.6 0 0 1-.6-.6v-9.8A.6.6 0 0 1 3 10zm6 11V10.6l3.9-6.4a1.6 1.6 0 0 1 2.9 1.2l-.9 3.6h4.6a2.2 2.2 0 0 1 2.1 2.8l-2 7.3a2.6 2.6 0 0 1-2.5 1.9z"/></svg>',
		excl: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M10 3h4l-.6 12h-2.8zM12 17.2a2.1 2.1 0 1 1 0 4.2 2.1 2.1 0 0 1 0-4.2z"/></svg>',
		cross: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.8" stroke-linecap="round"><path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/></svg>',
		gavel: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M13.2 2.6l6.2 6.2-2.1 2.1-6.2-6.2zM8.3 7.5l6.2 6.2-2.1 2.1-6.2-6.2zM11.3 5.9l4.8 4.8-1.8 1.8-4.8-4.8zM10.4 13l1.4 1.4-7.3 7.3L3 20.3zM13 20h9v2h-9z"/></svg>'
	};

	// Nobody in particular, for a seat whose player never uploaded a photograph. It is framed the
	// way a real photograph is - filling the card and cut off at the sides rather than fitted
	// inside it, with the head high up where a face falls in a portrait. Fitted, the square
	// drawing could only be as big as the card is wide, which left a small figure crouching along
	// the bottom of a tall card.
	var SILHOUETTE = '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMin slice"><circle cx="50" cy="33" r="19" fill="rgba(0,0,0,.32)"/><path d="M10 100c2-26 19-40 40-40s38 14 40 40z" fill="rgba(0,0,0,.32)"/></svg>';

	//--------------------------------------------------------------------------------------
	// what a player is
	//--------------------------------------------------------------------------------------
	// The role as the viewers are allowed to know it. A game that hides its roles shows none at
	// all, and so does a seat the deal has not reached yet.
	function roleOf(player)
	{
		if (hideRoles || !player)
		{
			return '';
		}
		if (player.role)
		{
			return player.role;
		}
		// A seat the deal has already gone past with nothing written down against it is a
		// civilian - see dealtTo().
		return (player.number <= dealt) ? 'town' : '';
	}

	// How far the roles have been handed out, as the last seat that counts as dealt. They are
	// dealt round the table in the order of the seats, and the game writes down only what a
	// player was given: a civilian is nothing at all until the whole deal is over and the site
	// says so for everybody. So a seat with a role means the deal has been past every seat
	// before it, and the blanks among those are the civilians rather than seats still to come.
	//
	// And once the cards that are not a civilian's are all out - the sheriff, the don and the
	// two mafiosi every table is dealt, see gameGenerateRoles() in js/src/game.js - there is
	// nothing left to hand out at all, so every seat still blank is a civilian however far
	// round the table the deal has got.
	function dealtTo(g)
	{
		var last = 0;
		var sheriff = 0;
		var don = 0;
		var maf = 0;
		for (var i = 0; i < g.players.length; ++i)
		{
			var player = g.players[i];
			if (!player.role)
			{
				continue;
			}
			if (player.number > last)
			{
				last = player.number;
			}
			if (player.role == 'sheriff')
			{
				++sheriff;
			}
			else if (player.role == 'don')
			{
				++don;
			}
			else if (player.role == 'maf')
			{
				++maf;
			}
		}
		return (sheriff >= 1 && don >= 1 && maf >= 2) ? SEATS : last;
	}

	function isMafia(role)
	{
		return role == 'maf' || role == 'don';
	}

	// Which of the four chips a seat wears. The sheriff and the don are told apart by this and by
	// the icon on their card, and by nothing else: their cards are the cards of their own side.
	function chipClass(player)
	{
		var role = roleOf(player);
		if (role == '')
		{
			return 'n';
		}
		return isMafia(role) ? 'b' : (role == 'sheriff' ? 'g' : 'r');
	}

	function chip(player, number, extra)
	{
		return '<div class="chip ' + (extra ? extra + ' ' : '') + chipClass(player) + '">' + number + '</div>';
	}

	function photo(player)
	{
		return (player && player.hasPhoto && player.photoUrl) ?
			'<img src="' + escapeHtml(player.photoUrl) + '" alt="">' : SILHOUETTE;
	}

	function byNumber(players, number)
	{
		for (var i = 0; i < players.length; ++i)
		{
			if (players[i].number == number)
			{
				return players[i];
			}
		}
		return null;
	}

	//--------------------------------------------------------------------------------------
	// drawing
	//--------------------------------------------------------------------------------------
	function show()
	{
		var g = game ? game : { players: [], round: 0, phase: 'night', state: 'notStarted' };
		if (!g.players)
		{
			g.players = [];
		}

		// The game itself says whether its roles are shown, and the moderator can turn that over
		// in the middle of it, so it is read afresh every time rather than settled at start-up.
		hideRoles = (g.hideRoles === true);
		dealt = dealtTo(g);

		var stage = document.getElementById('stage');
		stage.className = 'ov ' + (g.phase == 'night' ? 'night' : 'day') + (offline ? ' offline' : '');

		setHtml(document.getElementById('tl'), topLeft(g));
		setHtml(document.getElementById('nom'), nominated(g));
		setHtml(document.getElementById('tr'), topRight(g));
		setHtml(document.getElementById('over'), banner(g));
		showPlayers(g);
	}

	//--------------------------------------------------------------------------------------
	// top left: the phase, the checks and the nominees
	//--------------------------------------------------------------------------------------
	function topLeft(g)
	{
		var night = (g.phase == 'night');
		// The round as a table counts it. Night 0 is the one the game opens with, where the mafia
		// meet and nobody is shot, and it is called that at the table too - so a night is the round
		// itself. A day is the round after it, the game counting its rounds from zero and the first
		// circle of speeches being day 1, so a day is the round and one. That makes the order on
		// the screen night 0, day 1, night 1, day 2 - and it is the same count the players wear
		// when they leave the game, see deathRound in api/get/current_game.php.
		var name = (night ? options.labels.night : options.labels.day).
			replace('[0]', night ? g.round : g.round + 1);
		var state = stateLabel(g);

		var html = '<div class="phase gl ' + (night ? 'night' : 'day') + '">' +
			'<span class="ic">' + (night ? IC.moon : IC.sun) + '</span>' +
			'<span class="pn">' + escapeHtml(name) + '</span>';
		if (state != '')
		{
			html += '<span class="dv"></span><span class="st">' + escapeHtml(state) + '</span>';
		}
		html += '</div>';

		// A pill of checks appears only once there is a check in it. The don's checks come first,
		// because the don checks first.
		var don = checkedBy(g.players, 'checkedByDon');
		var sheriff = checkedBy(g.players, 'checkedBySheriff');
		var checks = '';
		if (don.length > 0)
		{
			checks += '<div class="ck d gl"><span class="ic">' + IC.hat + '</span>' + checkChips(don, true) + '</div>';
		}
		if (sheriff.length > 0)
		{
			checks += '<div class="ck s gl"><span class="ic">' + IC.star + '</span>' + checkChips(sheriff, false) + '</div>';
		}
		html += (checks == '' ? '' : '<div class="checks">' + checks + '</div>');

		// The site is not answering. The cards stay as they were - what is on the screen is the
		// last thing we knew - but whoever is running the stream has to be told that it is old.
		if (offline)
		{
			html += '<div class="off gl">' + escapeHtml(options.labels.offline) + '</div>';
		}
		return html;
	}

	// What the game is doing right now, in a word. Speeches are not said: the card of whoever is
	// speaking says it better, and the label would be up for most of the game.
	function stateLabel(g)
	{
		if (g.state == 'starting')
		{
			// The game begins with the roles being handed out; every other "starting" is a day or
			// a night that has just begun, and there is nothing to say about those.
			return (g.phase == 'night' && g.round == 0) ? options.labels.states.starting : '';
		}
		return options.labels.states[g.state] ? options.labels.states[g.state] : '';
	}

	// The players checked by the sheriff or by the don, in the order they were checked.
	function checkedBy(players, field)
	{
		var result = [];
		for (var i = 0; i < players.length; ++i)
		{
			if (players[i][field])
			{
				result.push(players[i]);
			}
		}
		result.sort(function(a, b) { return a[field] - b[field]; });
		return result;
	}

	// A sheriff's check wears the colour of what was found; the don is looking for the sheriff, so
	// his checks are amber where he found one and red everywhere else.
	function checkChips(players, isDon)
	{
		var html = '';
		for (var i = 0; i < players.length; ++i)
		{
			var player = players[i];
			html += isDon ?
				('<div class="chip ' + (roleOf(player) == 'sheriff' ? 'g' : 'r') + '">' + player.number + '</div>') :
				chip(player, player.number);
		}
		return html;
	}

	// The nominees, under the phase pill. It stands while there are nominees and all through the
	// voting, and goes as soon as the voted-out card has dropped.
	function nominated(g)
	{
		if (g.phase != 'day' || isOver(g))
		{
			return '';
		}
		if (g.votingCanceled)
		{
			return '<div class="nom quiet gl"><div class="msg">' + IC.thumb +
				escapeHtml(options.labels.noVoting) + '</div></div>';
		}
		if (!g.nominees || g.nominees.length == 0)
		{
			return '';
		}

		var out = votedOut(g);
		var chips = '';
		for (var i = 0; i < g.nominees.length; ++i)
		{
			var number = g.nominees[i];
			// Once the vote is settled, the nominees who stayed at the table step back.
			var fade = (out && out.number != number) ? ' fade' : '';
			chips += chip(byNumber(g.players, number), number, fade);
		}
		return '<div class="nom gl"><div class="lb">' + IC.thumb + '<span>' +
			escapeHtml(options.labels.nominated) + '</span></div><div class="cols">' + chips + '</div></div>';
	}

	//--------------------------------------------------------------------------------------
	// top right: the tournament and the judge
	//--------------------------------------------------------------------------------------
	function topRight(g)
	{
		var html = '';
		var parts = [];
		var name = g.tournament ? g.tournament.name : (g.club ? g.club.name : '');
		var logo = g.tournament ? g.tournament.iconUrl : (g.club ? g.club.iconUrl : null);

		if (name)
		{
			parts.push('<span class="nm">' + escapeHtml(name) + '</span>');
		}
		if (g.table)
		{
			parts.push('<span>' + escapeHtml(options.labels.table.replace('[0]', g.table)) + '</span>');
		}
		if (g.tour)
		{
			parts.push('<span>' + escapeHtml(options.labels.game.replace('[0]', g.tour)) + '</span>');
		}
		if (parts.length > 0)
		{
			html += '<div class="pill game gl">' +
				(logo ? '<span class="tlogo"><img src="' + escapeHtml(logo) + '" alt=""></span>' : '') +
				parts.join('<span class="dv"></span>') + '</div>';
		}

		// The judge, with the gavel after the name: the photograph says who, the gavel says what
		// they are doing there.
		var judge = g.moderator;
		if (judge && judge.name)
		{
			html += '<div class="pill judge gl"><span class="av">' + photo(judge) + '</span>' +
				'<span class="nm">' + escapeHtml(judge.name) + '</span>' + IC.gavel + '</div>';
		}
		return html;
	}

	//--------------------------------------------------------------------------------------
	// the game is over
	//--------------------------------------------------------------------------------------
	function isOver(g)
	{
		return g.state == 'mafiaWon' || g.state == 'townWon' || g.state == 'tie' || g.state == 'end';
	}

	function banner(g)
	{
		if (!isOver(g))
		{
			return '';
		}

		var title = options.labels.gameOver;
		var side = 'maf';
		if (g.state == 'townWon')
		{
			title = options.labels.townWins;
			side = 'town';
		}
		else if (g.state == 'mafiaWon')
		{
			title = options.labels.mafiaWins;
		}
		else if (g.state == 'tie')
		{
			title = options.labels.tie;
		}

		var sub = [];
		if (g.table)
		{
			sub.push(options.labels.table.replace('[0]', g.table));
		}
		if (g.tour)
		{
			sub.push(options.labels.game.replace('[0]', g.tour));
		}
		return '<div class="over gl ' + side + '"><h1>' + escapeHtml(title) + '</h1>' +
			(sub.length > 0 ? '<div class="sub">' + escapeHtml(sub.join(' · ')) + '</div>' : '') + '</div>';
	}

	//--------------------------------------------------------------------------------------
	// the players
	//--------------------------------------------------------------------------------------
	function showPlayers(g)
	{
		var div = document.getElementById('players');
		if (!div)
		{
			return;
		}

		// The table is laid out even when there is no game at all - an empty row of cards is what
		// the browser source is sized and placed against in OBS Studio, long before anybody sits
		// down. A seat with nobody on it is the same thing as a player we do not know.
		var players = g.players;
		var count = Math.max(players.length, SEATS);
		while (div.childElementCount > count)
		{
			div.removeChild(div.lastElementChild);
		}
		while (div.childElementCount < count)
		{
			div.appendChild(document.createElement('div'));
		}

		var context = playersContext(g);
		for (var i = 0; i < count; ++i)
		{
			var player = players[i] ? players[i] : { number: i + 1 };
			var cell = div.children[i];
			var html = cellHtml(g, player, context);
			if (cell.obsHtml !== html)
			{
				cell.obsHtml = html;
				cell.outerHTML = html;
				// Writing outerHTML replaces the element, so the one in the list is a new one.
				div.children[i].obsHtml = html;
			}
		}

		fitNames(div);
		flyVotes(div);
		riseStacks(div);
	}

	// The few things a card cannot work out on its own: who was shot first, what the voting looks
	// like, and who has already left the game.
	function playersContext(g)
	{
		var context = {};
		context.voting = (g.state == 'voting' || g.state == 'nomineeSpeaking');
		context.over = isOver(g);
		context.winners = g.state == 'townWon' ? 'town' : (g.state == 'mafiaWon' ? 'maf' : '');
		context.out = votedOut(g);
		// The hands as the game has them now, and nothing the overlay is holding of its own: the
		// count the moderator is taking is the only one that stands, and when it is taken away -
		// a table that splits puts every hand down, and a moderator who steps the game back
		// unmakes the whole vote - it has to go from the screen with it.
		context.votes = {};
		context.max = 0;
		var hands = g.votes ? g.votes : [];
		for (var i = 0; i < hands.length; ++i)
		{
			context.votes[hands[i].nominee] = hands[i].voters;
			context.max = Math.max(context.max, hands[i].voters.length);
		}
		// A tie is told by the game sending the tied nominees back to speak.
		context.tie = (g.state == 'nomineeSpeaking');

		// The best guess belongs above the card of the player shot on the first night.
		context.legacy = (g.legacy && g.legacy.length > 0) ? g.legacy : null;
		context.legacyOf = 0;
		if (context.legacy)
		{
			for (var i = 0; i < g.players.length; ++i)
			{
				var p = g.players[i];
				if (p.state == 'dead' && p.deathType == 'shooting' && p.deathRound == 1)
				{
					context.legacyOf = p.number;
					break;
				}
			}
		}
		return context;
	}

	// Whoever was voted out this round, once the game has written it down. A day is the round
	// after the one it is written down in - see the phase above - so the day this is matches
	// deathRound at the round and one.
	function votedOut(g)
	{
		for (var i = 0; i < g.players.length; ++i)
		{
			var p = g.players[i];
			if (p.state == 'dead' && p.deathType == 'voting' && p.deathRound == g.round + 1 && g.phase == 'day')
			{
				return p;
			}
		}
		return null;
	}

	function cellHtml(g, player, context)
	{
		var role = roleOf(player);
		var known = (player.id > 0);
		var dead = (player.state == 'dead');
		var classes = ['pc'];

		// A game that hides its roles shows every card as a player's card and tells nobody apart;
		// a seat whose role has not been handed out yet is the card of no side at all.
		if (hideRoles)
		{
			classes.push('town');
		}
		else if (role == '')
		{
			classes.push(known ? 'none' : 'none');
		}
		else
		{
			classes.push(isMafia(role) ? 'maf' : 'town');
			if (role == 'sheriff')
			{
				classes.push('sher');
			}
			if (role == 'don')
			{
				classes.push('don');
			}
		}
		if (dead)
		{
			classes.push('dead');
		}
		if (player.isSpeaking)
		{
			classes.push('speak');
		}
		if (context.over && context.winners != '')
		{
			classes.push((isMafia(role) ? 'maf' : 'town') == context.winners ? 'win' : 'lose');
		}

		var card = '<div class="' + classes.join(' ') + '">' +
			'<div class="clip"><div class="ph">' + photo(player) + '</div></div>' +
			roleIcon(role) +
			marks(player) +
			'<div class="meta"><span class="seat">' + player.number + '</span>' +
			'<span class="nick">' + escapeHtml(player.name ? player.name : '') + '</span></div>' +
			'</div>';

		// What stands above the card: the best guess, the votes being cast, or the pill saying who
		// voted this player out. Never two of them at once - they are all the same space.
		//
		// The pill is the game's own word rather than the hands the overlay is holding: those are
		// the count being taken and they come down with the day, while the numbers that put a
		// player out stay over their card to the end of the game - see votedOutBy in
		// api/get/current_game.php.
		var above = '';
		var cellClass = 'cell';
		if (player.votedOutBy && player.votedOutBy.length > 0)
		{
			var voters = player.votedOutBy;
			above = votedOutPill(g, voters);
			cellClass += ' vbo' + (voters.length > 5 ? ' r2' : ' r1');
		}
		else if (context.voting && context.votes[player.number])
		{
			above = voteCard(g, player, context);
		}
		else if (context.legacy && context.legacyOf == player.number)
		{
			above = bestGuess(g, context);
		}
		return '<div class="' + cellClass + '">' + above + card + '</div>';
	}

	function roleIcon(role)
	{
		if (role == '')
		{
			return '';
		}
		if (role == 'sheriff')
		{
			return '<div class="role s">' + IC.star + '</div>';
		}
		if (role == 'don')
		{
			return '<div class="role">' + IC.hat + '</div>';
		}
		if (role == 'maf')
		{
			return '<div class="role">' + IC.pistol + '</div>';
		}
		return '';
	}

	// The top right corner of the card: the fouls of a player who is still in the game, or the way
	// one who is not left it.
	function marks(player)
	{
		if (options.hideWarnings)
		{
			return '';
		}

		var dead = (player.state == 'dead');
		if (dead)
		{
			var type = player.deathType;
			if (type == 'shooting' || type == 'voting')
			{
				return '<div class="marks"><div class="elim when ' + type + '">' +
					(type == 'shooting' ? IC.target : IC.thumb) +
					'<span>' + player.deathRound + '</span></div></div>';
			}
			// Four fouls, two critical fouls, kicked out, gave up, or taken down with their side:
			// all of them are the judge taking a player out of the game.
			return '<div class="marks"><div class="elim out">' + IC.cross + '</div></div>';
		}

		var row = '';
		var critical = player.techFouls ? player.techFouls : 0;
		if (critical > 0)
		{
			// A critical foul is counted apart from the others and costs the player the game at
			// two, so it is a mark of its own rather than a fourth dot.
			row += '<div class="fouls tfg">' + IC.excl + '</div>';
		}

		var warnings = player.warnings ? player.warnings : 0;
		if (warnings > 0)
		{
			var dots = '';
			for (var i = 0; i < 3; ++i)
			{
				dots += '<i class="' + (i < warnings ? 'on' : '') + '"></i>';
			}
			row += '<div class="fouls' + (warnings >= 3 ? ' hot' : '') + '">' + dots + '</div>';
		}
		return row == '' ? '' : '<div class="marks"><div class="row">' + row + '</div></div>';
	}

	// The three seats named by the player shot on the first night. It stands at full strength
	// for as long as it is up, the voting included: it is the one thing on the screen that says
	// what the dead player left behind, and a table reads it most of all while the vote it
	// pointed at is being taken.
	function bestGuess(g, context)
	{
		var chips = '';
		for (var i = 0; i < context.legacy.length; ++i)
		{
			var number = context.legacy[i];
			chips += chip(byNumber(g.players, number), number);
		}
		return '<div class="bmw"><div class="bm">' + chips + '</div></div>';
	}

	// The vote card above a nominee: how many votes, and who gave them.
	function voteCard(g, player, context)
	{
		var voters = context.votes[player.number];
		var lead = (voters.length == context.max && context.max > 0);
		var classes = 'stk';
		if (context.out)
		{
			classes += (context.out.number == player.number) ? ' out' : ' fade';
		}
		else if (context.tie && lead)
		{
			classes += ' tie';
		}

		var chips = '';
		for (var i = 0; i < voters.length; ++i)
		{
			chips += '<div class="chip sm ' + chipClass(byNumber(g.players, voters[i])) +
				'" data-from="' + voters[i] + '">' + voters[i] + '</div>';
		}

		return '<div class="' + classes + '" data-seat="' + player.number + '">' +
			'<div class="stk-n"><b>' + voters.length + '</b><small>' + escapeHtml(votesWord(voters.length)) + '</small>' +
			((context.tie && lead) ? '<span class="stk-tag">' + escapeHtml(options.labels.tieTag) + '</span>' : '') +
			'</div>' +
			'<div class="stk-c" style="--per:' + perRow(voters.length) + '">' + chips + '</div></div>';
	}

	// The pill above a dropped card: who voted that player out. It is exactly as wide as the card,
	// and with six voters or more the chips take two rows and the pill grows upward - the card
	// below it keeps the height every other dead card has, so that the row of the dead stays even.
	function votedOutPill(g, voters)
	{
		var chips = '';
		for (var i = 0; i < voters.length; ++i)
		{
			chips += '<div class="chip xs ' + chipClass(byNumber(g.players, voters[i])) + '">' + voters[i] + '</div>';
		}
		return '<div class="vby"><span class="vbi">' + IC.thumb + '</span>' +
			'<div class="vbc" style="--per:' + perRow(voters.length) + '">' + chips + '</div></div>';
	}

	// How many chips go in a row, so that two rows come out even: seven votes are four and three
	// rather than five and two.
	function perRow(count)
	{
		return count > 5 ? Math.ceil(count / 2) : count;
	}

	// The word under the number of votes. Three forms, because Russian needs three and the
	// language the page is drawn in decides which.
	function votesWord(count)
	{
		var forms = options.labels.votes;
		if (forms.length < 3)
		{
			return forms[forms.length - 1];
		}
		var n = count % 100;
		if (n > 10 && n < 20)
		{
			return forms[2];
		}
		n = count % 10;
		if (n == 1)
		{
			return forms[0];
		}
		return (n >= 2 && n <= 4) ? forms[1] : forms[2];
	}

	//--------------------------------------------------------------------------------------
	// the two things that have to be measured
	//--------------------------------------------------------------------------------------
	// The nickname sits in the middle of the card and never over the seat chip, so where it
	// starts depends on how wide it is. A name too long for the room is set smaller, down to the
	// size the designer stopped at, and one too long even for that is cut off.
	function fitNames(div)
	{
		var scale = parseFloat(getComputedStyle(document.documentElement).fontSize) / 16;
		var nicks = div.getElementsByClassName('nick');
		for (var i = 0; i < nicks.length; ++i)
		{
			var nick = nicks[i];
			var card = nick.parentNode.parentNode;
			var width = card.clientWidth;
			if (width <= 0)
			{
				continue;
			}

			var reserved = 36 * scale; // the seat chip and the gap after it
			var size = 21;
			var text = nick.textContent;
			var textWidth = measure(text, size * scale);
			while (textWidth > width - reserved - 8 * scale && size > 15)
			{
				--size;
				textWidth = measure(text, size * scale);
			}
			nick.style.fontSize = (size * scale) + 'px';
			nick.style.setProperty('--nl', Math.max(reserved, (width - textWidth) / 2) + 'px');
		}
	}

	var ruler;

	function measure(text, px)
	{
		if (!ruler)
		{
			ruler = document.createElement('canvas').getContext('2d');
		}
		ruler.font = '800 ' + px + 'px Onest, system-ui, sans-serif';
		return ruler.measureText(text).width;
	}

	// A vote arrives as a copy of the voter's own seat number and flies from their card to the
	// nominee's. Only a vote that was not on the screen a moment ago flies: the rest are already
	// where they belong.
	function flyVotes(div)
	{
		var chips = div.querySelectorAll('.stk-c .chip[data-from]');
		var seen = {};
		for (var i = 0; i < chips.length; ++i)
		{
			var chip = chips[i];
			var from = chip.getAttribute('data-from');
			seen[from] = true;
			if (shown[from])
			{
				continue;
			}

			var seat = seatOf(div, from);
			if (seat)
			{
				var to = chip.getBoundingClientRect();
				var at = seat.getBoundingClientRect();
				chip.style.setProperty('--dx', (at.left - to.left) + 'px');
				chip.style.setProperty('--dy', (at.top - to.top) + 'px');
				chip.classList.add('fly');
			}
		}
		shown = seen;
	}

	// The vote card itself. The chips fly in one by one, but the glass they land on simply
	// appeared, which on a stream is a panel snapping into being. It rises into place instead -
	// and only the first time, the tick the nominee's first vote arrives: the cell is written
	// again on every count, so an animation left on the card would play on every hand raised.
	function riseStacks(div)
	{
		var cards = div.querySelectorAll('.stk[data-seat]');
		var seen = {};
		for (var i = 0; i < cards.length; ++i)
		{
			var seat = cards[i].getAttribute('data-seat');
			seen[seat] = true;
			if (!stacks || !stacks[seat])
			{
				cards[i].classList.add('rise');
			}
		}
		stacks = seen;
	}

	function seatOf(div, number)
	{
		var seats = div.getElementsByClassName('seat');
		for (var i = 0; i < seats.length; ++i)
		{
			if (seats[i].textContent == number)
			{
				return seats[i];
			}
		}
		return null;
	}

	//--------------------------------------------------------------------------------------
	// utilities
	//--------------------------------------------------------------------------------------
	// Replaces the content only when it really changes, otherwise the animations would restart
	// every time the page asks the site what is happening.
	function setHtml(element, html)
	{
		if (element && element.obsHtml !== html)
		{
			element.obsHtml = html;
			element.innerHTML = html;
		}
	}

	function escapeHtml(text)
	{
		return String(text).replace(/[&<>"]/g, function(c)
		{
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}
}
