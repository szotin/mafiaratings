<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/series.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('sеriеs')));
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('sеriеs')));
	}
	$series_id = (int)$_REQUEST['id'];
	$timezone = get_timezone();	
	
	list ($league_id, $club_id, $name, $start_time, $duration, $max_stars, $stages, $langs, $notes, $flags, $owner_langs, $owner_flags, $gaining_id, $gaining_version, $fee, $currency_id) =
		Db::record(get_label('sеriеs'),
			'SELECT s.league_id, s.club_id, s.name, s.start_time, s.duration, s.max_stars, s.stages, s.langs, s.notes, s.flags,' .
			' COALESCE(sol.langs, soc.langs), COALESCE(sol.flags, soc.flags),' .
			' s.gaining_id, s.gaining_version, s.fee, s.currency_id FROM series s' .
			series_owner_join() .
			' WHERE s.id = ?', $series_id);
	$currency_id = (int)$currency_id;
	$gaining_id = (int)$gaining_id;
	$max_stars = series_max_stars($max_stars);
	$stages = series_stage_count($stages);
	$plays = series_plays($flags);
	$infinite = series_is_infinite($start_time);
	$type = series_type($start_time, $max_stars);
	$league_id = (int)$league_id;
	$club_id = (int)$club_id;
	$owner = new SeriesOwner($league_id, $club_id);
	$is_club_series = !$owner->is_league();
	check_series_permissions($owner, $series_id);
	
	$series_list = '{';
	$delimiter = '';
	$query = new DbQuery('SELECT parent_id, stars FROM series_series WHERE child_id = ?', $series_id);
	while ($row = $query->next())
	{
		list ($s_id, $s_stars) = $row;
		$series_list .= 
			$delimiter . '"' . $s_id . '":{' . 
				'id:' . $s_id . ',' .
				'selected:true,' .
				'stars:' . $s_stars .
			'}';
		$delimiter = ',';
	}
	$series_list .= '}';
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="240">' . get_label('Series name') . ':</td><td><input id="form-name" value="' . $name . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="14" width="120">';
	start_upload_logo_button($series_id);
	echo get_label('Change logo') . '<br>';
	$series_pic = new Picture(SERIES_PICTURE);
	$series_pic->set($series_id, $name, $flags);
	$series_pic->show(ICONS_DIR, false);
	end_upload_logo_button(SERIES_PIC_CODE, $series_id);
	echo '</td></tr>';
	
	// The type is not a property of a series: it follows from the three rows below. Picking one
	// here fills them in with the shape such a series usually has, and changing any of them shows
	// in this list which type the series has become.
	echo '<tr><td>' . get_label('Type') . ':</td><td>';
	show_series_type_options($type, 'onTypeChange()');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Maximum stars') . ':</td><td>';
	echo '<input type="number" style="width: 45px;" step="1" min="0" max="' . SERIES_MAX_STARS_LIMIT . '" id="form-max-stars" value="' . $max_stars . '" onchange="onParamChange()">';
	echo ' ' . get_label('zero if the series does not use stars.');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Stages') . ':</td><td>';
	echo '<select id="form-stages" onchange="onParamChange()">';
	foreach (get_series_stages_options() as $option => $option_name)
	{
		show_option($option, $stages, $option_name);
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Played in the series') . ':</td><td>';
	echo '<select id="form-plays" onchange="onParamChange()">';
	foreach (get_series_plays_options() as $option => $option_name)
	{
		show_option($option, $plays, $option_name);
	}
	echo '</select>';
	echo '</td></tr>';

	// A series with no dates stores the epoch and an eternal duration, so the dates it would offer
	// are meaningless. Today is what the inputs start from if it is given dates.
	if ($infinite)
	{
		$start_time = time();
		$end_time = $start_time;
	}
	else
	{
		$end_time = $start_time + $duration - 24*60*60;
		if ($end_time < $start_time)
		{
			$end_time = $start_time;
		}
	}

	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . timestamp_to_string($start_time, $timezone, false) . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . timestamp_to_string($end_time, $timezone, false) . '" onchange="setSeries()">';
	echo '  ';
	echo '<input type="checkbox" id="form-infinite"' . ($infinite ? ' checked' : '') . ' onclick="onParamChange()"> ' . get_label('no dates at all - the series is eternal.');
	echo '</td></tr>';

	// The parent series on offer are the ones this series would run inside of, which is a question
	// about dates: a series with no dates is not asked it, and this row goes away. It sits below
	// the dates rather than above them so that what stays put does not move when it does.
	echo '<tr id="form-series-row"><td>' . get_label('Series') . ':</td><td><div id="form-series"></div></td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate per player-tournament').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="'.(is_null($fee)?'':$fee).'" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()"' . (is_null($fee)?' checked':'') .'> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option((int)$cid, $currency_id, $cname);
	}
	echo '</select></td></tr>';
	
	if (is_valid_lang($owner_langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $owner_langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes($langs, $owner_langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
	
	$query = new DbQuery('SELECT id, name FROM gainings WHERE (league_id IS NULL AND club_id IS NULL) OR league_id = ? OR club_id = ? ORDER BY name', $league_id, $club_id);
	echo '<tr><td>' . get_label('Gaining system') . ':</td><td><select id="form-gaining" onchange="gainingChanged()">';
	while ($row = $query->next())
	{
		list($gid, $gname) = $row;
		show_option((int)$gid, $gaining_id, $gname);
	}
	echo '</select> <select id="form-gaining-version"></select>';
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="60" rows="4">' . $notes . '</textarea></td></tr>';
		
	echo '<tr><td colspan="2">';
	// Whoever runs a series may close it to outsiders. The two below are not for everybody.
	echo '<input type="checkbox" id="form-private"';
	if ($flags & SERIES_FLAG_PRIVATE)
	{
		echo ' checked';
	}
	echo  '> ' . get_label('private series. Only the people who run it may enter a tournament or a subseries into it.');
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<br><input type="checkbox" id="form-pin"';
		if ($flags & SERIES_FLAG_PINNED)
		{
			echo ' checked';
		}
		echo  '> ' . get_label('pin to the main page.');
	}
	if ($owner->is_league() && ($owner_flags & LEAGUE_FLAG_ELITE) != 0)
	{
		echo '<br><input type="checkbox" id="form-elite"';
		if ($flags & SERIES_FLAG_ELITE)
		{
			echo ' checked';
		}
		echo  '> ' . get_label('elite series. The tournaments with more than one star become elite tournaments and bring more rating points.');
	}
	echo '</td></tr>';
	
	echo'</table>';
	
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	function gainingChanged(version)
	{
		json.post("api/get/gainings.php", { gaining_id: $('#form-gaining').val() }, function(data)
		{
			let g = data.gainings[0];
			let c = $('#form-gaining-version');
			c.find('option').remove();
			for (var v of g.versions)
			{
				c.append($('<option>').val(v.version).text(v.version));
			}
			if (isSet(version))
				c.val(version);
			else
				c.val(v.version);
		});
	}
	gainingChanged(<?php echo $gaining_version; ?>);
	
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
		if (!infinite)
		{
			setSeries();
		}
	}

	var seriesList = <?php echo $series_list; ?>;
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
			, <?php echo $is_club_series ? 'club_id' : 'league_id'; ?>: <?php echo $owner->id; ?>
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
				if (s.id == <?php echo $series_id; ?>)
				{
					continue;
				}
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
	// The stored properties are what the editor opens with, so only their effect is applied here
	// - picking a type is what replaces them.
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
			op: "change",
			series_id: <?php echo $series_id; ?>,
			name: $("#form-name").val(),
			// The three properties a series is made of. The type is not sent: the op derives it
			// from them, which is what keeps the two sides from ever disagreeing about it.
			max_stars: $('#form-max-stars').val(),
			stages: $('#form-stages').val(),
			infinite: isInfinite() ? 1 : 0,
			notes: $("#form-notes").val(),
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			gaining_id: $('#form-gaining').val(),
			gaining_version: $('#form-gaining-version').val(),
			langs: _langs,
			flags: _flags,
		};

		// A series with no dates was offered no parent series. Leaving parent_series out
		// altogether is what tells the op to leave the ones it has alone.
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
	
	function uploadLogo(seriesId, onSuccess)
	{
		json.upload('api/ops/series.php', 
		{
			op: "change"
			, series_id: seriesId
			, logo: document.getElementById("upload").files[0]
		}, 
		<?php echo UPLOAD_LOGO_MAX_SIZE; ?>, 
		onSuccess);
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