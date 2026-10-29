//  The voting, shown over the game on the overlay: the hands going up and where they fall.
//
//  A hand is a small square with the number of its player on it, in the colour of their role -
//  the same colours the info bar uses, a size smaller. They appear over the living players when
//  the counting begins; as the moderator counts them off for one name and moves on to the next,
//  the hands counted fly up to the info bar and line up in a column under the name they were
//  counted for. A count is shown once the moderator has pressed Next on it, which is also when
//  the text broadcast tells it - until then the name being counted holds everybody nobody has
//  been counted for yet, and the table would read as voting against one person. The last name
//  is the exception: nothing can be taken away from it any more, so it goes up with the rest.
//
//  The hands go when the names do, and not a moment after: hands counted for names that are no
//  longer on the screen say nothing. The info bar keeps the nominees up to the end of the day,
//  so the counted hands stand under them until then - which is also why the hands keep
//  following the names while they stand there, since the bar moves along as the day puts other
//  things on it.
//
//  A table that splits puts every hand down - the count begins again from nothing, and the
//  hands fly up again as the new one is taken.
//
//  The moderator can also step back through the game, and stepping back out of the voting into
//  the speeches undoes it rather than ends it: the day is being played again from before the
//  hands went up, so they go at once and come back when the counting does.
//
//  Stepping back the other way - out of the night into the day that has been voted - brings
//  the names back to the info bar, and the hands counted for them are put back under them.
//  The same thing happens when a source is opened in the middle of such a day: what is on the
//  screen is what the game is standing in, however the page came to be there.
//
//  A hand comes up from among the cards, where it is hidden behind the one it belongs to, and
//  the whole voting leaves one of five ways - blown away as dust, gathered into a heap,
//  falling, simply going, or scattered - picked at random each time, so that a tournament does
//  not play the same trick all evening.
//
//  Everything here is drawn on one sheet over the page, and everything on it is put where it
//  belongs by its transform. So a flight is not animated at all: the hand is given another
//  place and the stylesheet carries it there. The places are worked out afresh every time the
//  site answers, which is what makes all of it follow the source being resized. The ways out
//  work the same way: the place is worked out here, how long it takes is in obs.css.
//
//  The sheet is on the overlay only. The other two sources are a strip apiece - the players
//  without the info bar, the info bar without the players - and there is nowhere in either of
//  them for a hand to fly to.

var obsVoting = new function()
{
	// The side of a hand, in rem. Smaller than a number in the info bar, both because ten of
	// these stand over the game itself and because a column of them has to fit under a name
	// there without running into the column beside it.
	var TOKEN = 2.25;
	var GAP = 0.25;    // rem between a hand and what it hangs under
	var RISE = 10;     // rem below its place a hand starts, among the cards, on the way in

	// The ways a voting can leave the screen, one of them picked for the whole of it. They are
	// drawn by obs.css; where each hand ends up is worked out in leave().
	var WAYS = ['away', 'together', 'falling', 'fading', 'scattering'];

	var layer;     // the sheet all of this is drawn on, or null on a page without one
	var tokens;    // player number -> the hand of that player in the count being taken now
	var columns;   // nominee number -> where its number stands in the info bar, in rem
	var columnTop; // the height the columns of hands start at, in rem, under the info bar
	var order;     // the nominees in the order the info bar has them
	var state;     // 'idle', 'voting' while the hands are being counted, 'standing' after the
	               // counting while the names are still in the info bar
	var round;     // the round the hands on the sheet belong to

	this.start = function()
	{
		layer = document.getElementById('voting');
		forget();
		// Everything is placed in rem, which follows the source being resized, but the places
		// themselves are read off the page and have to be read again when it changes shape.
		window.addEventListener('resize', layout);
	}

	// Called with every game state the site sends back, as obs.js draws the rest of the page.
	this.show = function(game, hideRoles)
	{
		if (!layer)
		{
			return;
		}

		// Nothing of a game that is over belongs on the table the next one is played at.
		if (game.state == 'notStarted')
		{
			clear();
		}

		// The nominees speak between one count and the next, so a table that has split is
		// still voting.
		var counting = (game.state == 'voting' || game.state == 'nomineeSpeaking');
		var back = steppedBack(game);
		if (state == 'voting' && (!counting || game.round != round))
		{
			if (back)
			{
				dismiss(); // the voting is being taken back, not finished
			}
			else
			{
				state = 'standing'; // counted, and waiting for the names to go
			}
		}
		if (counting && state == 'standing' && game.round == round)
		{
			state = 'voting'; // and back into the count it came out of, with the same hands
		}
		if (counting && state != 'voting')
		{
			dismiss(); // whatever is left of the day before goes now
			startVoting(game, hideRoles);
		}

		// A day that has been voted and is still showing its names, with no hands under them:
		// the game has been stepped back into it out of the night, or this page was opened
		// while it stood there. Either way the hands belong under the names.
		if (state == 'idle' && !counting && !back && game.phase == 'day' &&
			game.votes && game.votes.length > 0 && nominees().length > 0)
		{
			recall(game, hideRoles);
		}

		if (state == 'voting')
		{
			readVoting(game, hideRoles);
		}
		else if (state == 'standing')
		{
			// The hands stand with the names: they go when the bar lets the names go, or at
			// once if the day is taken back to before the voting. The bar is read again every
			// time, because what happens in the rest of the day - a best move left behind, a
			// player going - moves the names along it, and the hands are to stay under them.
			if (back || nominees().length == 0)
			{
				dismiss();
			}
			else
			{
				readColumns();
			}
		}
		layout();
	}

	//--------------------------------------------------------------------------------------
	// the counting
	//--------------------------------------------------------------------------------------
	// A hand apiece for everybody still in the game. The dead have none to put up, and a seat
	// nobody is sitting on is not a player.
	function startVoting(game, hideRoles)
	{
		state = 'voting';
		round = game.round;
		var players = game.players ? game.players : [];
		for (var i = 0; i < players.length; ++i)
		{
			var player = players[i];
			if (!(player.id > 0) || player.state == 'dead')
			{
				continue;
			}
			var token = createToken(player.number);
			setRole(token, player, hideRoles);
			// Up from among the cards: it is put down behind the one it belongs to - the
			// cards stand in front of this sheet, see .players in obs.css - and then let up
			// into its place. Its place is worked out first, because a hand put on the page
			// before it has one flies in from the corner of the screen.
			home(token, layer.getBoundingClientRect(), rem());
			rise(token);
			layer.appendChild(token);
			tokens[player.number] = token;
		}
	}

	// Puts the hands of a count that is over back under their names. Only the hands that were
	// counted: the counting is done, so there is nobody left to wait over their own player -
	// and a player voted out is dead by now, which does not take back the hand they put up.
	function recall(game, hideRoles)
	{
		state = 'standing';
		round = game.round;
		readColumns();

		var r = rem();
		var players = game.players ? game.players : [];
		for (var i = 0; i < game.votes.length; ++i)
		{
			var nominee = game.votes[i].nominee;
			var voters = game.votes[i].voters ? game.votes[i].voters : [];
			var column = columns[nominee];
			if (!column)
			{
				continue; // a name the info bar is not showing has nowhere to hang its hands
			}
			for (var j = 0; j < voters.length; ++j)
			{
				var token = createToken(voters[j]);
				token.obsVote = nominee;
				token.obsPlace = j;
				setRole(token, players[voters[j] - 1], hideRoles);
				// Placed before it is on the page, so that it is there rather than flying in.
				place(token, r, column.tx, columnTop + j * (TOKEN + GAP));
				layer.appendChild(token);
				tokens[voters[j]] = token;
			}
		}
	}

	// Starts a hand below its place and lets it come up, which it does from behind the card of
	// the player who put it up. The place itself is already worked out - this only holds the
	// hand down for a moment and then lets go.
	function rise(token)
	{
		var place = token.obsTransform;
		token.className += ' rising';
		token.style.transform = place + ' translate(0, ' + (RISE * rem()) + 'px)';
		// Let go on the frame after the one it is drawn down there in. Letting go any sooner -
		// in this turn, or on the next frame, before the hand has been drawn once - would give
		// the browser nothing to come up from, and it would simply be in its place.
		requestAnimationFrame(function()
		{
			requestAnimationFrame(function() { token.style.transform = place; });
		});
		setTimeout(function() { token.className = token.className.replace(' rising', ''); }, 1300);
	}

	// Where every hand belongs now: under the name it has been counted for, or over its own
	// player while nobody has counted it.
	function readVoting(game, hideRoles)
	{
		readColumns();

		// Who is counted for whom, by the number of the player who put the hand up.
		var counted = {};
		var votes = game.votes ? game.votes : [];
		for (var i = 0; i < votes.length; ++i)
		{
			var voters = votes[i].voters ? votes[i].voters : [];
			for (var j = 0; j < voters.length; ++j)
			{
				counted[voters[j]] = { nominee: votes[i].nominee, place: j };
			}
		}

		var players = game.players ? game.players : [];
		for (var number in tokens)
		{
			var token = tokens[number];
			token.obsVote = counted[number] ? counted[number].nominee : 0;
			token.obsPlace = counted[number] ? counted[number].place : 0;
			// The moderator can turn the roles over in the middle of a game, and a role handed
			// out during the day is a colour the hand should be wearing.
			setRole(token, players[number - 1], hideRoles);
		}
	}

	// Where the columns hang: under the numbers in the info bar, read off the page because the
	// bar lays itself out and a name added to it moves along the ones after it.
	function readColumns()
	{
		var base = layer.getBoundingClientRect();
		var r = rem();
		var elements = nominees();

		columns = {};
		order = [];
		for (var i = 0; i < elements.length; ++i)
		{
			var number = parseInt(elements[i].textContent, 10);
			if (!(number >= 1 && number <= 10))
			{
				continue;
			}
			var rect = elements[i].getBoundingClientRect();
			columns[number] = {
				x: (rect.left - base.left) / r,
				y: (rect.top - base.top) / r,
				w: rect.width / r,
				h: rect.height / r };
			order.push(number);
		}

		// A column hangs under the info bar rather than under the number itself, so that the
		// first hand in it does not sit half on the bar and half off it. All the columns start
		// at the same height, which is also how they read as one table.
		var bar = document.getElementsByClassName('top-bar')[0];
		columnTop = bar ? (bar.getBoundingClientRect().bottom - base.top) / r + GAP : 0;
		for (var name in columns)
		{
			columnTop = Math.max(columnTop, columns[name].y + columns[name].h + GAP);
		}

		// Where each column stands. Under its own name - except that the names are packed
		// against each other in the bar, so a column is nudged along where it would otherwise
		// run into the one before it.
		var left = 0;
		for (var i = 0; i < order.length; ++i)
		{
			var column = columns[order[i]];
			column.tx = Math.max(column.x + (column.w - TOKEN) / 2, left);
			left = column.tx + TOKEN + 0.0625;
		}
	}

	// The names have gone off the info bar, so the hands put up for them go too, there and
	// then. They go from where they stand: there is nothing left on the bar for them to fly
	// back to or away from.
	function dismiss()
	{
		// One way for the whole voting: they are a table of hands and they go as one.
		var way = WAYS[Math.floor(Math.random() * WAYS.length)];
		var heap = (way == 'together') ? middle() : null;
		for (var number in tokens)
		{
			leave(tokens[number], way, heap);
		}
		forget();
	}

	// The middle of where the hands are standing, which is where they gather to collapse.
	function middle()
	{
		var x = 0;
		var y = 0;
		var count = 0;
		for (var number in tokens)
		{
			x += tokens[number].obsX;
			y += tokens[number].obsY;
			++count;
		}
		return { x: count > 0 ? x / count : 0, y: count > 0 ? y / count : 0 };
	}

	// Whether the game is standing where it stood before its voting. The moderator steps back
	// through a game as well as forward, and a day that is back at the speeches has no voting
	// to show - not the hands counted, and not the hands waiting to be.
	function steppedBack(game)
	{
		return (game.phase == 'day' &&
			(game.state == 'starting' || game.state == 'speaking' || game.state == 'nightKillSpeaking'));
	}

	// Nothing of the last game stays on the screen when the next one begins.
	function clear()
	{
		if (state == 'idle' && !layer.firstChild)
		{
			return;
		}
		while (layer.firstChild)
		{
			layer.removeChild(layer.firstChild);
		}
		forget();
	}

	// The numbers of the nominees as the info bar has them now.
	function nominees()
	{
		return document.querySelectorAll('#nominees .element');
	}

	// Everything to do with the count that has just been put away.
	function forget()
	{
		tokens = {};
		columns = {};
		order = [];
		state = 'idle';
		round = -1;
	}

	//--------------------------------------------------------------------------------------
	// where everything stands
	//--------------------------------------------------------------------------------------
	// Works out the place of every hand on the sheet and puts it there. A place that has
	// changed since the last time is a flight, because the stylesheet carries a hand from
	// where it was to where it is now.
	function layout()
	{
		if (!layer)
		{
			return;
		}
		var base = layer.getBoundingClientRect();
		var r = rem();

		for (var number in tokens)
		{
			var token = tokens[number];
			var column = columns[token.obsVote];
			if (column)
			{
				place(token, r, column.tx, columnTop + token.obsPlace * (TOKEN + GAP));
			}
			else if (state == 'voting')
			{
				// Nobody has counted this hand yet, so it waits over its own player. Once the
				// counting is over a hand stays where it was put, whatever becomes of the bar.
				home(token, base, r);
			}
		}
	}

	// Over its own player, just clear of the card. The cards are where the browser put them, so
	// the place is read off the page rather than worked out.
	function home(token, base, r)
	{
		var players = document.getElementById('players');
		var card = players ? players.children[token.obsNumber - 1] : null;
		if (!card)
		{
			return;
		}
		var rect = card.getBoundingClientRect();
		place(token, r, (rect.left - base.left) / r + (rect.width / r - TOKEN) / 2,
			(rect.top - base.top) / r - TOKEN - GAP);
	}

	// Everything on the sheet is placed by its transform, which is also what the stylesheet
	// animates. Sizes are in rem so that a source of another size is the same drawing; the
	// transform is in pixels, so the rem is what it is worked out with.
	function place(el, r, x, y)
	{
		var transform = 'translate(' + (x * r) + 'px, ' + (y * r) + 'px)';
		// In pixels as well, for the ways out, which are worked out from where the hand is
		// rather than from where it belongs.
		el.obsX = x * r;
		el.obsY = y * r;
		if (el.obsTransform !== transform)
		{
			el.obsTransform = transform;
			el.style.transform = transform;
		}
	}

	function createToken(number)
	{
		var token = document.createElement('div');
		token.className = 'vote-token';
		token.textContent = number;
		token.obsNumber = number;
		token.obsVote = 0;
		token.obsPlace = 0;
		return token;
	}

	function setRole(token, player, hideRoles)
	{
		var role = (!hideRoles && player && player.role) ? player.role : '';
		var name = token.className.replace(/ role-[a-z]+/, '');
		token.className = name + (role == '' ? '' : ' role-' + role);
	}

	// Off the screen, the way the voting was given. Everything here is worked out against the
	// place the hand is standing in - the transforms are read left to right, so what is added
	// on the end moves it from where it is.
	function leave(el, way, heap)
	{
		var r = rem();
		var away = '';
		switch (way)
		{
		case 'away':
			// To dust: off to the right and up a little, growing smaller as it blurs.
			away = ' translate(' + (20 * r) + 'px, ' + (-8 * r) + 'px) rotate(20deg) scale(0.5)';
			break;
		case 'together':
			// To the middle of them all, and then, once they are there, to nothing.
			away = ' translate(' + (heap.x - el.obsX) + 'px, ' + (heap.y - el.obsY) + 'px)';
			setTimeout(function()
			{
				el.className += ' gone';
				el.style.transform = el.obsTransform + away + ' scale(0)';
			}, 600);
			break;
		case 'falling':
			// Down off the bottom, each hand its own way over.
			away = ' translate(' + (random(3) * r) + 'px, ' + ((32 + Math.random() * 20) * r) + 'px)' +
				' rotate(' + random(80) + 'deg)';
			break;
		case 'scattering':
			// Every one its own way out, turning over itself as it goes.
			var angle = Math.random() * 2 * Math.PI;
			var far = (20 + Math.random() * 15) * r;
			away = ' translate(' + (Math.cos(angle) * far) + 'px, ' + (Math.sin(angle) * far) + 'px)' +
				' rotate(' + random(720) + 'deg) scale(0.4)';
			break;
		}

		el.className += ' leaving ' + way;
		if (away != '')
		{
			el.style.transform = el.obsTransform + away;
		}
		setTimeout(function()
		{
			if (el.parentNode)
			{
				el.parentNode.removeChild(el);
			}
		}, 1600);
	}

	// Somewhere between -spread and spread, for the ways out that are not the same twice.
	function random(spread)
	{
		return (Math.random() * 2 - 1) * spread;
	}

	// The size everything here is drawn in - see the top of obs.css.
	function rem()
	{
		return parseFloat(getComputedStyle(document.documentElement).fontSize);
	}
}
