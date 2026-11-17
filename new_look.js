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

// The search of the header: Ctrl+K (or Cmd+K), "/" or the button open a dialog; what is typed in it
// goes to api/control/search.php, and the players, clubs, tournaments, leagues and series it returns
// are listed under it. The arrows choose, Enter opens, Escape closes.
(function()
{
	var dlg = document.getElementById('search');
	if (!dlg)
	{
		return;
	}
	var input = dlg.querySelector('input');
	var list = dlg.querySelector('.search-results');
	var words = {};
	try
	{
		words = JSON.parse(dlg.getAttribute('data-words')) || {};
	}
	catch (e)
	{
	}
	var ORDER = ['player', 'club', 'tournament', 'league', 'series'];
	var opener = null;  // what had the focus before the dialog opened
	var timer = null;
	var request = 0;    // the number of the latest request; the answers to older ones are dropped
	var items = [];     // the links in the list, in order
	var current = -1;

	// The names come from the database as html - "&amp;" and the like - and go into the list as text.
	var decoder = document.createElement('textarea');
	function decode(html)
	{
		decoder.innerHTML = html == null ? '' : html;
		return decoder.value;
	}

	function initials(name)
	{
		var w = name.split(/[\s\-_.]+/).filter(function(s) { return s.length > 0; });
		var r = w.length >= 2 ? w[0].charAt(0) + w[1].charAt(0) : name.substr(0, 2);
		return r.toUpperCase();
	}

	function el(tag, cls, text)
	{
		var e = document.createElement(tag);
		if (cls)
		{
			e.className = cls;
		}
		if (text != null)
		{
			e.textContent = text;
		}
		return e;
	}

	function select(i)
	{
		if (current >= 0 && items[current])
		{
			items[current].setAttribute('aria-selected', 'false');
		}
		current = i;
		if (current >= 0 && items[current])
		{
			items[current].setAttribute('aria-selected', 'true');
			input.setAttribute('aria-activedescendant', items[current].id);
			items[current].scrollIntoView({ block: 'nearest' });
		}
		else
		{
			input.removeAttribute('aria-activedescendant');
		}
	}

	function message(text)
	{
		list.innerHTML = '';
		items = [];
		current = -1;
		input.setAttribute('aria-expanded', 'false');
		if (text)
		{
			list.appendChild(el('li', 'search-empty', text));
		}
	}

	function show(results)
	{
		list.innerHTML = '';
		items = [];
		current = -1;
		if (!results || results.length == 0)
		{
			message(words.none);
			return;
		}
		ORDER.forEach(function(type)
		{
			var group = results.filter(function(r) { return r.type == type; });
			if (group.length == 0)
			{
				return;
			}
			list.appendChild(el('li', 'search-group', words[type] || type)).setAttribute('role', 'presentation');
			group.forEach(function(r)
			{
				var name = decode(r.name);
				var li = el('li');
				li.setAttribute('role', 'presentation');
				var a = el('a');
				a.href = r.url;
				a.id = 'search-r' + items.length;
				a.setAttribute('role', 'option');
				a.setAttribute('aria-selected', 'false');
				var pic = el('span', 'search-pic' + (type == 'player' ? ' round' : ''));
				if (r.pic)
				{
					var img = el('img');
					img.src = r.pic;
					img.alt = '';
					img.loading = 'lazy';
					pic.appendChild(img);
				}
				else
				{
					pic.textContent = initials(name);
					pic.classList.add('is-empty');
				}
				a.appendChild(pic);
				var txt = el('span', 'search-txt');
				txt.appendChild(el('span', 'search-name', name));
				if (r.sub)
				{
					txt.appendChild(el('span', 's', decode(r.sub)));
				}
				a.appendChild(txt);
				li.appendChild(a);
				list.appendChild(li);
				items.push(a);
			});
		});
		input.setAttribute('aria-expanded', 'true');
		select(0);
	}

	function search()
	{
		var term = input.value.trim();
		if (term.length < 2)
		{
			message(term.length == 0 ? '' : words['short']);
			return;
		}
		var n = ++request;
		dlg.classList.add('is-busy');
		fetch('api/control/search.php?term=' + encodeURIComponent(term), { credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(data)
			{
				if (n != request)
				{
					return;
				}
				dlg.classList.remove('is-busy');
				if (data.error)
				{
					message(words.error);
					return;
				}
				show(data.results);
			})
			.catch(function()
			{
				if (n == request)
				{
					dlg.classList.remove('is-busy');
					message(words.error);
				}
			});
	}

	function setSearch(state)
	{
		if (state)
		{
			if (!dlg.hidden)
			{
				return;
			}
			opener = document.activeElement;
			dlg.hidden = false;
			document.body.classList.add('is-locked');
			input.focus();
			input.select();
		}
		else
		{
			dlg.hidden = true;
			document.body.classList.remove('is-locked');
			if (opener && opener.focus)
			{
				opener.focus();
			}
		}
	}

	document.addEventListener('click', function(e)
	{
		if (e.target.closest('[data-open-search]'))
		{
			e.preventDefault();
			setSearch(true);
		}
	});
	dlg.addEventListener('click', function(e)
	{
		if (e.target.closest('[data-close]'))
		{
			setSearch(false);
		}
	});
	dlg.addEventListener('mousemove', function(e)
	{
		var a = e.target.closest('.search-results a');
		if (a)
		{
			var i = items.indexOf(a);
			if (i >= 0 && i != current)
			{
				select(i);
			}
		}
	});

	input.addEventListener('input', function()
	{
		clearTimeout(timer);
		timer = setTimeout(search, 180);
	});

	input.addEventListener('keydown', function(e)
	{
		if (e.key == 'ArrowDown' || e.key == 'ArrowUp')
		{
			if (items.length > 0)
			{
				var step = e.key == 'ArrowDown' ? 1 : -1;
				select((current + step + items.length) % items.length);
			}
			e.preventDefault();
		}
		else if (e.key == 'Enter')
		{
			if (current >= 0 && items[current])
			{
				window.location.href = items[current].href;
			}
			e.preventDefault();
		}
		else if (e.key == 'Tab')
		{
			// the focus stays in the dialog
			e.preventDefault();
		}
	});

	document.addEventListener('keydown', function(e)
	{
		if (!dlg.hidden)
		{
			if (e.key == 'Escape')
			{
				e.preventDefault();
				e.stopPropagation();
				setSearch(false);
			}
			return;
		}
		var t = e.target;
		var typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
		if ((e.key == 'k' || e.key == 'K') && (e.ctrlKey || e.metaKey) && !e.altKey)
		{
			e.preventDefault();
			setSearch(true);
		}
		else if (e.key == '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey && !document.querySelector('.ui-dialog:not([style*="display: none"])'))
		{
			e.preventDefault();
			setSearch(true);
		}
	}, true);
})();
