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
	var walk;       // which way round the screens is the one in play - see restart()
	var restarted;  // when the round was last begun again, so that it is not begun twice over
	var ready;      // who to tell when the screen the round begins with is up, if anybody

	this.start = function(pageOptions)
	{
		options = pageOptions;
		at = -1;
		empty = 0;
		timer = null;
		walk = 0;
		restarted = 0;
		ready = null;

		// Nothing can be shown until we know what to show, so the first answer is waited for
		// rather than guessed at. After that it is asked for again on its own clock.
		settings().then(function() { next(); });
		setInterval(settings, options.settingsInterval);

		// OBS says when this source starts being shown: obsSourceActiveChanged when the scene it
		// stands in becomes the one on the air, obsSourceVisibleChanged when the source itself is
		// switched on in a scene already showing. Either way the round begins again - see
		// restart(). Outside OBS neither of them is ever sent.
		window.addEventListener('obsSourceActiveChanged', function(event)
		{
			if (!event.detail || event.detail.active)
			{
				restart();
			}
		});
		window.addEventListener('obsSourceVisibleChanged', function(event)
		{
			if (!event.detail || event.detail.visible)
			{
				restart();
			}
		});
	}

	// The same thing asked for from outside, by a page that has these screens in a frame of its
	// own: a browser source has OBS to tell it when it starts being shown, and a frame has only
	// the page around it. See text_broadcast.php, which brings the screens up the moment a game
	// is over - and which waits for the word given back here before it shows the frame at all,
	// so that the first screen is what comes up and not what was standing there before.
	this.restart = function(done)
	{
		restart(done);
	}

	// Back to the first screen. A source nobody is looking at goes on turning its screens over
	// all the while - OBS does not stop the page, it only stops drawing it - so by the time the
	// scene comes up it would be standing on whichever screen its clock had reached, halfway
	// through a round and halfway through a screen's time. So it starts afresh: the table is
	// shown what the round begins with, for as long as a screen is meant to stand.
	//
	// What was up is left standing until the first screen of the round has been fetched, and then
	// gives way to it in the ordinary manner - the new frame goes in underneath and the one on
	// top fades off it, see put(). It belongs to a round that is over, which is why it used to be
	// taken down the moment the word came; but inside OBS the source is on the air by then, and
	// a screen cannot be up before it has been fetched, so that left the page bare for as long as
	// the fetch took and the first screen then eased in out of nothing over another second and a
	// half. On a stream that reads as the picture going black - it is the page's own dark ground,
	// #0A0608, and a recording of one tournament was measured doing it five times. A screen of
	// this table whose turn had passed, fading away as the one that belongs here comes up under
	// it, is the lesser of the two by a long way.
	//
	// A caller that asks to be told when the first screen is up keeps its frame out of sight until
	// the word comes back - see text_broadcast.php - so there nothing is on the screen to protect
	// and what was standing there is dropped at once.
	//
	// Whatever was on its way is dropped rather than waited for: a screen fetched for the round
	// that has just been abandoned would arrive on top of the new one and set a second clock
	// going beside the first, and from then on the screens would turn over twice as fast.
	//
	// The two things OBS says - the scene becoming active and the source becoming visible -
	// arrive together when a scene is switched to, and either of them alone is reason enough,
	// so the second one to arrive is passed over.
	function restart(done)
	{
		var now = Date.now();
		if (now - restarted < 1000)
		{
			return;
		}
		restarted = now;
		at = -1;
		empty = 0;
		walk += 1;
		ready = (typeof done == 'function') ? done : null;

		if (ready !== null)
		{
			var screen = document.getElementById('screen');
			while (screen.firstChild)
			{
				screen.removeChild(screen.firstChild);
			}
		}

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
	// it is still the one in play: the round can be begun again from under it - see restart().
	function next()
	{
		if (timer !== null)
		{
			clearTimeout(timer);
			timer = null;
		}

		if (!stream || !stream.screens || stream.screens.length <= 0)
		{
			later(1000); // nothing to show yet; ask again shortly
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
	// a round begun again sets it afresh rather than running beside the one it found.
	function later(delay)
	{
		if (timer !== null)
		{
			clearTimeout(timer);
		}
		timer = setTimeout(next, delay);
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

	// How long one screen takes to give way to the next, as it is written into between.css.
	// It is here so that the frame left behind is taken away when it is done with.
	var FADE = 1800;

	// Somebody was waiting for the round to be standing on its first screen again - see
	// restart(). The screen is in the page by now and drawn in the same breath as this, so the
	// word goes out as soon as it is there and is given only once.
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

	// One screen gives way to the next by fading: the new frame goes in underneath, whole and
	// still, and the one on top goes slowly out until it is not there. So there is never a
	// moment with nothing on the screen, and nothing moves but the light.
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

		// The first frame of all has nothing to give way: it comes up out of the ground. The
		// class comes off again once it has - left on, its animation would be the one still
		// standing when this frame is the one going out, and the frame would sit there at full
		// strength instead of fading.
		if (old.length <= 0)
		{
			frame.classList.add('first');
			var drop = function() { frame.classList.remove('first'); };
			frame.addEventListener('animationend', drop);
			setTimeout(drop, 2000);
			return;
		}

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
