//  Shows one screen at a time between games on a page used as a browser source in OBS Studio.
//  The page skeleton is created by between.php. Each screen is a page of its own that reads
//  straight from the database and prints itself; this script fetches them in turn.

var obs = new function()
{
	var options; // the options passed by between.php
	var at;      // which screen of the round we are on
	var empty;   // how many screens in a row had nothing, so as not to spin forever

	this.start = function(pageOptions)
	{
		options = pageOptions;
		at = -1;
		empty = 0;
		next();
	}

	// Walks the screens in order, over and over. A screen is fetched afresh every time its turn
	// comes round, so a game written down between two showings is there the next time.
	function next()
	{
		at += 1;
		if (at >= options.screens.length)
		{
			at = 0;
		}
		load(options.screens[at]).then(show);
	}

	function load(page)
	{
		return fetch(page + '?' + new URLSearchParams(options.params)).
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
		if (empty < options.screens.length)
		{
			next();
			return;
		}

		// None of them had anything: before the first game of a round with no seating, after the
		// last game of a tournament. Put up the title rather than a blank rectangle, which would
		// be indistinguishable from a source that is not working.
		empty = 0;
		load(options.fallback).then(put);
	}

	function put(html)
	{
		document.getElementById('screen').innerHTML = html;
		setTimeout(next, options.delay);
	}
}
