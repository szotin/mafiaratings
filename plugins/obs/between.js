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
	var timer;      // when the screen that is up gives way to the next one
	var walk;       // which way round the screens is the one in play - see begin()
	var onAir;      // the scene this source stands in is the one OBS is showing
	var onStage;    // the source itself is switched on in that scene
	var active;     // the two of them together: whether anybody can see this page
	var ready;      // who to tell when the screen the round begins with is up, if anybody

	this.start = function(pageOptions)
	{
		options = pageOptions;
		at = -1;
		empty = 0;
		timer = null;
		walk = 0;
		onAir = true;
		onStage = true;
		active = true;
		ready = null;

		// Nothing can be shown until we know what to show, so the first answer is waited for
		// rather than guessed at. After that it is asked for again on its own clock.
		settings().then(function() { next(); });
		setInterval(settings, options.settingsInterval);

		// What OBS says about this source: obsSourceActiveChanged when the scene it stands in
		// becomes, or stops being, the one on the air, and obsSourceVisibleChanged when the
		// source itself is switched on or off inside its scene. The two are different questions
		// and either can change on its own, so both are kept and the page is being watched only
		// when they agree. Outside OBS neither is ever sent and the page counts as watched.
		window.addEventListener('obsSourceActiveChanged', function(event)
		{
			onAir = (!event.detail || event.detail.active !== false);
			settle();
		});
		window.addEventListener('obsSourceVisibleChanged', function(event)
		{
			onStage = (!event.detail || event.detail.visible !== false);
			settle();
		});
	}

	// The same thing said from outside, by a page that has these screens in a frame of its own:
	// a browser source has OBS to tell it when it is being watched, and a frame has only the
	// page around it. See text_broadcast.php, which hides the frame while a game is on and
	// brings it back when the game is over.
	this.shown = function(on, done)
	{
		onAir = (on ? true : false);
		onStage = onAir;
		settle(done);
	}

	//-----------------------------------------------------------------------------------------
	// being watched, and not
	//-----------------------------------------------------------------------------------------
	// A source nobody is looking at does not walk its screens. It stands on the one the round
	// begins with and holds it, however long the scene stays off the air - OBS does not stop the
	// page, it only stops drawing it, and a page left to its own clock would be standing on
	// whichever screen that clock had reached by the time the scene came up. That is what made
	// the end of a game show a screen out of the middle of the round for a moment before the
	// first one took its place.
	//
	// So the round is begun the moment the source stands down, while nobody is watching and
	// there is nothing to see. When it comes up again the first screen is already there; it is
	// fetched afresh, because a game has been played since and it is the screen that tells how
	// it ended, and the new one takes the old one's place where it stands rather than coming up
	// out of a bare page. Then the clock starts: the first screen keeps its full time, and only
	// after it does the round go on.
	function settle(done)
	{
		var on = (onAir && onStage);
		if (typeof done == 'function')
		{
			ready = done;
		}
		else if (on === active)
		{
			return; // the two events of a scene change arrive together; one of them is enough
		}

		active = on;
		begin();
	}

	// The round from its beginning: the screen it begins with, fetched now. Whatever was on its
	// way is dropped rather than waited for - a screen fetched for the round that has just been
	// abandoned would arrive on top of the new one and set a second clock going beside the
	// first, and from then on the screens would turn over twice as fast.
	function begin()
	{
		at = -1;
		empty = 0;
		walk += 1;
		next();
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

		// Whether this page is still made of what the site is made of. OBS holds on to the page
		// a source first loaded, so a deployed script would otherwise never reach a source that
		// is already running - see stream_plugin_build() in include/stream.php, which is where
		// both numbers come from.
		if (settings.build && options.build && settings.build != options.build)
		{
			options.build = settings.build; // so that one change asks for one reload
			window.location.reload();
			return;
		}

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
	//
	// Every walk is numbered, and what comes back is put up only while the walk that asked for
	// it is still the one in play: the round can be begun again from under it - see begin().
	function next()
	{
		if (timer !== null)
		{
			clearTimeout(timer);
			timer = null;
		}

		if (!stream || !stream.screens || stream.screens.length <= 0)
		{
			// Nothing to show yet, watched or not: this clock is for finding out what to
			// show, not for turning screens over, so it runs either way.
			timer = setTimeout(next, 1000);
			return;
		}

		at += 1;
		if (at >= stream.screens.length)
		{
			at = 0;
		}

		var mine = walk;
		load(stream.screens[at]).then(function(html)
		{
			if (mine === walk)
			{
				show(html);
			}
		});
	}

	// When the screen that is up gives way to the next one. There is only ever the one clock:
	// a round begun again sets it afresh rather than running beside the one it found, and a
	// source that is standing down has none at all - see settle().
	function later(delay)
	{
		if (timer !== null)
		{
			clearTimeout(timer);
			timer = null;
		}
		if (active)
		{
			timer = setTimeout(next, delay);
		}
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
		var mine = walk;
		load(stream.fallback).then(function(html)
		{
			if (mine === walk)
			{
				put(html);
			}
		});
	}

	// How long a screen takes to fade off, as it is written into between.css. It is here so
	// that the frame left behind is taken away when it is done with - and, now that the two
	// never stand together, so that the next one knows when it may begin.
	var FADE = 900;

	// Somebody was waiting for the round to be standing on its first screen - see settle(). The
	// screen is in the page by now and drawn in the same breath as this, so the word goes out as
	// soon as it is there and is given only once.
	function up()
	{
		if (ready === null)
		{
			return;
		}
		var tell = ready;
		ready = null;
		tell();
	}

	// One screen gives way to the next by fading, one at a time: the frame that is up goes out
	// until it is not there, it is taken off the screen, and only then does the new one begin
	// to come up. The new frame is in the page the whole while - drawn, measured and held at
	// nothing - so that when its turn comes there is nothing left to do but show it.
	function put(html)
	{
		var screen = document.getElementById('screen');
		var holder = document.createElement('div');
		holder.innerHTML = html;
		var frame = holder.firstElementChild;
		if (!frame)
		{
			later(stream.delay); // nothing came back that can be put up
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
		later(stream.delay);
		up();

		// Coming up onto an empty screen. The class comes off again once it has - left on, its
		// animation would be the one still standing when this frame is the one going out, and
		// the frame would sit there at full strength instead of fading.
		function arrive()
		{
			frame.classList.remove('wait');
			frame.classList.add('enter');
			var drop = function() { frame.classList.remove('enter'); };
			frame.addEventListener('animationend', drop);
			setTimeout(drop, 2000);
		}

		// The first frame of all has nothing to wait for.
		if (old.length <= 0)
		{
			arrive();
			return;
		}

		// Held at nothing until the one before it has gone.
		frame.classList.add('wait');
		for (var j = 0; j < old.length; ++j)
		{
			old[j].classList.add('leave');
		}

		// What is left behind goes when the fade is done. A source that is not being drawn at
		// all - a scene nobody is on, a window put away - finishes nothing, so a clock is kept
		// as the second answer and is given longer than the fade itself.
		var swept = false;
		function sweep()
		{
			if (swept)
			{
				return;
			}
			swept = true;
			for (var i = 0; i < old.length; ++i)
			{
				if (old[i].parentNode)
				{
					old[i].parentNode.removeChild(old[i]);
				}
			}
			// The screen is empty now, which is what the new frame has been waiting for.
			arrive();
		}

		// Only the frame's own fade ending counts. The event rises from whatever is inside the
		// frame as well, and a screen is free to have something of its own moving on it.
		var waiting = old[old.length - 1];
		waiting.addEventListener('animationend', function(event)
		{
			if (event.target == waiting)
			{
				sweep();
			}
		});
		setTimeout(sweep, FADE + 1000);
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
