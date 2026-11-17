// The header of every page: the dropdowns of the menu (and of the tabs of a club, an event, a
// tournament...), the user's menu and the login panel, and the drawer that holds the menu on a
// narrow screen. Plain javascript, loaded as it is - not compiled with the rest of js/.
(function()
{
	var open = null; // the .nav-more whose menu is open

	function setOpen(more, state)
	{
		var button = more.querySelector(':scope > button');
		var menu = more.querySelector(':scope > .menu');
		if (!button || !menu)
		{
			return;
		}
		button.setAttribute('aria-expanded', state ? 'true' : 'false');
		menu.hidden = !state;
		if (state)
		{
			// a menu that would run off the right edge of the window opens to the left
			menu.classList.remove('menu-flip');
			var r = menu.getBoundingClientRect();
			if (r.right > document.documentElement.clientWidth - 8)
			{
				menu.classList.add('menu-flip');
			}
			var input = menu.querySelector('input');
			if (input)
			{
				input.focus();
			}
		}
		open = state ? more : null;
	}

	document.addEventListener('click', function(e)
	{
		var button = e.target.closest('.nav-more > button');
		if (button)
		{
			var more = button.parentNode;
			var wasOpen = (open === more);
			if (open)
			{
				setOpen(open, false);
			}
			if (!wasOpen)
			{
				setOpen(more, true);
			}
			e.preventDefault();
			return;
		}
		if (open && !open.contains(e.target))
		{
			setOpen(open, false);
		}
	});

	var drawer = document.getElementById('drawer');
	var burger = document.querySelector('.burger');

	function setDrawer(state)
	{
		if (!drawer)
		{
			return;
		}
		if (state)
		{
			drawer.hidden = false;
			// the next frame, so that the panel slides in rather than appearing
			requestAnimationFrame(function() { drawer.classList.add('is-open'); });
			document.body.classList.add('is-locked');
			var panel = drawer.querySelector('.drawer-panel');
			if (panel)
			{
				panel.focus();
			}
		}
		else
		{
			drawer.classList.remove('is-open');
			document.body.classList.remove('is-locked');
			setTimeout(function() { drawer.hidden = true; }, 220);
		}
		if (burger)
		{
			burger.setAttribute('aria-expanded', state ? 'true' : 'false');
		}
	}

	if (burger)
	{
		burger.addEventListener('click', function() { setDrawer(true); });
	}
	if (drawer)
	{
		drawer.addEventListener('click', function(e)
		{
			if (e.target.closest('[data-close]'))
			{
				setDrawer(false);
			}
		});
	}

	document.addEventListener('keydown', function(e)
	{
		if (e.key != 'Escape')
		{
			return;
		}
		if (open)
		{
			var button = open.querySelector(':scope > button');
			setOpen(open, false);
			if (button)
			{
				button.focus();
			}
		}
		else if (drawer && !drawer.hidden)
		{
			setDrawer(false);
		}
	});
})();
