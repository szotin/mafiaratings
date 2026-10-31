//  Shows one screen at a time between games on a page used as a browser source in OBS Studio.
//  The page skeleton is created by between.php. Each screen is a page of its own that reads
//  straight from the database and prints itself; this script fetches them in turn.
//
//  How the table is set up - the language, how long a screen stays up, which of them to show,
//  what ground they stand on - is not in the address of the source. It is kept on the site and
//  asked for every few seconds, so a change made there reaches a source that is already
//  running without anybody touching OBS.

var obs = new function()
{
	var options;    // what between.php put on the page: the table, and where to ask about it
	var stream;     // how this table is set up, as last heard from the site
	var at;         // which screen of the round we are on
	var empty;      // how many screens in a row had nothing, so as not to spin forever

	this.start = function(pageOptions)
	{
		options = pageOptions;
		at = -1;
		empty = 0;

		// Nothing can be shown until we know what to show, so the first answer is waited for
		// rather than guessed at. After that it is asked for again on its own clock.
		settings().then(function() { next(); });
		setInterval(settings, options.settingsInterval);
	}

	//-----------------------------------------------------------------------------------------
	// how this table is set up
	//-----------------------------------------------------------------------------------------
	function settings()
	{
		return fetch(options.settingsUrl + '&' + new URLSearchParams(options.params)).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(answer) { if (answer && answer.stream) { apply(answer.stream); } }).
			catch(function(error) { /* the site is not answering: carry on with what we have */ });
	}

	// What can be done right here is done right here - the ground the screens stand on. The
	// rest is picked up by the parts that use it: the next screen is fetched in the language
	// that is set then, and put up for however long the delay says then.
	function apply(settings)
	{
		stream = settings;

		// Shown at all, or not. What is behind it goes on as before - the screens are still
		// fetched in their turn - so bringing it back puts up a screen that is current rather
		// than the one that was up when it went away.
		document.body.classList.toggle('hidden-source', settings.hideBetween ? true : false);

		var light = (settings.background == 'light');
		var clear = (settings.transparent ? true : false);
		var body = document.body;
		if (body.classList.contains('bg-light') != light ||
			body.classList.contains('bg-clear') != clear)
		{
			body.classList.toggle('bg-dark', !light);
			body.classList.toggle('bg-light', light);
			body.classList.toggle('bg-clear', clear);
		}
	}

	//-----------------------------------------------------------------------------------------
	// the round of screens
	//-----------------------------------------------------------------------------------------
	// Walks the screens in order, over and over. A screen is fetched afresh every time its turn
	// comes round, so a game written down between two showings is there the next time. The list
	// can change under us when the site is asked again, which is why the place in it is kept as
	// a number and brought back inside the list rather than trusted to stay where it was.
	function next()
	{
		if (!stream || !stream.screens || stream.screens.length <= 0)
		{
			setTimeout(next, 1000); // nothing to show yet; ask again shortly
			return;
		}

		at += 1;
		if (at >= stream.screens.length)
		{
			at = 0;
		}
		load(stream.screens[at]).then(show);
	}

	function load(page)
	{
		var params = new URLSearchParams(options.params);
		if (stream && stream.locale)
		{
			params.set('locale', stream.locale);
		}
		return fetch(page + '?' + params).
			then(function(response) { return response.ok ? response.text() : Promise.reject(response.status); }).
			catch(function(error) { return ''; }).
			then(function(html) { return html.trim(); });
	}

	// An empty answer means that screen has nothing to say - which is how each of them decides
	// for itself whether it is shown at all - so move straight on to the next one.
	function show(html)
	{
		if (html != '')
		{
			empty = 0;
			put(html);
			return;
		}

		empty += 1;
		if (empty < stream.screens.length)
		{
			next();
			return;
		}

		// None of them had anything: before the first game of a round with no seating, after the
		// last game of a tournament. Put up the title rather than a blank rectangle, which would
		// be indistinguishable from a source that is not working.
		empty = 0;
		load(stream.fallback).then(put);
	}

	// How long each way through takes, from the lengths written into between.css: the turn is
	// two halves of six tenths, the page leaving is nine tenths. They are here so that the
	// frame left behind is taken away when it is done with.
	var TURN = 1200;
	var SLIDE = 900;

	// One screen gives way to the next, one of two ways picked at random: the page turns over
	// about one of its two axes, or it leaves to one side and the next one is standing under it.
	// Either way the new frame goes in underneath first, so there is never a moment with
	// nothing on the screen.
	function put(html)
	{
		var screen = document.getElementById('screen');
		var holder = document.createElement('div');
		holder.innerHTML = html;
		var frame = holder.firstElementChild;
		if (!frame)
		{
			setTimeout(next, stream.delay); // nothing came back that can be put up
			return;
		}

		// Whatever is already up - usually one frame, and two if a screen's turn came round
		// while the one before it was still going.
		var old = [];
		for (var i = 0; i < screen.children.length; ++i)
		{
			old.push(screen.children[i]);
		}

		screen.insertBefore(frame, old.length > 0 ? old[0] : null);
		dots(frame);
		shrink(frame);
		setTimeout(next, stream.delay);

		// The first frame of all has nothing to give way: it simply arrives. The class comes
		// off again once it has - left on, its animation would be the one still standing when
		// this frame is the one giving way, and the frame would sit there instead of leaving.
		if (old.length <= 0)
		{
			frame.classList.add('first');
			var drop = function() { frame.classList.remove('first'); };
			frame.addEventListener('animationend', drop);
			setTimeout(drop, 2000);
			return;
		}

		var turning = (Math.random() < 0.5);
		var length = turning ? TURN : SLIDE;
		if (turning)
		{
			turn(frame, old);
		}
		else
		{
			slide(old);
		}

		// What is left behind goes when the way through is done, and the new frame is put
		// straight whatever that way managed to do. A source that is not being drawn at all -
		// a scene nobody is on, a window put away - finishes nothing, and a clock on its own
		// would take the old frame away and leave the ground bare until the browser got round
		// to the new one. So the clock is the second answer, is given longer than the movement
		// itself, and sets the frame down where it belongs before it sweeps.
		//
		// The movement to wait for is the last one of the two: the half that brings the new
		// frame up when the page turns, and the old frame leaving when it slides. The other
		// one ends halfway through and would sweep too early.
		var swept = false;
		function sweep()
		{
			if (swept)
			{
				return;
			}
			swept = true;
			// Nothing of the way through is left on the frame. It is where it belongs now -
			// taking the class off puts it there even if the movement never ran - and it has
			// its own way out to come, which anything left behind here would stand in the way
			// of: a frame holding animation: none cannot leave at all.
			frame.classList.remove('turn-in');
			frame.style.removeProperty('animation');
			frame.style.removeProperty('transform');
			for (var i = 0; i < old.length; ++i)
			{
				if (old[i].parentNode)
				{
					old[i].parentNode.removeChild(old[i]);
				}
			}
		}

		// Only the frame's own movement ending counts. The event rises from whatever is inside
		// the frame as well, and a screen is free to have something of its own moving on it.
		var waiting = turning ? frame : old[old.length - 1];
		waiting.addEventListener('animationend', function(event)
		{
			if (event.target == waiting)
			{
				sweep();
			}
		});
		setTimeout(sweep, length + 1000);
	}

	// The page turning over: what is up turns away about an axis - upright or lying down,
	// either way round - and the next comes up out of the same turn. The diagonals were tried
	// and taken out again: a page folding about a corner reads as a mistake rather than as a
	// page being turned.
	function turn(frame, old)
	{
		var axes = [[0, 1], [1, 0]];
		var axis = axes[Math.floor(Math.random() * axes.length)];
		var way = (Math.random() < 0.5) ? 1 : -1;

		var all = old.concat([frame]);
		for (var i = 0; i < all.length; ++i)
		{
			all[i].style.setProperty('--ax', axis[0] * way);
			all[i].style.setProperty('--ay', axis[1] * way);
		}
		for (var j = 0; j < old.length; ++j)
		{
			old[j].classList.add('turn-out');
		}
		frame.classList.add('turn-in');
	}

	// The page leaving to one side, with the next one already standing under it. The new frame
	// does nothing at all here - it is simply uncovered.
	function slide(old)
	{
		var ways = [[-100, 0], [100, 0], [0, -100], [0, 100]];
		var way = ways[Math.floor(Math.random() * ways.length)];
		for (var i = 0; i < old.length; ++i)
		{
			old[i].style.setProperty('--tx', way[0] + '%');
			old[i].style.setProperty('--ty', way[1] + '%');
			old[i].classList.add('slide-out');
		}
	}

	// A name too long for its card is made smaller, a step at a time, and only cut off when
	// even the smallest size will not hold it. A nickname is what a player is known by on the
	// stream, and half of one says nothing.
	function shrink(frame)
	{
		var names = frame.getElementsByClassName('nick');
		for (var i = 0; i < names.length; ++i)
		{
			var el = names[i];
			el.style.fontSize = '';
			var size = parseFloat(getComputedStyle(el).fontSize);
			var least = 17 * (size / 25); // the designer's 25px down to 17px, in this frame's scale
			while (el.scrollWidth > el.clientWidth && size > least)
			{
				size -= 0.5;
				el.style.fontSize = size + 'px';
			}
		}
	}

	// Which screen of the round this is, at the foot of the frame. It belongs to the shell
	// rather than to any one screen: the rotation is what the site was asked for, and a screen
	// knows only itself. A round of one screen has nothing to say about where it is, so the
	// dots stay away.
	function dots(frame)
	{
		var count = (stream && stream.screens) ? stream.screens.length : 0;
		if (!frame || count < 2)
		{
			return;
		}

		var html = '';
		for (var i = 0; i < count; ++i)
		{
			html += '<i class="' + (i == at ? 'on' : '') + '"></i>';
		}
		var el = document.createElement('div');
		el.className = 'dots';
		el.innerHTML = html;
		frame.appendChild(el);
	}
}
