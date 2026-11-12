// A row of tabs stays one row. What is shown is an unbroken run of the tabs in their own order,
// always with the open one in it. The tabs before the run go into a menu opened from a button at
// the start of the row, the tabs after it into one at the end, and they come back out as soon as
// there is room again.
//
// It works on every <div class="tab"> of the page, as they stand when the page is loaded. Each
// tab in a menu is the tab itself, pressed for you: whatever it does on the row, it does from
// the menu too. Plain JavaScript, loaded as it is - it is not compiled with the rest of js/, and
// it does not need jQuery.
(function()
{
	var openMenu = null;
	var openButton = null;

	function closeMenu()
	{
		if (openMenu)
		{
			openMenu.parentNode.removeChild(openMenu);
			openMenu = null;
			openButton = null;
		}
	}

	function tabsOf(row)
	{
		var tabs = [];
		for (var i = 0; i < row.children.length; ++i)
		{
			var c = row.children[i];
			if (c.tagName == 'BUTTON' && !c.classList.contains('tab-more') && !c.classList.contains('tab-less'))
			{
				tabs.push(c);
			}
		}
		return tabs;
	}

	// The tabs a button holds: those before the shown run for the one at the start, those after
	// it for the one at the end.
	function showMenu(row, button, before)
	{
		closeMenu();
		var menu = document.createElement('div');
		menu.className = 'tab-menu';
		var tabs = tabsOf(row);
		var shown = false;
		tabs.forEach(function(tab)
		{
			if (!tab.classList.contains('tab-away'))
			{
				shown = true;
				return;
			}
			if (shown == before)
			{
				return;
			}
			var item = document.createElement('div');
			item.className = 'tab-menu-item' + (tab.disabled ? ' disabled' : '');
			item.innerHTML = tab.innerHTML;
			if (!tab.disabled)
			{
				item.onclick = function()
				{
					closeMenu();
					tab.click();
				};
			}
			menu.appendChild(item);
		});
		document.body.appendChild(menu);

		// Under the button: from its left edge for the start, to its right edge for the end, and
		// kept inside the window either way.
		var r = button.getBoundingClientRect();
		var left = before ? r.left : r.right - menu.offsetWidth;
		var maxLeft = document.documentElement.clientWidth - menu.offsetWidth;
		left = Math.max(0, Math.min(left, maxLeft));
		menu.style.left = (left + window.pageXOffset) + 'px';
		menu.style.top = (r.bottom + window.pageYOffset) + 'px';
		openMenu = menu;
		openButton = button;
	}

	function layout(row)
	{
		var less = row.querySelector('.tab-less');
		var more = row.querySelector('.tab-more');
		var tabs = tabsOf(row);
		tabs.forEach(function(tab) { tab.classList.remove('tab-away'); });
		less.style.display = 'none';
		more.style.display = 'none';
		if (tabs.length == 0 || row.scrollWidth <= row.clientWidth)
		{
			return;
		}

		// The buttons are measured shown; both are the same kind of button.
		less.style.display = '';
		more.style.display = '';
		var lessWidth = less.offsetWidth;
		var moreWidth = more.offsetWidth;
		var room = row.clientWidth;

		var widths = tabs.map(function(tab) { return tab.offsetWidth; });
		var active = 0;
		tabs.forEach(function(tab, i)
		{
			if (tab.classList.contains('active'))
			{
				active = i;
			}
		});
		var last = tabs.length - 1;

		function sum(from, to)
		{
			var s = 0;
			for (var i = from; i <= to; ++i)
			{
				s += widths[i];
			}
			return s;
		}

		function fits(from, to)
		{
			var w = sum(from, to);
			if (from > 0)
			{
				w += lessWidth;
			}
			if (to < last)
			{
				w += moreWidth;
			}
			return w <= room;
		}

		// The run starts as early as it can while still reaching the open tab, and then goes on
		// to the right as far as there is room.
		var start = 0;
		while (start < active && !fits(start, active))
		{
			++start;
		}
		var end = active;
		while (end < last && fits(start, end + 1))
		{
			++end;
		}

		tabs.forEach(function(tab, i)
		{
			if (i < start || i > end)
			{
				tab.classList.add('tab-away');
			}
		});
		less.style.display = start > 0 ? '' : 'none';
		more.style.display = end < last ? '' : 'none';
	}

	function makeButton(row, className, text, before)
	{
		var button = document.createElement('button');
		button.className = className;
		button.type = 'button';
		button.innerHTML = text;
		button.onclick = function(e)
		{
			e.stopPropagation();
			if (openButton === button)
			{
				closeMenu();
			}
			else
			{
				showMenu(row, button, before);
			}
		};
		return button;
	}

	function init()
	{
		var rows = document.querySelectorAll('div.tab');
		for (var i = 0; i < rows.length; ++i)
		{
			(function(row)
			{
				row.insertBefore(makeButton(row, 'tab-less', '&laquo;', true), row.firstChild);
				row.appendChild(makeButton(row, 'tab-more', '&raquo;', false));
				layout(row);
				window.addEventListener('resize', function()
				{
					closeMenu();
					layout(row);
				});
			})(rows[i]);
		}
		document.addEventListener('click', closeMenu);
	}

	if (document.readyState == 'loading')
	{
		document.addEventListener('DOMContentLoaded', init);
	}
	else
	{
		init();
	}
})();
