<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timespan.php';
require_once '../include/datetime.php';
require_once '../include/security.php';
require_once '../include/picture.php';
require_once '../include/currency.php';
require_once '../include/series.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('sеriеs')));
	
	// A series belongs either to a league or to a club. Everything below is the same for both -
	// only the owner the defaults come from differs.
	$league_id = isset($_REQUEST['league_id']) ? (int)$_REQUEST['league_id'] : 0;
	$club_id = isset($_REQUEST['club_id']) ? (int)$_REQUEST['club_id'] : 0;
	$is_club_series = ($club_id > 0);

	if ($club_id > 0)
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		list($owner_name, $owner_flags, $owner_langs) = Db::record(get_label('club'), 'SELECT name, flags, langs FROM clubs WHERE id = ?', $club_id);
		$owner_pic = new Picture(CLUB_PICTURE);
		$owner_id = $club_id;
		$owner_is_elite = false;
		$default_gaining_id = 0;
	}
	else if ($league_id > 0)
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		list($owner_name, $owner_flags, $owner_langs, $default_gaining_id) = Db::record(get_label('league'), 'SELECT name, flags, langs, gaining_id FROM leagues WHERE id = ?', $league_id);
		$owner_pic = new Picture(LEAGUE_PICTURE);
		$owner_id = $league_id;
		$owner_is_elite = ($owner_flags & LEAGUE_FLAG_ELITE) != 0;
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('league')));
	}

	echo '<table class="dialog_form" width="100%">';

	echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td width="' . ICON_WIDTH . '">';
	$owner_pic->set($owner_id, $owner_name, $owner_flags);
	$owner_pic->show(ICONS_DIR, false);
	echo '</td><td align="center"><b>' . $owner_name . '</b></td></tr></table></td></tr>';
	
	echo '<tr><td width="240">' . get_label('Series name') . ':</td><td><input id="form-name" value=""></td></tr>';

	// The type is not a property of a series: it follows from the three rows below. Picking one
	// here fills them in with the shape such a series usually has, and changing any of them shows
	// in this list which type the series has become. A season is what a new series starts as.
	$defaults = get_series_type_defaults(SERIES_TYPE_SEASON);

	echo '<tr><td>' . get_label('Type') . ':</td><td>';
	show_series_type_options(SERIES_TYPE_SEASON, 'onTypeChange()');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Maximum stars') . ':</td><td>';
	echo '<input type="number" style="width: 45px;" step="1" min="0" max="' . SERIES_MAX_STARS_LIMIT . '" id="form-max-stars" value="' . $defaults['max_stars'] . '" onchange="onParamChange()">';
	echo ' ' . get_label('zero if the series does not use stars.');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Stages') . ':</td><td>';
	echo '<select id="form-stages" onchange="onParamChange()">';
	foreach (get_series_stages_options() as $option => $option_name)
	{
		show_option($option, $defaults['stages'], $option_name);
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Played in the series') . ':</td><td>';
	echo '<select id="form-plays" onchange="onParamChange()">';
	foreach (get_series_plays_options() as $option => $option_name)
	{
		show_option($option, $defaults['plays'], $option_name);
	}
	echo '</select>';
	echo '</td></tr>';

	$timezone = get_timezone();
	$datetime = get_datetime(time(), $timezone);
	$date = datetime_to_string($datetime, false);

	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . $date . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . $date . '" onchange="setSeries()">';
	echo '  ';
	echo '<input type="checkbox" id="form-infinite"' . ($defaults['infinite'] ? ' checked' : '') . ' onclick="onParamChange()"> ' . get_label('no dates at all - the series is eternal.');
	echo '</td></tr>';

	// The parent series on offer are the ones this series would run inside of, which is a question
	// about dates: a series with no dates is not asked it, and this row goes away. It sits below
	// the dates rather than above them so that what stays put does not move when it does.
	echo '<tr id="form-series-row"><td>' . get_label('Belongs to series') . ':</td><td><div id="form-series"></div></td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate per player-tournament').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()" checked> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, DEFAULT_CURRENCY, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option((int)$cid, DEFAULT_CURRENCY, $cname);
	}
	echo '</select></td></tr>';
	
	if (is_valid_lang($owner_langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $owner_langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes(LANG_ALL, $owner_langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}

	// The owner's own gaining systems plus the global ones. A club has no default gaining system
	// the way a league does, so the first one on the list is preselected.
	$default_gaining_id = (int)$default_gaining_id;
	$query = new DbQuery('SELECT id, name FROM gainings WHERE (league_id IS NULL AND club_id IS NULL) OR league_id = ? OR club_id = ? ORDER BY name', $league_id, $club_id);
	echo '<tr><td>' . get_label('Gaining system') . ':</td><td><select id="form-gaining">';
	while ($row = $query->next())
	{
		list($gaining_id, $gaining_name) = $row;
		if ($default_gaining_id <= 0)
		{
			$default_gaining_id = (int)$gaining_id;
		}
		show_option((int)$gaining_id, $default_gaining_id, $gaining_name);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Notes') . ':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
	
	echo '<tr><td colspan="2">';
	
	// Whoever runs a series may close it to outsiders. The two below are not for everybody.
	echo '<input type="checkbox" id="form-private"> ' . get_label('private series. Only the people who run it may enter a tournament or a subseries into it.');
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<br><input type="checkbox" id="form-pin"> ' . get_label('pin to the main page.');
	}
	if ($owner_is_elite)
	{
		echo '<br><input type="checkbox" id="form-elite"> ' . get_label('elite series. The tournaments with more than one star become elite tournaments and bring more rating points.');
	}
	echo '</td></tr>';
	
	echo '</table>';
		
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	function feeChanged()
	{
		$("#form-fee-unknown").prop('checked', 0);
	}
	
	function feeUnknownClicked()
	{
		if ($("#form-fee-unknown").attr('checked'))
		{
			$("#form-fee").val('');
		}
	}
	
	function isInfinite()
	{
		return $('#form-infinite').prop('checked');
	}

	// The type of a series follows from its three properties, and this is the same derivation
	// series_type_from_params() does on the server, which is the one that counts.
	function deriveType()
	{
		if (isInfinite())
		{
			return <?php echo SERIES_TYPE_RECURRING; ?>;
		}
		if ($('#form-max-stars').val() < 1)
		{
			return <?php echo SERIES_TYPE_SERIES; ?>;
		}
		return <?php echo SERIES_TYPE_SEASON; ?>;
	}

	// Picking a type fills the three properties in with the shape such a series usually has. Any
	// of them can then be changed, which may well derive a different type - and that is the point.
	var typeDefaults = <?php echo json_encode(array(
		SERIES_TYPE_SEASON => get_series_type_defaults(SERIES_TYPE_SEASON),
		SERIES_TYPE_SERIES => get_series_type_defaults(SERIES_TYPE_SERIES),
		SERIES_TYPE_RECURRING => get_series_type_defaults(SERIES_TYPE_RECURRING))); ?>;

	function onTypeChange()
	{
		var d = typeDefaults[$('#form-type').val()];
		$('#form-infinite').prop('checked', d.infinite != 0);
		$('#form-max-stars').val(d.max_stars);
		$('#form-stages').val(d.stages);
		$('#form-plays').val(d.plays);
		onParamChange();
	}

	function onParamChange()
	{
		var infinite = isInfinite();
		// Disabled, not hidden: a control that disappears takes with it the only hint of how to
		// get it back, and the checkbox that hid it sits right there in the same row.
		$('#form-start, #form-end').prop('disabled', infinite);
		$('#form-series-row').css('display', infinite ? 'none' : '');
		$('#form-type').val(deriveType());
		// setSeries() refuses to run while the series has no dates, so the list of parent series
		// has to be asked for again once it is given some.
		if (!infinite)
		{
			setSeries();
		}
	}

	var seriesList = new Object();
	function setSeries()
	{
		if (isInfinite())
		{
			return;
		}
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		json.post("api/get/series.php",
		{
			ended_after: _end
			, started_before: '+' + strToDate($('#form-start').val())
			// Which series this one can be part of, and nothing to do with who is logged in. A club
			// series: the series of that club, the ones it has been invited to, and the series of the
			// leagues it belongs to - all three are what club_id means here. A league series: the
			// series of that league only.
			, <?php echo $is_club_series ? 'club_id' : 'league_id'; ?>: <?php echo $owner_id; ?>
			// Only the series a subseries can be part of: one where subseries are played, and not
			// one that is private to somebody else.
			, can_enter: <?php echo SERIES_FLAG_PLAYS_SERIES; ?>
			// A recurring tournament grades nothing and holds tournaments rather than subseries, so
			// there is nothing for a subseries to be worth inside one. Having no dates it overlapped
			// every window, which is why every one of them used to be on this list.
			, types: <?php echo SERIES_TYPE_FILTER_SEASON | SERIES_TYPE_FILTER_SERIES; ?>
		},
		function(series)
		{
			var html = '<table class="dialog_form" width="100%"><tr>';
			// Seeded with the parents it already has. This object is what the save posts, so an entry
			// dropped here would quietly unlink a series nobody touched - and the filters above can
			// leave out something that was linked before they existed.
			var sl = $.extend({}, seriesList);
			for (i = 0; i < series.series.length; ++i)
			{
				var s = series.series[i];
				if (seriesList[s.id])
				{
					sl[s.id] = seriesList[s.id];
				}
				else
				{
					sl[s.id] =
					{
						id: s.id,
						selected: false,
						stars: 0
					};
				}
				
				html += '<tr><td width="80" align="center"><img src="' + s.icon + '" width="48"><br><b>' + s.name + '</td>';
				
				html += '<td><input type="checkbox" id="form-p-' + s.id + '" onclick="seriesParticipantClick(' + s.id + ')"'
				if (sl[s.id].selected)
				{
					html += ' checked';
				}
				html += '> <?php echo get_label('participate'); ?></td>';
				html += '<td align="center"><div id="form-stars-' + s.id + '" class="stars"></div></td></tr>';
			}
			html += '</table>';
			$('#form-series').html(html);
			
			seriesList = sl;
			for (i = 0; i < series.series.length; ++i)
			{
				var s = series.series[i];
				if (seriesList[s.id])
				{
					$("#form-stars-" + s.id).rate(
					{
						max_value: s.max_stars,
						step_size: 1,
						initial_value: seriesList[s.id].stars,
					}).on("change", function(ev, data) { starsChanged(this, data.to); });
				}
			}
		});
	}
	onParamChange();
	
	function starsChanged(control, stars)
	{
		var seriesId = control.id.substr(control.id.lastIndexOf('-')+1);
		$("#form-p-" + seriesId).prop('checked', true);
		$("#form-f-" + seriesId).prop('disabled', false);
		seriesList[seriesId].stars = stars;
		seriesList[seriesId].selected = true;
	}
	
	function seriesParticipantClick(seriesId)
	{
		var d = false;
		if (!$("#form-p-" + seriesId).attr('checked'))
		{
			d = true;
			$("#form-stars-" + seriesId).rate("setValue", 0);
			$("#form-p-" + seriesId).prop('checked', false);
			$("#form-f-" + seriesId).prop('checked', false);
		}
		$("#form-f-" + seriesId).prop('disabled', d);
		seriesList[seriesId].selected = !d;
	}
	
	function onMinDateChange()
	{
		$('#form-end').attr("min", $('#form-start').val());
		var f = new Date($('#form-start').val());
		var t = new Date($('#form-end').val());
		if (f > t)
		{
			$('#form-end').val($('#form-start').val());
		}
		setSeries();
	}
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		
		var _flags = 0;
		_flags |= parseInt($('#form-plays').val());
		if ($("#form-private").attr('checked')) _flags |= <?php echo SERIES_FLAG_PRIVATE; ?>;
		if ($("#form-pin").attr('checked')) _flags |= <?php echo SERIES_FLAG_PINNED; ?>;
		if ($("#form-elite").attr('checked')) _flags |= <?php echo SERIES_FLAG_ELITE; ?>;
		
		var params =
		{
			op: "create",
			league_id: <?php echo $league_id; ?>,
			club_id: <?php echo $club_id; ?>,
			name: $("#form-name").val(),
			// The three properties a series is made of. The type is not sent: the op derives it
			// from them, which is what keeps the two sides from ever disagreeing about it.
			max_stars: $('#form-max-stars').val(),
			stages: $('#form-stages').val(),
			infinite: isInfinite() ? 1 : 0,
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			notes: $("#form-notes").val(),
			gaining_id: $('#form-gaining').val(),
			langs: _langs,
			flags: _flags
		};

		// A series with no dates has none to send, and was offered no parent series - the ones on
		// offer are those it would run inside of, which is a question about dates.
		if (!isInfinite())
		{
			var _end = strToDate($('#form-end').val());
			_end.setDate(_end.getDate() + 1); // inclusive
			params['start'] = $('#form-start').val();
			params['end'] = dateToStr(_end);

			var series = [];
			for (const i in seriesList)
			{
				var s = seriesList[i];
				if (s.selected)
				{
					series.push({ id: s.id, stars: s.stars });
				}
			}
			params['parent_series'] = JSON.stringify(series);
		}

		json.post("api/ops/series.php", params, onSuccess);
	}
	
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>