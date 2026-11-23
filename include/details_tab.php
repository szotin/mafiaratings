<?php

// The pages that create a thing, prepare it or manage it, drawn as one step-by-step card (the "wizard"
// of the v3 design): create.php, event_preparation.php, tournament_preparation.php,
// series_preparation.php, club_management.php, league_management.php, address_management.php.
//
// The card: the steps on top as numbered pills - those done with a green number, the current one dark,
// those not open yet grey - a bar of how far along, "Step 2 of 5"; the step itself; Back and Next on a
// bar at its foot. Beside it, the summary: what has been chosen so far.
//
// The first step, "Basics", is the fields of the thing itself, its name, its dates, where it is played.
// They are the ones of the dialog that used to create or edit it (form/*_create.php, form/*_edit.php),
// loaded into the step instead of a dialog: one set of fields for both, and the dialogs still work where
// a list opens them. The form's commit() - what the Ok of the dialog called - is called by Next instead.
// Nothing is created by opening the step: a thing that is being created is created by Next, and leaving
// the page without pressing it leaves nothing behind.

// The tabs of the fields of the thing itself of a preparation page, before all the others and
// numbered below 0, so that the numbers of the others stay what they were: a tournament stores the tab
// it was left on, and emails link to them by number. "Basics", and - for a tournament - its series.
define('PREP_TAB_DETAILS', -2);
define('PREP_TAB_SERIES', -1);

// The name of the first step.
function details_tab_name()
{
	return get_label('Basics');
}

// The card begins: the steps, and the box the current one goes into. $steps is an array of
// array(name, onclick) - onclick NULL for a step that cannot be opened yet; $cur is the index of the
// current one in it.
function wizard_begin($steps, $cur)
{
	$count = count($steps);
	echo '<div class="wz"><div class="wz-main"><nav class="wz-stepper" aria-label="' . get_label('Steps') . '"><ol>';
	foreach ($steps as $i => $step)
	{
		list($name, $onclick) = $step;
		$cls = $i == $cur ? ' class="cur"' : ($i < $cur ? ' class="done"' : '');
		$inner = '<span class="n">' . ($i + 1) . '</span><span class="t">' . $name . '</span>';
		if (is_null($onclick))
		{
			echo '<li' . $cls . '><span' . ($i == $cur ? ' aria-current="step"' : ' aria-disabled="true"') . '>' . $inner . '</span></li>';
		}
		else
		{
			// The current one is a button too, held down: a step shown without leaving the page
			// (see wizardStep()) lets it go.
			echo '<li' . $cls . '><button type="button" class="wz-step" onclick="' . $onclick . '"' . ($i == $cur ? ' aria-current="step" disabled' : '') . '>' . $inner . '</button></li>';
		}
	}
	echo '</ol><div class="wz-progress" aria-hidden="true"><i style="width: ' . round(($cur + 1) * 100 / max($count, 1)) . '%"></i></div>';
	echo '<p class="wz-count" data-text="' . htmlspecialchars(get_label('Step [0] of [1]', '{0}', '{1}'), ENT_QUOTES, 'UTF-8') . '">' . get_label('Step [0] of [1]', $cur + 1, $count) . '</p></nav>';
	echo '<div class="wz-form">';
}

// The card ends: the bar with Back at its left and Next at its right - either may be '' - and the
// summary beside the card.
function wizard_end($back, $next, $summary)
{
	echo '</div>';
	if ($back != '' || $next != '')
	{
		echo '<div class="wz-nav"><div>' . $back . '</div><div>' . $next . '</div></div>';
	}
	echo '</div><aside class="wz-side">' . $summary . '</aside></div>';
}

// The two buttons of the bar.
function wizard_back_button($onclick, $id = 'prep-back-btn')
{
	return '<button type="button" id="' . $id . '" class="wz-back" onclick="' . $onclick . '">' . PageBase::icon('chev-l') . '<span>' . get_label('Back') . '</span></button>';
}

// $text: Next, or Create / Save when it is the last step; $disabled: held back until something is done.
function wizard_next_button($text, $onclick, $id = 'prep-next-btn', $disabled = false)
{
	return '<button type="button" id="' . $id . '" class="wz-next" onclick="' . $onclick . '"' . ($disabled ? ' disabled' : '') . '><span>' . $text . '</span>' . PageBase::icon('chev-r') . '</button>';
}

// The summary of a step whose fields are the form of the first step: filled from the form as it is
// filled in (see show_details_form()). $kind is what is being made, shown on top; $logo, its picture
// beside it (see wizard_summary_head()).
function wizard_summary_live($kind, $logo = '')
{
	return '<section class="wz-sum">' . wizard_summary_head($kind, $logo) .
		'<div id="wz-sum" data-title="' . htmlspecialchars(details_tab_name(), ENT_QUOTES, 'UTF-8') . '"><p class="dim">' . get_label('Your choices appear here as you go.') . '</p></div></section>';
}

// The name of a month in a date: "октября", "October".
function wizard_month($month)
{
	global $_lang;
	return get_month_name($month, $_lang, false);
}

// The days of a thing in its summary, with the year: "21 ноября 2026", "7–8 ноября 2026",
// "30 октября – 2 ноября 2026"; "November 7–8, 2026". The same as formatPeriod() below, which writes
// them while the form is filled in.
function wizard_period($start, $duration, $timezone)
{
	global $_lang;

	date_default_timezone_set($timezone);
	$end = $start + max($duration, 1) - 1;
	list($sy, $sm, $sd) = explode('-', date('Y-n-j', $start));
	list($ey, $em, $ed) = explode('-', date('Y-n-j', $end));
	$day_first = ($_lang == LANG_RUSSIAN || $_lang == LANG_UKRAINIAN);
	$month = 'wizard_month';
	if ($sy != $ey)
	{
		return $day_first ?
			$sd . ' ' . $month($sm) . ' ' . $sy . ' – ' . $ed . ' ' . $month($em) . ' ' . $ey :
			$month($sm) . ' ' . $sd . ', ' . $sy . ' – ' . $month($em) . ' ' . $ed . ', ' . $ey;
	}
	if ($sm != $em)
	{
		return $day_first ?
			$sd . ' ' . $month($sm) . ' – ' . $ed . ' ' . $month($em) . ' ' . $sy :
			$month($sm) . ' ' . $sd . ' – ' . $month($em) . ' ' . $ed . ', ' . $sy;
	}
	if ($sd != $ed)
	{
		return $day_first ? $sd . '–' . $ed . ' ' . $month($sm) . ' ' . $sy : $month($sm) . ' ' . $sd . '–' . $ed . ', ' . $sy;
	}
	return $day_first ? $sd . ' ' . $month($sm) . ' ' . $sy : $month($sm) . ' ' . $sd . ', ' . $sy;
}

// Date for a thing of one day, Dates for one of more.
function wizard_period_label($start, $duration, $timezone)
{
	date_default_timezone_set($timezone);
	return date('Y-m-d', $start) == date('Y-m-d', $start + max($duration, 1) - 1) ? get_label('Date') : get_label('Dates');
}

// A thing in a summary with its picture - a series of a tournament: the picture, the name, and what
// follows it ($after - the stars).
function wizard_entity($pic, $name, $after = '')
{
	return '<span class="wz-ent">' . PageBase::capture(function() use ($pic) { $pic->show(ICONS_DIR, false, 22, 22); }) .
		'<span class="wz-ent-t">' . $name . ($after != '' ? ' <span class="stars">' . $after . '</span>' : '') . '</span></span>';
}

// The top of the summary: "Summary", what the thing is, and its logo at the right of the two - ''
// for none.
function wizard_summary_head($kind, $logo)
{
	$html = '<div class="wz-sum-hd"><div><h2>' . get_label('Summary') . '</h2><p class="wz-sum-type">' . $kind . '</p></div>';
	if ($logo != '')
	{
		$html .= '<div class="wz-sum-logo">' . $logo . '</div>';
	}
	return $html . '</div>';
}

// The summary of a thing that is there already: $groups is an array of title => array of
// array(label, value); the empty ones are left out. A row with no label - a series of a tournament
// in the group of the series - takes the whole width.
function wizard_summary($kind, $groups, $logo = '')
{
	$html = '<section class="wz-sum">' . wizard_summary_head($kind, $logo);
	foreach ($groups as $title => $rows)
	{
		$dl = '';
		foreach ($rows as $row)
		{
			list($label, $value) = $row;
			if (!is_null($value) && $value !== '')
			{
				$dl .= $label === '' ? '<div class="wide"><dd>' . $value . '</dd></div>' : '<div><dt>' . $label . '</dt><dd>' . $value . '</dd></div>';
			}
		}
		if ($dl != '')
		{
			$html .= '<h3>' . $title . '</h3><dl>' . $dl . '</dl>';
		}
	}
	return $html . '</section>';
}

// The place the form of the first step goes into, and the script that loads it there. $form_url is
// relative to the root of the site, the way dlg.form() takes it. The live summary, if the page has one,
// follows the form: a row of the form - a label, then its fields - is a row of the summary.
//
// A form too long for one step is shown a part at a time: $only is a selector of something in the rows
// to show - only they are; $without, of something in the rows to leave out (see detailsRows()). The rows
// left out are there all the same, hidden, and commit() sends them with the rest.
function show_details_form($form_url, $only = NULL, $without = NULL)
{
	echo '<div id="details-form" class="details-form"></div>';
?>
<script>
var detailsLoaded = false;
$(function()
{
	html.get(<?php echo json_encode($form_url); ?>, function(text)
	{
		// The title is the dialog's; on a page the band says it.
		$('#details-form').html(text.replace(/<title=[^>]*>/, ''));
		detailsLoaded = true;
		detailsRows(<?php echo json_encode($only); ?>, <?php echo json_encode($without); ?>);
		detailsSummary();
		$('#details-form').on('input change', detailsSummary);
		// Some of a form comes later, by a request of its own - the series of a tournament: the
		// summary is read again when it arrives.
		if (window.MutationObserver)
		{
			new MutationObserver(detailsSummary).observe(document.getElementById('details-form'), { childList: true, subtree: true });
		}
	});
});

// Next: the form's own commit(), as the Ok of its dialog called it.
function detailsCommit(onSuccess)
{
	if (detailsLoaded)
	{
		commit(onSuccess);
	}
}

// Which rows of the form show: with $only, only those holding what it selects; with $without, all but
// those; with neither, all.
function detailsRows(only, without)
{
	var rows = $('#details-form table.dialog_form').first().children('tbody').children('tr');
	// A part that is a step of its own is named by the step: the labels of its rows are not shown.
	rows.children('td:first-child').toggleClass('wz-lab-off', !!only);
	rows.each(function()
	{
		var row = $(this), show = true;
		if (only)
		{
			show = row.find(only).length > 0;
		}
		else if (without)
		{
			show = row.find(without).length == 0;
		}
		row.toggle(show).toggleClass('wz-off', !show);
	});
	detailsSummary();
}

// The current step changed without leaving the page - a form shown a part at a time.
function wizardStep(cur)
{
	var items = $('.wz-stepper li');
	items.each(function(i)
	{
		$(this).toggleClass('cur', i == cur).toggleClass('done', i < cur);
		$(this).children().attr('aria-current', i == cur ? 'step' : null);
		$(this).children('button').prop('disabled', i == cur);
	});
	$('.wz-progress i').css('width', Math.round((cur + 1) * 100 / items.length) + '%');
	var count = $('.wz-count');
	count.text(count.data('text').replace('{0}', cur + 1).replace('{1}', items.length));
}

// An amount as its currency writes it: the pattern has a # for the number ("$#", "# грн"); ## is a
// # of its own. Nothing is free but 0. The same as format_currency() in include/currency.php.
function formatFee(amount, pattern)
{
	var n = parseInt(amount, 10);
	if (isNaN(n))
	{
		return amount;
	}
	if (n == 0)
	{
		return <?php echo json_encode(get_label('free')); ?>;
	}
	var num = n.toLocaleString('en-US');
	if (!pattern)
	{
		return num;
	}
	pattern = String(pattern);
	for (var i = 0; i < pattern.length; ++i)
	{
		if (pattern.charAt(i) == '#')
		{
			if (pattern.charAt(i + 1) == '#')
			{
				++i;
				continue;
			}
			return pattern.substr(0, i) + num + pattern.substr(i + 1);
		}
	}
	return num;
}

// The days from one date to another ("2026-11-21") as wizard_period() writes them: "7–8 ноября 2026",
// "November 7–8, 2026".
function formatPeriod(from, to)
{
<?php
	global $_lang;
	$months = array();
	for ($m = 1; $m <= 12; ++$m)
	{
		$months[] = wizard_month($m);
	}
?>
	var months = <?php echo json_encode($months, JSON_UNESCAPED_UNICODE); ?>;
	var dayFirst = <?php echo ($_lang == LANG_RUSSIAN || $_lang == LANG_UKRAINIAN) ? 'true' : 'false'; ?>;
	function parse(s)
	{
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
		return m ? { y: +m[1], m: +m[2], d: +m[3] } : null;
	}
	var s = parse(from), e = parse(to);
	if (!s)
	{
		return '';
	}
	if (!e || (e.y < s.y || (e.y == s.y && (e.m < s.m || (e.m == s.m && e.d < s.d)))))
	{
		e = s;
	}
	var sm = months[s.m - 1], em = months[e.m - 1];
	if (s.y != e.y)
	{
		return dayFirst ?
			s.d + ' ' + sm + ' ' + s.y + ' – ' + e.d + ' ' + em + ' ' + e.y :
			sm + ' ' + s.d + ', ' + s.y + ' – ' + em + ' ' + e.d + ', ' + e.y;
	}
	if (s.m != e.m)
	{
		return dayFirst ?
			s.d + ' ' + sm + ' – ' + e.d + ' ' + em + ' ' + s.y :
			sm + ' ' + s.d + ' – ' + em + ' ' + e.d + ', ' + s.y;
	}
	if (s.d != e.d)
	{
		return dayFirst ? s.d + '–' + e.d + ' ' + sm + ' ' + s.y : sm + ' ' + s.d + '–' + e.d + ', ' + s.y;
	}
	return dayFirst ? s.d + ' ' + sm + ' ' + s.y : sm + ' ' + s.d + ', ' + s.y;
}

function detailsSummary()
{
	var box = $('#wz-sum');
	if (box.length == 0)
	{
		return;
	}
	var table = $('#details-form table.dialog_form').first();
	function esc(s) { return $('<div>').text(s).html(); }
	function words(el) { return $.trim($(el).text().replace(/\s+/g, ' ')); }
	// The label of a row of dates, set by values(): Date for one day, Dates for more.
	var dateLabel = null;
	// The words of a checkbox: the text after it, or - in a table of its own, the series of a
	// tournament - the first cell of its row.
	function boxWords(cb)
	{
		var inner = $(cb).closest('table');
		if (inner.length && inner[0] !== table[0])
		{
			var first = words($(cb).closest('tr').children('td').first());
			if (first != '')
			{
				return first;
			}
		}
		var t = '', n = cb.nextSibling;
		while (n && !(n.nodeType == 1 && /^(BR|INPUT|SELECT|TABLE|DIV)$/.test(n.tagName)))
		{
			t += n.textContent || '';
			n = n.nextSibling;
		}
		return $.trim(t.replace(/\s+/g, ' '));
	}
	function values(cell)
	{
		var out = [];
		// An admission rate is written the way its currency writes it - "$30"; an unknown one is not
		// written at all.
		var fee = $(cell).find('#form-fee');
		if (fee.length > 0)
		{
			var amount = $.trim(fee.val());
			if (amount == '' || $('#form-fee-unknown').prop('checked'))
			{
				return [];
			}
			return [formatFee(amount, $(cell).find('#form-currency option:selected').data('pattern'))];
		}
		// A name with its translations (see NameControl in js/src/mr.js) is said once: in the language
		// of the reader if it has one, otherwise the name every language has.
		var nameDiv = $(cell).find('[id$="-div"]').filter(function() { return $(this).find('#' + this.id.slice(0, -4)).length > 0; }).first();
		if (nameDiv.length > 0)
		{
			var base = nameDiv[0].id.slice(0, -4);
			var own = $.trim(nameDiv.find('#' + base + '-<?php global $_lang; echo get_lang_code($_lang); ?>').val() || '');
			var name = own != '' ? own : $.trim(nameDiv.find('#' + base).val() || '');
			// The name is always there: a dash until it is written.
			return name == '' ? [{ html: '<span class="dim">—</span>' }] : [name];
		}
		// Dates: one if they are the same, from - to if not.
		var dates = $(cell).find('input[type=date]').filter(':visible');
		var datesDone = false;
		$(cell).find('input, select, textarea').each(function()
		{
			if (this.type == 'hidden' || this.type == 'file' || this.type == 'button' || !$(this).is(':visible'))
			{
				return;
			}
			if (this.type == 'date')
			{
				if (!datesDone)
				{
					datesDone = true;
					var p = formatPeriod(dates.first().val(), dates.last().val());
					// One day is a date, not dates.
					var last = dates.last().val();
					dateLabel = (dates.length < 2 || last == '' || last <= dates.first().val()) ?
						<?php echo json_encode(get_label('Date')); ?> : <?php echo json_encode(get_label('Dates')); ?>;
					if (p != '')
					{
						out.push(p);
					}
				}
				return;
			}
			// A row of a table of its own with a checkbox - a series of a tournament - is one thing
			// when it is ticked: its picture and name from the first cell, its stars and its stage.
			// Its other fields say nothing by themselves.
			var inner = $(this).closest('table');
			if (inner.length && inner[0] !== table[0])
			{
				if (this.type == 'checkbox' && this.checked)
				{
					var tr = $(this).closest('tr');
					var first = tr.children('td').first();
					var img = first.find('img').first();
					var after = '';
					var id = this.id.substr(this.id.lastIndexOf('-') + 1);
					var stars = (window.seriesList && seriesList[id]) ? Math.floor(seriesList[id].stars) : 0;
					for (var i = 0; i < stars && i < 10; ++i)
					{
						after += '★';
					}
					var stage = tr.find('select option:selected');
					out.push({ html: '<span class="wz-ent">' + '<span>' + (img.length ? '<img src="' + esc(img.attr('src')) + '">' : '') + '</span>' +
						'<span class="wz-ent-t">' + esc(words(first)) + (after != '' ? ' <span class="stars">' + after + '</span>' : '') +
						(stage.length ? ' <span class="dim">' + esc($.trim(stage.text())) + '</span>' : '') + '</span></span>' });
				}
				return;
			}
			if (this.type == 'checkbox' || this.type == 'radio')
			{
				if (this.checked)
				{
					var w = boxWords(this);
					if (w != '')
					{
						out.push(w);
					}
				}
			}
			else if (this.tagName == 'SELECT')
			{
				var o = this.options[this.selectedIndex];
				if (o && $.trim(o.text) != '')
				{
					out.push($.trim(o.text));
				}
			}
			else if ($.trim(this.value) != '')
			{
				var v = $.trim(this.value);
				out.push(v.length > 80 ? v.substr(0, 80) + '…' : v);
			}
		});
		return out;
	}
	// The rows of the other part of the form count too: shown for the moment it takes to read them.
	var off = table.children('tbody').children('tr.wz-off').show();
	var rows = [];
	table.children('tbody').children('tr').each(function()
	{
		var cells = $(this).children('td');
		if (cells.length >= 2)
		{
			dateLabel = null;
			var v = values(cells[1]);
			var label = dateLabel || words(cells[0]).replace(/:$/, '');
			// The languages a thing is played in are its language.
			if (label == <?php echo json_encode(get_label('Languages')); ?>)
			{
				label = <?php echo json_encode(get_label('Language')); ?>;
			}
			if (label != '' && v.length > 0)
			{
				// Things with their pictures go a line each; words, one after another.
				var many = typeof v[0] == 'object';
				rows.push([label, $.map(v, function(x) { return typeof x == 'object' ? x.html : esc(x); }).join(many ? '<br>' : ', ')]);
			}
		}
		else if (cells.length == 1)
		{
			// A row of options under the fields: each one ticked is a line of its own.
			$(cells[0]).find('input[type=checkbox]:checked').each(function()
			{
				var w = boxWords(this);
				if (w != '')
				{
					rows.push(['✓', esc(w)]);
				}
			});
		}
	});
	off.hide();
	if (rows.length == 0)
	{
		return;
	}
	var html = '<h3>' + esc(box.data('title')) + '</h3><dl>';
	for (var i = 0; i < rows.length; ++i)
	{
		html += '<div><dt>' + esc(rows[i][0]) + '</dt><dd>' + rows[i][1] + '</dd></div>';
	}
	box.html(html + '</dl>');
}
</script>
<?php
}

?>
