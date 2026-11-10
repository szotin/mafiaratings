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
	// How long the curtain takes to sweep across, which is how long the page has to wait before
	// taking away what it was covering. The same length is written into .curtain in obs.css, and
	// the two have to be changed together.
	//
	// It is slow on purpose - a curtain in a theatre is - and it can afford to be because it
	// starts closing as the night opens rather than when the first thing worth hiding happens:
	// see nightCovered(), where the whole of a night is covered and not only its steps.
	var CURTAIN_TIME = 2200;

	var options;   // the options passed by obs.php
	var game;      // the last game state we got from the site
	var offline;   // true when the site did not answer the last request
	var pending;   // the next turn, already booked, so that waking the page does not book a second
	var hideRoles; // true when the game being played keeps its roles off the overlays
	var dealt;     // how far the roles have been handed out - see dealtTo()
	var shows;     // which parts of the drawing this table is set to show - see settings()
	var shown;     // the voters already on the screen, so that only a new one flies in
	var stacks;    // the nominees whose vote card is already up, so that only a new one rises
	var step;      // how far the game had got when it was last drawn - see show()
	var stepGame;  // which game that was, so that the next game is not read as a step back
	var wasSplit;  // true when the hands last drawn were the ones the table split on
	var ground;    // the ground this table's between-games screens stand on: "dark" or "light"
	var curtainDown; // whether the curtain is across the picture, as the page last drew it
	var curtainTimer; // when the halves of an opening curtain are to be taken off the page

	this.start = function(pageOptions)
	{
		options = pageOptions;
		game = null;
		offline = false;
		shown = {};
		curtainDown = false;
		curtainTimer = null;
		// Until the site answers. Dark rather than pale because that is the ground the night
		// cover was drawn for, and because a source that cannot reach the site is one whose
		// curtain had better be the colour of a camera that has been switched off.
		ground = 'dark';
		// Everything, until the site says otherwise a moment from now. A source that comes up
		// while the site is unreachable draws the whole thing rather than half of it.
		shows = { top: true, phase: true, game: true, judge: true, donChecks: true,
			sheriffChecks: true, nominees: true, cards: true, votes: true, warnings: true,
			bestMove: true, speaker: true };
		settings();
		setInterval(settings, options.settingsInterval);
		refresh();
		document.addEventListener('visibilitychange', wake);

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

				// The ground the between-games screens of this table stand on. The overlay has
				// none of its own - it stands on a camera - but the curtain drawn over a night
				// does, and the two are one evening: a club that has set its break screens pale
				// does not want one frame of it coming up black. See apply() in between.js,
				// which reads the very same answer.
				var wants = (answer.stream.background == 'light') ? 'light' : 'dark';
				if (ground != wants)
				{
					ground = wants;
					show();
				}

				// And which parts of the drawing this table shows. Unlike the language, these
				// need no reload: nothing of them was written into the page by the server, so
				// the picture is simply drawn again. Only when something has actually changed,
				// so that the cards are not rewritten every ten seconds for nothing.
				if (readShows(answer.stream.show))
				{
					show();
				}
				if (answer.stream.locale && options.locale && answer.stream.locale != options.locale)
				{
					options.locale = answer.stream.locale; // so that one change asks for one reload
					window.location.reload();
				}

				// And whether this page is still made of what the site is made of. OBS holds on
				// to the page a source first loaded, so a deployed script would otherwise never
				// reach a source that is already running - see stream_plugin_build() in
				// include/stream.php, which is where both numbers come from.
				if (answer.stream.build && options.build && answer.stream.build != options.build)
				{
					options.build = answer.stream.build; // so that one change asks for one reload
					window.location.reload();
				}
			}).
			catch(function(error) { /* the site is not answering: leave the page as it stands */ });
	}

	// Takes what the site says about each part and answers whether any of it is news. A part the
	// answer says nothing about is left standing as it is, which is what an older site - one
	// that knows nothing of these - leaves: everything drawn, as at a table nobody has set up.
	function readShows(said)
	{
		if (!said)
		{
			return false;
		}
		var changed = false;
		for (var name in shows)
		{
			if (said[name] === undefined)
			{
				continue;
			}
			var on = (said[name] ? true : false);
			if (shows[name] !== on)
			{
				shows[name] = on;
				changed = true;
			}
		}
		return changed;
	}

	// Whether there is a strip along the top of the overlay to put anything in: the strip is
	// switched on, and something in it is - a strip with all six of its parts turned off is an
	// empty one. See STREAM_FLAG_HIDE_TOP in include/constants.php.
	function topShown()
	{
		return shows.top && (shows.phase || shows.game || shows.judge || shows.donChecks ||
			shows.sheriffChecks || shows.nominees);
	}

	// Asking what is happening at the table, and booking the next ask. The booking is cleared
	// first wherever it is made, so that refresh() can be called from outside the chain - see
	// the waking below - without leaving two chains running side by side.
	function refresh()
	{
		clearTimeout(pending);
		// The game state is taken from our own site, so the page works the same way everywhere it is deployed.
		fetch('../../api/get/current_game.php?' + new URLSearchParams(options.params)).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(data) { game = data.game; offline = false; }).
			catch(function(error) { offline = true; }).
			then(function()
			{
				show();
				clearTimeout(pending);
				pending = setTimeout(refresh, options.retryDelay);
			});
	}

	// A page nobody is looking at is slowed down by the browser and can be stopped outright: a
	// tab behind another one is held to one turn a minute and after a few minutes of that is
	// frozen where it stands. A browser source in OBS is never behind anything, so this is for
	// the frames of text_broadcast.php, where a reader can leave the page and come back to a
	// picture as old as the last turn the browser allowed - and for a source OBS itself puts
	// to sleep while its scene is off the air. Coming back into view is the moment to ask.
	function wake()
	{
		if (!document.hidden)
		{
			refresh();
			settings();
		}
	}

	//--------------------------------------------------------------------------------------
	// the pictures
	//--------------------------------------------------------------------------------------
	var IC =
	{
		// A chair, for the free seating. It was a table with seats round it first, which at the
		// size the cover gives it read as a sun - and the sun is in this very list, standing for
		// the day.
		seats: '<svg viewBox="0 0 24 24"><g fill="currentColor"><path d="M5.6 2.6h12.8c.9 0 1.6.7 1.6 1.6v7.6H4V4.2c0-.9.7-1.6 1.6-1.6z"/><rect x="2.6" y="13.2" width="18.8" height="3.2" rx="1.3"/><rect x="5.2" y="17.1" width="2.3" height="4.3" rx="1.1"/><rect x="16.5" y="17.1" width="2.3" height="4.3" rx="1.1"/></g></svg>',
		cards: '<svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="11" height="15" rx="2" fill="currentColor" opacity=".55" transform="rotate(-10 9 12.5)"/><rect x="9.5" y="4" width="11" height="15" rx="2" fill="currentColor" transform="rotate(8 15 11.5)"/></svg>',
		eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.6" fill="currentColor" stroke="none"/></svg>',
		tick: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
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

		showCurtain(g);

		// Whether the moderator has gone back since the last drawing. The game's own step only
		// grows while a game is played, so a step smaller than the one we drew last is the one
		// thing a page can read as the undo button being pressed - and it has to read it,
		// because a count of hands that is being unmade and one that is simply over look the
		// same from here. A game we were not looking at a moment ago is not a game going back.
		var sameGame = (g.id === stepGame);
		var back = (typeof g.step == 'number' && typeof step == 'number' &&
			sameGame && g.step < step);
		if (typeof g.step == 'number')
		{
			step = g.step;
			stepGame = g.id;
		}
		if (!sameGame)
		{
			// A different game at the table: nothing we were holding about the last one says
			// anything about this one.
			wasSplit = false;
		}
		showPlayers(g, back);
	}

	//--------------------------------------------------------------------------------------
	// top left: the phase, the checks and the nominees
	//--------------------------------------------------------------------------------------
	function topLeft(g)
	{
		var night = (g.phase == 'night');
		// The round as a table counts it, which is the round the game writes down. A game opens
		// with night 0, where the mafia meet and nobody is shot, and then day 0, the one circle
		// of speeches with all ten still at the table and nobody usually voted out; night 1 is
		// the first shot and day 1 the day after it, with nine. So a day and the night before it
		// wear the same number and the order on the screen is night 0, day 0, night 1, day 1 -
		// which is the count the site puts over the log of a game that has been played, see
		// view_game.php, and the one the players wear when they leave the game, see deathRound
		// in api/get/current_game.php.
		var name = (night ? options.labels.night : options.labels.day).
			replace('[0]', g.round);
		var state = stateLabel(g);

		var html = '';
		if (shows.top && shows.phase)
		{
			html = '<div class="phase gl ' + (night ? 'night' : 'day') + '">' +
				'<span class="ic">' + (night ? IC.moon : IC.sun) + '</span>' +
				'<span class="pn">' + escapeHtml(name) + '</span>';
			if (state != '')
			{
				html += '<span class="dv"></span><span class="st">' + escapeHtml(state) + '</span>';
			}
			html += '</div>';
		}

		// A pill of checks appears only once there is a check in it. The don's checks come first,
		// because the don checks first.
		var don = checkedBy(g.players, 'checkedByDon');
		var sheriff = checkedBy(g.players, 'checkedBySheriff');
		var checks = '';
		if (don.length > 0 && shows.top && shows.donChecks)
		{
			checks += '<div class="ck d gl"><span class="ic">' + IC.hat + '</span>' + checkChips(don, true) + '</div>';
		}
		if (sheriff.length > 0 && shows.top && shows.sheriffChecks)
		{
			checks += '<div class="ck s gl"><span class="ic">' + IC.star + '</span>' + checkChips(sheriff, false) + '</div>';
		}
		html += (checks == '' ? '' : '<div class="checks">' + checks + '</div>');

		// The site is not answering. The cards stay as they were - what is on the screen is the
		// last thing we knew - but whoever is running the stream has to be told that it is old.
		// Only where there is a strip to say it in: a table that has turned the strip off has
		// said it wants nothing up there, and this is no exception to that.
		if (offline && topShown())
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
		if (!shows.top || !shows.nominees || g.phase != 'day' || isOver(g))
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

	// Which part of the tournament is being played - the final, the semi-final, and so on
	// down. Empty for the main round, for a game that belongs to no tournament, and for a rung
	// deeper than the names reach: the list the page was handed holds the rungs above the main
	// round and nothing else, so there is nothing to look up in the one case that matters. A
	// tournament still in its main round is simply the tournament, and saying so would take up
	// room on the overlay for a word that tells a viewer nothing.
	function stageName(g)
	{
		if (!g.stage || !options.labels.stages)
		{
			return '';
		}
		var name = options.labels.stages[g.stage];
		return name ? name : '';
	}

	// Which table this is, unless the round is seated for one table only - then there is no
	// other table to tell it from. A round whose seating is not arranged yet says it as
	// before: tableCount is 0 there, which means nothing states how many tables it will need.
	function tableName(g)
	{
		if (!g.table || g.tableCount == 1)
		{
			return '';
		}
		return options.labels.table.replace('[0]', g.table);
	}

	function topRight(g)
	{
		var html = '';
		var parts = [];
		var name = g.tournament ? g.tournament.name : (g.club ? g.club.name : '');
		var logo = g.tournament ? g.tournament.iconUrl : (g.club ? g.club.iconUrl : null);
		var stage = stageName(g);
		var table = tableName(g);

		if (name)
		{
			parts.push('<span class="nm">' + escapeHtml(name) + '</span>');
		}
		// After the tournament and before the table: the round is the tournament's own
		// business, while the table and the game are this table's.
		if (stage)
		{
			parts.push('<span>' + escapeHtml(stage) + '</span>');
		}
		if (table)
		{
			parts.push('<span>' + escapeHtml(table) + '</span>');
		}
		if (g.tour)
		{
			parts.push('<span>' + escapeHtml(options.labels.game.replace('[0]', g.tour)) + '</span>');
		}
		if (parts.length > 0 && shows.top && shows.game)
		{
			html += '<div class="pill game gl">' +
				(logo ? '<span class="tlogo"><img src="' + escapeHtml(logo) + '" alt=""></span>' : '') +
				parts.join('<span class="dv"></span>') + '</div>';
		}

		// The judge, with the gavel after the name: the photograph says who, the gavel says what
		// they are doing there.
		var judge = g.moderator;
		if (judge && judge.name && shows.top && shows.judge)
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

		// The same three things as the pill above, in the same order and under the same rules.
		var sub = [];
		var stage = stageName(g);
		var table = tableName(g);
		if (stage)
		{
			sub.push(stage);
		}
		if (table)
		{
			sub.push(table);
		}
		if (g.tour)
		{
			sub.push(options.labels.game.replace('[0]', g.tour));
		}
		return '<div class="over gl ' + side + '"><h1>' + escapeHtml(title) + '</h1>' +
			(sub.length > 0 ? '<div class="sub">' + escapeHtml(sub.join(' · ')) + '</div>' : '') + '</div>';
	}

	//--------------------------------------------------------------------------------------
	// the night cover
	//--------------------------------------------------------------------------------------
	// A game played with its roles hidden cannot show the table at night. The camera would show
	// the mafia putting their hands up and pointing, the don pointing and the sheriff pointing
	// back, and a viewer who caught any of it would know more than the players at the table do -
	// for the rest of that game, and, where the stream is kept, for as long as the recording
	// lasts. So the whole picture is covered for the length of the night, and this stands over
	// it: what the table is doing, said without saying who anybody is.
	//
	// Nothing drawn here reads a role, and nothing may be added to it that does. Every card is
	// the town's red whoever is on it, every seat number is the chip of a player with no role,
	// there are no role badges and no checks, and the only thing said about a player who has
	// left is the thing the table itself saw: the night they were shot, or the day they were
	// voted out.

	// The steps of a night, in the order a table goes through them: the state the game calls
	// each one by, the picture it is drawn by, and the colour of that picture.
	//
	// Night 0 is the deal, the mafia's arrangement and the free seating the table settles into
	// afterwards; every night after it is the shot and the two checks. Every step is listed
	// whether it is going to happen or not - a night that left out the don's check because the
	// don is dead would say that the don is dead.
	function nightSteps(g)
	{
		if (g.round == 0)
		{
			return [['starting', IC.cards, 't'], ['arranging', IC.eye, 'r'],
				['sitting', IC.seats, 't']];
		}
		return [['shooting', IC.target, 'r'], ['donChecking', IC.hat, 'w'],
			['sheriffChecking', IC.star, 'g']];
	}

	// Whether the picture is to be covered at all: a game whose roles are hidden, standing in a
	// night. The whole of the night and not only the steps of it - the night opens with the
	// table closing its eyes and ends with them still shut, and the curtain comes down over the
	// first of those rather than over the shot. That is a table doing nothing, which is exactly
	// when there is time to draw a curtain slowly; waiting for the mafia to wake would have it
	// still crossing the picture while they are pointing at somebody.
	//
	// A table with no game on it is left alone: there is nothing to give away before the roles
	// are dealt, and the hall is what a stream has to show between games.
	function nightCovered(g)
	{
		return (g.hideRoles === true && g.phase == 'night' && g.state != 'notStarted');
	}

	// Where the night has got to: the step it is standing in, or -1 before the first of them and
	// the number of them after the last. The moon stands over both ends - the steps are all
	// still to come at one and all behind at the other - and a step stands over the middle.
	function nightAt(g)
	{
		var steps = nightSteps(g);
		for (var i = 0; i < steps.length; ++i)
		{
			if (steps[i][0] == g.state)
			{
				return i;
			}
		}
		// The night played out, waiting for the morning. Anything else that is night and not a
		// step of it is the night opening, before anything has been done in it.
		return (g.state == 'ending') ? steps.length : -1;
	}

	// The curtain itself, which is the thing it is named after: two halves that sweep in from
	// the sides of the picture and meet in the middle to cover it, and part and run off to the
	// sides to uncover it. Each half is a window on the same frame, placed so that the two of
	// them standing together are the one picture with no seam in it - see .curtain in obs.css.
	//
	// The night is written into both halves rather than the whole thing being written again,
	// because writing an element again starts its animation over: a curtain written again at
	// every step of the night would sweep shut five times a night.
	//
	// Going up takes as long as coming down, and what the curtain was covering is left on the
	// page for the whole of it. Taking it away first would have the cover vanish and the halves
	// run off over nothing, which is not a curtain opening.
	function showCurtain(g)
	{
		var div = document.getElementById('curtain');
		if (!div)
		{
			return; // one of the two narrow views, which has no picture to cover
		}

		if (nightCovered(g))
		{
			clearTimeout(curtainTimer);
			curtainTimer = null;
			if (!div.firstChild)
			{
				div.innerHTML = '<div class="cl"><div class="cin"></div></div>' +
					'<div class="cr"><div class="cin"></div></div>';
			}
			var html = nightCover(g);
			var halves = div.getElementsByClassName('cin');
			for (var i = 0; i < halves.length; ++i)
			{
				setHtml(halves[i], html);
			}
			// The cards carry nicknames and are placed the way the ones on the table are, so
			// they are measured the same way.
			fitNames(div);
			// Said every time round rather than only as it comes down, so that a table moved
			// from one ground to the other while the curtain is up is answered without waiting
			// for the night to end. It does not start the sweep again: what the halves are
			// doing is named by a class that is not the one changing.
			curtainDown = true;
			var want = curtainClass('closing');
			if (div.className != want)
			{
				div.className = want;
			}
			return;
		}

		if (!curtainDown)
		{
			return;
		}
		curtainDown = false;
		div.className = curtainClass('opening');
		clearTimeout(curtainTimer);
		curtainTimer = setTimeout(function()
		{
			div.innerHTML = '';
			div.className = curtainClass('');
			curtainTimer = null;
		}, CURTAIN_TIME);
	}

	// What the curtain wears: what it is doing, and which of the two grounds it stands on.
	function curtainClass(doing)
	{
		return 'curtain' + (ground == 'light' ? ' light' : '') + (doing ? ' ' + doing : '');
	}

	function nightCover(g)
	{
		if (!nightCovered(g))
		{
			return '';
		}

		var steps = nightSteps(g);
		var at = nightAt(g);
		// Before the first step or past the last: the moon stands where a step that is not
		// happening would, and the steps are shown all to come or all behind.
		var moon = (at < 0 || at >= steps.length);
		var i;

		var html = '<div class="nbg"><div class="glow g1"></div><div class="glow g2"></div>' +
			'<div class="glow g3"></div><div class="glow g4"></div><div class="glow vig"></div></div>';

		html += nightHead(g);

		html += '<div class="nmain"><div class="nbig ' + (moon ? 'w' : steps[at][2]) + '">' +
			(moon ? IC.moon : steps[at][1]) + '</div>' +
			(moon ? '' : '<div class="nstage">' + escapeHtml(stepName(steps[at][0])) + '</div>') +
			'</div>';

		var track = '';
		for (i = 0; i < steps.length; ++i)
		{
			var done = (i < at);
			track += (i > 0 ? '<i class="nsl"></i>' : '') +
				'<div class="ns ' + (done ? 'done' : (i == at ? 'on' : '')) + '">' +
				'<span class="ni ' + steps[i][2] + '">' + (done ? IC.tick : steps[i][1]) + '</span>' +
				'<span>' + escapeHtml(stepName(steps[i][0])) + '</span></div>';
		}
		html += '<div class="nsteps">' + track + '</div>';

		var cards = '';
		var players = g.players ? g.players : [];
		var count = Math.max(players.length, SEATS);
		for (i = 0; i < count; ++i)
		{
			cards += nightCard(players[i] ? players[i] : { number: i + 1 });
		}
		html += '<div class="nt-row">' + cards + '</div>';

		return html + nightFoot(g);
	}

	// What a step is called, in the words the overlay already names the phases of a game by.
	function stepName(state)
	{
		return options.labels.states[state] ? options.labels.states[state] : '';
	}

	// The corners of the frame, the same ones every between-games screen wears: the tournament
	// and which part of it on the left, the night in the middle, which game of how many on the
	// right. The logo is the tournament's own, or its club's where it has none - the same answer
	// the pill in the corner of the overlay gives.
	function nightHead(g)
	{
		var owner = g.tournament ? g.tournament : (g.club ? g.club : null);
		var stage = stageName(g);
		var num = '';
		if (g.tour)
		{
			num = (g.tourCount > 0)
				? options.labels.gameOf.replace('[0]', g.tour).replace('[1]', g.tourCount)
				: options.labels.game.replace('[0]', g.tour);
		}

		return '<div class="nhd"><div class="blk">' +
			((owner && owner.hasPhoto && owner.iconUrl)
				? '<span class="tlogo"><img src="' + escapeHtml(owner.iconUrl) + '" alt=""></span>' : '') +
			'<div><div class="b1">' + escapeHtml(owner ? owner.name : '') + '</div>' +
			(stage ? '<div class="b2">' + escapeHtml(stage) + '</div>' : '') + '</div></div>' +
			'<div class="nttl"><h1>' +
			escapeHtml(options.labels.night.replace('[0]', g.round)) + '</h1></div>' +
			'<div class="blk r"><div class="b1">' + escapeHtml(num) + '</div></div></div>';
	}

	// One player, as a card that says nothing but who they are and whether they are still in the
	// game. A player who has left wears the public reason - the night the table lost them, or
	// the day it voted them out - and a judge taking somebody out wears the cross it wears on
	// the table. None of the three says anything about a role.
	function nightCard(player)
	{
		var mark = '';
		if (player.state == 'dead')
		{
			if (player.deathType == 'shooting' || player.deathType == 'voting')
			{
				mark = '<span class="nx ' + player.deathType + '">' +
					(player.deathType == 'shooting' ? IC.target : IC.thumb) +
					'<span>' + player.deathRound + '</span></span>';
			}
			else
			{
				mark = '<span class="nx out">' + IC.cross + '</span>';
			}
		}

		return '<div class="ncard' + (player.state == 'dead' ? ' out' : '') + '">' +
			'<div class="ph">' + photo(player) + '</div>' + mark +
			'<div class="meta"><span class="chip n">' + player.number + '</span>' +
			'<span class="nick">' + escapeHtml(player.name ? player.name : '') + '</span>' +
			'</div></div>';
	}

	// The referee, in the bottom right corner, said the way the between-games screens say it -
	// see obs_foot() in screens.php, including the table being left out where the round is
	// seated for one.
	function nightFoot(g)
	{
		var table = tableName(g);
		var judge = g.moderator;
		var html = '';
		if (table)
		{
			html += '<span>' + escapeHtml(table) + '</span>';
		}
		if (judge && judge.name)
		{
			html += (table ? '<span class="nfdv"></span>' : '') + IC.gavel +
				'<b>' + escapeHtml(judge.name) + '</b>' +
				((judge.hasPhoto && judge.iconUrl)
					? '<span class="nav"><img src="' + escapeHtml(judge.iconUrl) + '" alt=""></span>'
					: '<span class="nav none"></span>');
		}
		return (html == '') ? '' : '<div class="nftr">' + html + '</div>';
	}

	//--------------------------------------------------------------------------------------
	// the players
	//--------------------------------------------------------------------------------------
	function showPlayers(g, back)
	{
		var div = document.getElementById('players');
		if (!div)
		{
			return;
		}

		// A table that has turned the cards off keeps the row of them empty. The game is still
		// followed underneath, so turning them back on puts up what is happening then.
		if (!shows.cards)
		{
			while (div.firstChild)
			{
				div.removeChild(div.firstChild);
			}
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
		// Measured before the cells are written again: a vote that is about to go is measured where
		// it still stands, because a moment later the card it stood on no longer holds it.
		var going = snapVotes(div);
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

		// A table that splits puts every hand down and is asked again. The hands it split on
		// stand through the speeches of the tied players and no further - see playersContext() -
		// so the draw after the last of those speeches is the moment every hand on the table
		// comes down at once. They go home to the players who put them up, the way they do when
		// the moderator takes a count back: the panels themselves stay where they are, because
		// the same seats are about to be asked about again.
		var swap = (wasSplit === true && !context.split && !back);
		wasSplit = context.split;

		fitNames(div);
		flyVotes(div);
		clearVotes(div, going, back, swap);
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

		// How long the hands stay up: all through the counting, and then through the last word
		// of whoever they put out. A table listening to that speech is still looking at the vote
		// that caused it - who was against them, and how close it was - and the speech is where
		// that is talked about. When it ends the whole thing goes, and nothing is left over the
		// card: a player who left the game is told by the mark in the corner of their own card,
		// not by numbers standing above it.

		context.counting = (context.voting || (context.out !== null && context.out.isSpeaking));
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

		// The count the table split on. Those hands are down - that is what a split means - but
		// they are the reason the speeches are happening, so they stay on the screen through
		// them rather than leaving two seats standing there with nothing against either. Through
		// the speeches and no further: when the last of them ends and the table is asked again,
		// the cards over those same seats are where the new hands are about to land, and an old
		// count sitting in them would be read as the new one.
		context.split = false;
		if (context.tie && context.max <= 0 && g.splitVotes)
		{
			for (var k = 0; k < g.splitVotes.length; ++k)
			{
				context.votes[g.splitVotes[k].nominee] = g.splitVotes[k].voters;
				context.max = Math.max(context.max, g.splitVotes[k].voters.length);
			}
			// Said out loud, because the draw where this stops being true is the draw where the
			// table is asked again and the panels change hands - see showPlayers().
			context.split = true;
		}

		// Everybody who has been named, whether a hand has gone up for them yet or not. A card
		// stands over each of them from the moment the counting begins, empty if it must: an
		// empty card says the table is being asked about this seat and the hands are still to
		// come, where nothing at all says only that nothing is happening. It is also what makes
		// the speeches of a split table readable - the game sends back only the seats that tied,
		// so a card over each of them is the answer to who is being voted on.
		context.named = {};
		var named = g.nominees ? g.nominees : [];
		for (var i = 0; i < named.length; ++i)
		{
			context.named[named[i]] = true;
		}

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

	// Whoever was voted out this round, once the game has written it down. A day wears the number
	// of the round it is written down in - see the phase above - so the day this is and the tag on
	// their card are the same number.
	function votedOut(g)
	{
		for (var i = 0; i < g.players.length; ++i)
		{
			var p = g.players[i];
			if (p.state == 'dead' && p.deathType == 'voting' && p.deathRound == g.round && g.phase == 'day')
			{
				return p;
			}
		}
		return null;
	}

	function cellHtml(g, player, context)
	{
		var role = roleOf(player);
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
			classes.push('none');
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
		if (player.isSpeaking && shows.speaker)
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

		// What stands above the card: the hands being counted, or the best guess of the player
		// shot on the first night. Never both at once - they are the same space.
		var above = '';
		var held = false;
		if (shows.votes && context.counting &&
			(context.votes[player.number] || context.named[player.number]))
		{
			above = voteCard(g, player, context);
			// And whether the count has to be held where it is. The card under it is the card of a
			// player who has just been voted out, which is shorter than a living one, and the count
			// is still up because they are speaking their last word. See .cell.held in obs.css.
			held = (player.state == 'dead');
		}
		else if (shows.bestMove && context.legacy && context.legacyOf == player.number)
		{
			above = bestGuess(g, context);
		}
		return '<div class="cell' + (held ? ' held' : '') + '">' + above + card + '</div>';
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
		// Either the address of the source or the table's own settings can keep these off. The
		// address was the only way before the settings existed, and a source set up that way
		// goes on working.
		if (options.hideWarnings || !shows.warnings)
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
			// The opposite team winning is told in words rather than by the cross: it is not the
			// judge sending one player away but a whole side going down with them, and the table
			// calls it by its three letters.
			var out = (type == 'oppositeTeamWins')
				? '<div class="elim out td">' + escapeHtml(options.labels.teamDefeat) + '</div>'
				: '<div class="elim out">' + IC.cross + '</div>';
			// And a player the mafia had already taken that night wears that as well. The judge's
			// word is the last thing that happened to them, not the whole of it, and the table
			// watched both - see shotRound in api/get/current_game.php, which is where the night
			// is read from once their own death no longer holds it. Side by side rather than one
			// above the other: this corner is small, and a second row would reach down over the
			// picture - the same reason the fouls stand in a row.
			if (player.shotRound)
			{
				return '<div class="marks"><div class="row">' +
					'<div class="elim when shooting">' + IC.target +
					'<span>' + player.shotRound + '</span></div>' + out + '</div></div>';
			}
			return '<div class="marks">' + out + '</div>';
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
		var voters = context.votes[player.number] ? context.votes[player.number] : [];
		var lead = (voters.length == context.max && context.max > 0);
		// While a split table speaks again there are no hands up at all, and every card that is
		// standing belongs to a seat that tied: each of them is told so rather than none.
		var tied = (context.tie && (lead || context.max <= 0));
		var classes = 'stk';
		// While the player who was voted out says their last word their own count is ringed.
		// The other counts used to be taken down to a third of their ink at the same moment,
		// which took the table's reading of the day off the screen exactly while it was being
		// talked about. They stand as they stood.
		if (context.out && context.out.number == player.number)
		{
			classes += ' out';
		}
		else if (tied)
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
			(tied ? '<span class="stk-tag">' + escapeHtml(options.labels.tieTag) + '</span>' : '') +
			'</div>' +
			'<div class="stk-c" style="--per:' + perRow(voters.length) + '">' + chips + '</div></div>';
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
	//
	// Two widths come into it, and they are not the same one. The name lies in .meta, which
	// starts a little in from the left edge of the card - see .pc .meta in obs.css - so the room
	// it has is .meta's and not the card's. Measuring the card gave every name a few pixels it
	// did not have, and a name that came out within those few pixels was left at a size it did
	// not fit at and cut off instead of being set one step smaller. But the middle a short name
	// is meant to sit in is the middle of the card, which is what a reader sees, so the offset
	// between the two is taken off again when the name is placed.
	function fitNames(div)
	{
		var scale = parseFloat(getComputedStyle(document.documentElement).fontSize) / 16;
		var nicks = div.getElementsByClassName('nick');
		for (var i = 0; i < nicks.length; ++i)
		{
			var nick = nicks[i];
			var box = nick.parentNode;
			var card = box.parentNode;
			var width = box.clientWidth;
			var inset = card.clientWidth - width; // how far .meta starts in from the card
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
			nick.style.setProperty('--nl',
				Math.max(reserved, (card.clientWidth - textWidth) / 2 - inset) + 'px');
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
				chip.style.setProperty('--lift', lift(at.left - to.left, to.height) + 'px');
				chip.classList.add('fly');
			}
		}
		shown = seen;
	}

	// The vote card itself. The chips fly in one by one, but the glass they land on simply
	// appeared, which on a stream is a panel snapping into being. It slides out from under the
	// player's card instead - and only the first time, the tick the nominee's first vote arrives:
	// the cell is written again on every count, so an animation left on the card would play on
	// every hand raised.
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
				cards[i].style.setProperty('--drop', dropOf(cards[i]) + 'px');
				cards[i].classList.add('rise');
			}
		}
		stacks = seen;
	}

	// How far a vote card has to go down to be out of sight behind the player's card: the
	// distance between the two tops. Measured rather than worked out, because the gap between
	// them is not always the same gap - a seat whose player has left keeps the height their card
	// gave up under the count that is still being read, and the vote card then stands further
	// above it than the others do. See .cell.held in obs.css.
	function dropOf(stk)
	{
		var cell = stk.parentNode;
		var pc = cell ? cell.querySelector('.pc') : null;
		var rect = stk.getBoundingClientRect();
		if (!pc)
		{
			return rect.height;
		}
		return Math.max(pc.getBoundingClientRect().top - rect.top, rect.height);
	}

	// How high above the straight line the throw goes. A vote from the next seat is tossed a
	// little way up, one from across the table a good deal higher, and nothing is thrown higher
	// than about two and a half chips: the lift is measured in the chip's own size so that it
	// comes out the same at every source size, the whole drawing being in rem.
	function lift(dx, size)
	{
		return Math.min(Math.abs(dx) * 0.14 + size * 0.8, size * 2.6);
	}

	// What the vote cards hold right now, taken before the cells are written again. Whatever is
	// not on the screen afterwards is seen leaving from where this says it stood.
	function snapVotes(div)
	{
		var snap = { chips: [], cards: [] };
		if (noMotion())
		{
			return snap;
		}
		var cards = div.querySelectorAll('.stk[data-seat]');
		for (var i = 0; i < cards.length; ++i)
		{
			var card = cards[i];
			// The card itself, not a copy of it: writing the cell again takes this one out of the
			// page but leaves it whole in hand, which is all a thing that is leaving needs.
			// How far down this one would have to go to be behind the player's card, taken while
			// the cell it belongs to is still standing: a copy laid over the page afterwards has
			// no cell to measure itself against.
			snap.cards.push({ seat: card.getAttribute('data-seat'),
				rect: card.getBoundingClientRect(), drop: dropOf(card), node: card });
			var chips = card.querySelectorAll('.stk-c .chip[data-from]');
			for (var k = 0; k < chips.length; ++k)
			{
				// The card as well as the voter: a vote whose card went with it is carried away
				// by the card rather than seen off on its own - see clearVotes().
				snap.chips.push({ seat: card.getAttribute('data-seat'),
					from: chips[k].getAttribute('data-from'), cls: chips[k].className,
					rect: chips[k].getBoundingClientRect() });
			}
		}
		return snap;
	}

	// A count that is taken down does not simply go out. The panel goes back the way it came,
	// down under the player's card, whichever of the two things has happened to it - it slid out
	// from under that card and that is where it belongs. What differs is the votes.
	//
	// Hands come down off a count in two ways. The moderator can be unmaking it - they pressed
	// back - or the table can have split, put every hand down and be about to be asked again.
	// Either way the hands are given back: each vote goes home to the player who gave it, thrown
	// along the same curve it arrived by, settling into their seat number. The panel goes with
	// them where the seat is not being asked about any more, emptied; where it is, it stays, and
	// the hands land again on the same card a moment later.
	//
	// Any other count is simply over - the hands are down for good, or the last word of whoever
	// they put out has been said - and the votes ride the panel down, still in it. The table is
	// shown the day closing, rather than ten chips finding their way home from a day nobody is
	// going back to.
	//
	// The cells have already been written again without any of this, so what moves is laid over
	// the page at the place it stood in: a fresh chip for each vote, and the card itself, which
	// writing the cell again left detached and whole.
	function clearVotes(div, was, back, swap)
	{
		var i;
		var here = {};
		var chips = div.querySelectorAll('.stk-c .chip[data-from]');
		for (i = 0; i < chips.length; ++i)
		{
			here[chips[i].getAttribute('data-from')] = true;
		}
		var standing = {};
		var cards = div.querySelectorAll('.stk[data-seat]');
		for (i = 0; i < cards.length; ++i)
		{
			standing[cards[i].getAttribute('data-seat')] = true;
		}

		// Which of the panels that were up are going down: the ones whose seat has no panel any
		// more. A seat that is about to be asked about again keeps its panel and loses only the
		// hands that were on it.
		var sinking = {};
		for (i = 0; i < was.cards.length; ++i)
		{
			if (!standing[was.cards[i].seat])
			{
				sinking[was.cards[i].seat] = true;
			}
		}

		for (i = 0; i < was.chips.length; ++i)
		{
			var vote = was.chips[i];
			if (here[vote.from])
			{
				continue;
			}
			// A vote whose panel is going down is inside it and rides it under; only a vote taken
			// off a panel that is staying up has to be seen off on its own.
			if (!back && sinking[vote.seat])
			{
				continue;
			}
			var ghost = document.createElement('div');
			ghost.textContent = vote.from;
			// Home to the player who put it up, either because the moderator is taking the count
			// back or because the table split and is being asked again: both are hands coming
			// down off a count, and a hand that comes down belongs to whoever raised it.
			if (back || swap)
			{
				var seat = seatOf(div, vote.from);
				var at = seat ? seat.getBoundingClientRect() : null;
				// Into the middle of the seat number, which is smaller than the chip in the air.
				var dx = at ? at.left + (at.width - vote.rect.width) / 2 - vote.rect.left : 0;
				var dy = at ? at.top + (at.height - vote.rect.height) / 2 - vote.rect.top : 0;
				// Without the class that flew it in: it is going the other way now.
				ghost.className = vote.cls.replace(' fly', '') + ' ghost';
				ghost.style.setProperty('--dx', dx + 'px');
				ghost.style.setProperty('--dy', dy + 'px');
				ghost.style.setProperty('--lift', lift(dx, vote.rect.height) + 'px');
			}
			else
			{
				// One hand off a card that is still standing, with the count it belongs to
				// still being taken: a hand lifted off the last name while the moderator is
				// standing on it, which is the one name that is shown as it is counted. Nothing
				// has been given back - the count is still going - so this one goes out where
				// it is rather than flying home.
				ghost.className = vote.cls.replace(' fly', '') + ' gone';
			}
			layOver(ghost, vote.rect);
		}

		for (i = 0; i < was.cards.length; ++i)
		{
			var card = was.cards[i];
			if (!sinking[card.seat])
			{
				continue;
			}
			var copy = card.node;
			copy.classList.remove('rise');
			if (back)
			{
				var votes = copy.querySelector('.stk-c');
				if (votes)
				{
					// The chips are in the air on their way home; the copy keeps only the glass and the
					// count, and the height it is given holds its shape without them.
					votes.parentNode.removeChild(votes);
				}
			}
			else
			{
				// The votes ride it down, still in it. A chip caught in the middle of flying in
				// is stopped first - putting the copy back on the page starts its throw again,
				// and a vote arriving into a panel that is on its way out is nonsense.
				var flying = copy.querySelectorAll('.chip.fly');
				for (var k = 0; k < flying.length; ++k)
				{
					flying[k].classList.remove('fly');
				}
			}
			copy.style.setProperty('--drop', card.drop + 'px');
			copy.classList.add('sink');
			layOver(copy, card.rect);
		}
	}

	// Puts a copy where the thing it is a copy of stood, on top of everything, and takes it away
	// when its animation is over. The timer is only in case the animation never reports itself.
	function layOver(ghost, rect)
	{
		ghost.style.left = rect.left + 'px';
		ghost.style.top = rect.top + 'px';
		ghost.style.width = rect.width + 'px';
		ghost.style.height = rect.height + 'px';
		document.body.appendChild(ghost);
		var done = function()
		{
			if (ghost.parentNode)
			{
				ghost.parentNode.removeChild(ghost);
			}
		};
		ghost.addEventListener('animationend', done);
		setTimeout(done, 2000);
	}

	function noMotion()
	{
		return window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;
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
