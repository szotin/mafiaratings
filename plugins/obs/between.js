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

	function put(html)
	{
		document.getElementById('screen').innerHTML = html;
		setTimeout(next, stream.delay);
	}
}
