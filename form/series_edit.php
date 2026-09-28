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
	
	list ($league_id, $club_id, $name, $start_time, $duration, $type, $max_stars, $langs, $notes, $flags, $owner_langs, $owner_flags, $gaining_id, $gaining_version, $fee, $currency_id) =
		Db::record(get_label('sеriеs'),
			'SELECT s.league_id, s.club_id, s.name, s.start_time, s.duration, s.type, s.max_stars, s.langs, s.notes, s.flags,' .
			' COALESCE(sol.langs, soc.langs), COALESCE(sol.flags, soc.flags),' .
			' s.gaining_id, s.gaining_version, s.fee, s.currency_id FROM series s' .
			series_owner_join() .
			' WHERE s.id = ?', $series_id);
	$currency_id = (int)$currency_id;
	$gaining_id = (int)$gaining_id;
	$type = (int)$type;
	$max_stars = (int)$max_stars;
	$league_id = (int)$league_id;
	$club_id = (int)$club_id;
	$owner = new SeriesOwner($league_id, $club_id);
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
	
	echo '<td align="center" valign="top" rowspan="13" width="120">';
	start_upload_logo_button($series_id);
	echo get_label('Change logo') . '<br>';
	$series_pic = new Picture(SERIES_PICTURE);
	$series_pic->set($series_id, $name, $flags);
	$series_pic->show(ICONS_DIR, false);
	end_upload_logo_button(SERIES_PIC_CODE, $series_id);
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Type') . ':</td><td>';
	show_series_type_options($type, 'onTypeChange()');
	echo '</td></tr>';

	// Every tournament of a recurring tournament is the same event happening again, so it
	// always gives exactly one star and is not asked about it.
	echo '<tr id="form-max-stars-row"><td>' . get_label('Maximum stars') . ':</td><td>';
	echo '<input type="number" style="width: 45px;" step="1" min="1" max="' . SERIES_MAX_STARS_LIMIT . '" id="form-max-stars" value="' . series_max_stars($type, $max_stars) . '">';
	echo '</td></tr>';

	// A recurring tournament has no dates, and the parent series on offer are the ones this series
	// would run inside of - a question about dates too. Both rows are hidden for it.
	echo '<tr id="form-series-row"><td>' . get_label('Series') . ':</td><td><div id="form-series"></div></td></tr>';

	// A recurring tournament stores an eternal duration, so the end it would offer is meaningless.
	// Today is what the date inputs start from if the type is taken away from it.
	if ($type == SERIES_TYPE_RECURRING)
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

	echo '<tr id="form-dates-row"><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . timestamp_to_string($start_time, $timezone, false) . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . timestamp_to_string($end_time, $timezone, false) . '" onchange="setSeries()"">';
	echo '</td></tr>';
	echo '</td></tr>';
	
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
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<input type="checkbox" id="form-pin"';
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
	
	function isRecurring()
	{
		return $('#form-type').val() == <?php echo SERIES_TYPE_RECURRING; ?>;
	}

	function onTypeChange()
	{
		var recurring = isRecurring();
		$('#form-dates-row').css('display', recurring ? 'none' : '');
		$('#form-series-row').css('display', recurring ? 'none' : '');
		$('#form-max-stars-row').css('display', recurring ? 'none' : '');
		if (!recurring)
		{
			setSeries();
		}
	}

	var seriesList = <?php echo $series_list; ?>;
	function setSeries()
	{
		if (isRecurring())
		{
			return;
		}
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		json.post("api/get/series.php",
		{
			ended_after: _end
			, started_before: '+' + strToDate($('#form-start').val())
		},
		function(series)
		{
			var html = '<table class="dialog_form" width="100%"><tr>';
			var sl = new Object();
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
	onTypeChange();
	
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
		if ($("#form-pin").attr('checked')) _flags |= <?php echo SERIES_FLAG_PINNED; ?>;
		if ($("#form-elite").attr('checked')) _flags |= <?php echo SERIES_FLAG_ELITE; ?>;

		var params =
		{
			op: "change",
			series_id: <?php echo $series_id; ?>,
			name: $("#form-name").val(),
			type: $('#form-type').val(),
			max_stars: $('#form-max-stars').val(),
			notes: $("#form-notes").val(),
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			gaining_id: $('#form-gaining').val(),
			gaining_version: $('#form-gaining-version').val(),
			langs: _langs,
			flags: _flags,
		};

		// A recurring tournament has no dates, and no parent series were offered for it. Leaving
		// parent_series out altogether is what tells the op to leave the ones it has alone.
		if (!isRecurring())
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