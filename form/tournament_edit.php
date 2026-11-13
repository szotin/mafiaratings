<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/tournament.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';
require_once '../include/series.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('tournament')));
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['id'];
	
	list ($club_id, $name, $start_time, $duration, $timezone, $address_id, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $fee, $currency_id, $num_players, $langs, $notes, $flags, $mwt_id, $imafia_id, $emo_id, $rules_code) = 
		Db::record(get_label('tournament'), 'SELECT t.club_id, t.name, t.start_time, t.duration, ct.timezone, t.address_id, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, t.fee, t.currency_id, t.num_players, t.langs, t.notes, t.flags, t.mwt_id, t.imafia_id, t.emo_id, t.rules FROM tournaments t' . 
		' JOIN addresses a ON a.id = t.address_id' .
		' JOIN cities ct ON ct.id = a.city_id' .
		' WHERE t.id = ?', $tournament_id);
	$address_id = (int)$address_id;
	$currency_id = (int)$currency_id;
	$flags = (int)$flags;
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	if (isset($_profile->clubs[$club_id]))
	{
		$club = $_profile->clubs[$club_id];
	}
	else
	{
		$club = new stdClass();
		list($club->langs) = Db::record(get_label('club'), 'SELECT langs FROM clubs WHERE id = ?', $club_id);
	}
	if (is_null($normalizer_id))
	{
		$normalizer_id = 0;
	}
	
	$series_list = '{';
	$delimiter = '';
	$query = new DbQuery('SELECT s.id, st.stars, st.stage, s.stages FROM series_tournaments st JOIN series s ON s.id = st.series_id WHERE st.tournament_id = ?', $tournament_id);
	while ($row = $query->next())
	{
		list ($series_id, $series_stars, $series_stage, $series_stages) = $row;
		$series_list .=
			$delimiter . '"' . $series_id . '":{' .
				'id:' . $series_id . ',' .
				'selected:true,' .
				'stage:' . series_stage($series_stages, $series_stage) . ',' .
				'stars:' . $series_stars .
			'}';
		$delimiter = ',';
	}
	$series_list .= '}';
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="240">' . get_label('Tournament name') . ':</td><td><input id="form-name" value="' . $name . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="8" width="120">';
	start_upload_logo_button($tournament_id);
	echo get_label('Change logo') . '<br>';
	$tournament_pic = new Picture(TOURNAMENT_PICTURE);
	$tournament_pic->set($tournament_id, $name, $flags);
	$tournament_pic->show(ICONS_DIR, false);
	end_upload_logo_button(TOURNAMENT_PIC_CODE, $tournament_id);
	echo '</td></tr>';

	
	echo '<tr><td>' . get_label('Series') . ':</td><td><div id="form-series"></div></td></tr>';
	
	$end_time = $start_time + $duration - 24*60*60;
	if ($end_time < $start_time)
	{
		$end_time = $start_time;
	}
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . timestamp_to_string($start_time, $timezone, false) . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . timestamp_to_string($end_time, $timezone, false) . '" onchange="setSeries()">';
	echo '</td></tr>';
	echo '</td></tr>';
	
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id">';
	while ($row = $query->next())
	{
		show_option((int)$row[0], $address_id, $row[1]);
	}
	echo '</select></td></tr>';


	echo '<tr><td>'.get_label('Admission rate').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="'.(is_null($fee)?'':$fee).'" onchange="feeChanged()">';
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
	

	if (is_valid_lang($club->langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $club->langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes($langs, $club->langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
	

	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="60" rows="4">' . $notes . '</textarea></td></tr>';
		
	// The rules, the scoring, the awards, the number of players, whether the tournament is a
	// long-term one, the hiding of the table and who may register are set on the preparation
	// page now, each on its own tab; the stream on the stream settings page. The ids on the other
	// sites are set by the imports.
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-manual_scoring"';
	if ($flags & TOURNAMENT_FLAG_MANUAL_SCORE)
	{
		echo ' checked';
	}
	echo  '> ' . get_label('scoring is entered manually instead of calculating it from games results.') . '<br>';
	
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<input type="checkbox" id="form-pin"';
		if ($flags & SERIES_FLAG_PINNED)
		{
			echo ' checked';
		}
		echo  '> ' . get_label('pin to the main page.') . '<br>';
	}

	echo '<input type="checkbox" id="form-hide"';
	if ($flags & TOURNAMENT_FLAG_HIDE_FROM_MAIN_PAGE)
	{
		echo ' checked';
	}
	echo  '> ' . get_label('hide from the main page.') . '<br>';
	echo '</td></tr>';
	echo '</table>';
	
?>

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	var seriesList = <?php echo $series_list; ?>;
	function setSeries()
	{
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		json.post("api/get/series.php",
		{
			ended_after: '+' + strToDate($('#form-start').val())
			, started_before: '+' + _end
			, club_id: <?php echo $club_id; ?>
			// Only the series this tournament can be part of: one where tournaments are played,
			// and not one that is private to somebody else.
			, can_enter: <?php echo SERIES_FLAG_PLAYS_TOURNAMENTS; ?>
		},
		function(series)
		{
			var html = '<table class="dialog_form" width="100%"><tr>';
			var sl = new Object();
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
						stars: 0,
						stage: <?php echo SERIES_STAGE_BASE; ?>
					};
				}
				
				// Not every series rates its tournaments, and not every series is a ladder. A
				// series that does neither asks nothing beyond whether the tournament is in it.
				//
				// The ladder is the shape of the competition, so only a manager of the series is
				// shown it. For anybody else the stage simply stays what it is - the op ignores a
				// stage sent by them, so saving the tournament cannot undo it.
				var hasStars = (s.max_stars > 0);
				var hasStages = (s.stages > 1);
				var mayEditStage = (hasStages && s.is_manager);

				html += '<tr><td width="80" align="center"><img src="' + s.icon + '" width="48"><br><b>' + s.name + '</td>';

				html += '<td><input type="checkbox" id="form-p-' + s.id + '" onclick="seriesParticipantClick(' + s.id + ')"'
				if (sl[s.id].selected)
				{
					html += ' checked';
				}
				html += '> <?php echo get_label('participate'); ?>';

				html += '</td>';

				html += '<td align="center" nowrap>';
				if (mayEditStage)
				{
					html += '<select id="form-stage-' + s.id + '" onchange="stageChanged(' + s.id + ')">';
					for (var j = 0; j < s.stage_names.length; ++j)
					{
						html += '<option value="' + s.stage_names[j].stage + '"';
						if (s.stage_names[j].stage == sl[s.id].stage)
						{
							html += ' selected';
						}
						html += '>' + s.stage_names[j].name + '</option>';
					}
					html += '</select>';
					if (hasStars)
					{
						html += '<br>';
					}
				}
				if (hasStars)
				{
					html += '<div id="form-stars-' + s.id + '" class="stars"></div>';
				}
				html += '</td></tr>';
			}
			html += '</table>';
			$('#form-series').html(html);

			seriesList = sl;
			for (i = 0; i < series.series.length; ++i)
			{
				var s = series.series[i];
				if (s.max_stars < 1)
				{
					continue; // no star control was drawn for it
				}
				$("#form-stars-" + s.id).rate(
				{
					max_value: s.max_stars,
					step_size: 1,
					initial_value: seriesList[s.id].stars,
				}).on("change", function(ev, data) { starsChanged(this, data.to); });
			}
		});
	}
	setSeries();
	
	function starsChanged(control, stars)
	{
		
		var seriesId = control.id.substr(control.id.lastIndexOf('-')+1);
		$("#form-p-" + seriesId).prop('checked', true);
		seriesList[seriesId].stars = stars;
		seriesList[seriesId].selected = true;
	}
	
	function stageChanged(seriesId)
	{
		seriesList[seriesId].stage = $("#form-stage-" + seriesId).val();
		$("#form-p-" + seriesId).prop('checked', true);
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
		}
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
		if ($("#form-manual_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_MANUAL_SCORE; ?>;
		if ($("#form-pin").length > 0 && $("#form-pin").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_PINNED; ?>;
		if ($("#form-hide").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_HIDE_FROM_MAIN_PAGE; ?>;
		// Only the flags this form shows. The rest are set elsewhere - see the note above the
		// checkboxes - and are left as they are.
		var _flagsMask = <?php echo TOURNAMENT_FLAG_MANUAL_SCORE | TOURNAMENT_FLAG_PINNED | TOURNAMENT_FLAG_HIDE_FROM_MAIN_PAGE; ?>;
		
		var series = [];
		for (const i in seriesList) 
		{
			var s = seriesList[i];
			if (s.selected)
			{
				var _s = { id: s.id, stars: s.stars, stage: s.stage };
				series.push(_s);
			}
		}
		series = JSON.stringify(series);
		
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		var params =
		{
			op: "change",
			tournament_id: <?php echo $tournament_id; ?>,
			parent_series: series,
			name: $("#form-name").val(),
			address_id: $("#form-addr_id").val(),
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags,
			flags_mask: _flagsMask,
		};
		
		json.post("api/ops/tournament.php", params, onSuccess);
	}
	
	function uploadLogo(tournamentId, onSuccess)
	{
		json.upload('api/ops/tournament.php', 
		{
			op: "change"
			, tournament_id: tournamentId
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