<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/tournament.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('tournament')));
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['id'];
	
	list ($club_id, $name, $start_time, $duration, $timezone, $address_id, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $price, $langs, $notes, $flags) = 
		Db::record(get_label('tournament'), 'SELECT t.club_id, t.name, t.start_time, t.duration, ct.timezone, t.address_id, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, t.price, t.langs, t.notes, t.flags FROM tournaments t' . 
		' JOIN addresses a ON a.id = t.address_id' .
		' JOIN cities ct ON ct.id = a.city_id' .
		' WHERE t.id = ?', $tournament_id);
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
	$query = new DbQuery('SELECT s.id, st.stars, s.finals_id FROM series_tournaments st JOIN series s ON s.id = st.series_id WHERE st.tournament_id = ?', $tournament_id);
	while ($row = $query->next())
	{
		list ($series_id, $series_stars, $series_finals_id) = $row;
		$series_list .= 
			$delimiter . '"' . $series_id . '":{' . 
				'id:' . $series_id . ',' .
				'selected:true,' .
				'finals:' . (($series_finals_id == $tournament_id) ? 'true' : 'false') . ',' .
				'stars:' . $series_stars .
			'}';
		$delimiter = ',';
	}
	$series_list .= '}';
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value="' . $name . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="12" width="120">';
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
		show_option($row[0], $address_id, $row[1]);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Admission rate') . ':</td><td><input id="form-price" value="' . $price . '"></td></tr>';
	
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td>';
	show_scoring_select($club_id, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, json_decode($scoring_options), '<br>', 'onScoringChange', SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION, 'form-scoring');
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
		
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-team"';
	if ($flags & TOURNAMENT_FLAG_TEAM)
	{
		echo ' checked';
	}
	echo  '> ' . get_label('team tournament') . '<br>';
	
	echo '<input type="checkbox" id="form-long_term" onclick="longTermClicked()"';
	if ($flags & TOURNAMENT_FLAG_LONG_TERM)
	{
		echo ' checked';
	}
	echo '> ' . get_label('long term tournament. Like a seasonal club championship.') . '<br>';
	
	echo '<input type="checkbox" id="form-single_game" onclick="singleGameClicked()"';
	if (($flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
	{
		echo ' disabled';
	}
	if ($flags & TOURNAMENT_FLAG_SINGLE_GAME)
	{
		echo ' checked';
	}
	echo '> ' . get_label('single games from non-tournament events can be assigned to the tournament.') . '<br>';
	
	echo '<input type="checkbox" id="form-use_rounds_scoring"';
	if ($flags & TOURNAMENT_FLAG_SINGLE_GAME)
	{
		echo ' disabled';
	}
	if ($flags & TOURNAMENT_FLAG_USE_ROUNDS_SCORING)
	{
		echo ' checked';
	}
	echo '> ' . get_label('scoring rules can be custom in tournament rounds.') . '<br>';
	
	echo '<input type="checkbox" id="form-manual_scoring"';
	if ($flags & TOURNAMENT_FLAG_MANUAL_SCORE)
	{
		echo ' checked';
	}
	echo  '> ' . get_label('scoring is entered manually instead of calculating it from games results.') . '<br>';
	
	echo '</td></tr>';
	echo '</table>';
	
?>

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	var seriesList = <?php echo $series_list; ?>;
	function setSeries()
	{
		json.post("api/get/series.php",
		{
			started_before: new Date($('#form-end').val())
			, ended_after: new Date($('#form-start').val())
			, club_id: <?php echo $club_id; ?>
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
						finals: false,
						stars: 0
					};
				}
				
				html += '<tr><td width="80" align="center"><img src="' + s.icon + '" width="48"><br><b>' + s.name + '</td>';
				
				html += '<td><input type="checkbox" id="form-p-' + s.id + '" onclick="seriesParticipantClick(' + s.id + ')"'
				if (sl[s.id].selected)
				{
					html += ' checked';
				}
				html += '> <?php echo get_label('participate'); ?>';
				
				html += '<br><input type="checkbox" id="form-f-' + s.id + '" onclick="seriesFinalsClick(' + s.id + ')"';
				if (!sl[s.id].selected)
				{
					html += ' disabled';
				}
				if (sl[s.id].finals)
				{
					html += ' checked';
				}
				html += '> <?php echo get_label('finals'); ?></td>';
				
				html += '<td align="center"><div id="form-stars-' + s.id + '" class="stars"></div></td></tr>';
			}
			html += '</table>';
			$('#form-series').html(html);
			
			seriesList = sl;
			for (i = 0; i < series.series.length; ++i)
			{
				console.log(s.id);
				var s = series.series[i];
				$("#form-stars-" + s.id).rate(
				{
					max_value: 5,
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
			seriesList[seriesId].finals = false;
		}
		$("#form-f-" + seriesId).prop('disabled', d);
		seriesList[seriesId].selected = !d;
	}
	
	function seriesFinalsClick(seriesId)
	{
		seriesList[seriesId].finals = $("#form-f-" + seriesId).attr('checked') ? true : false;
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
	
	var scoringId = <?php echo $scoring_id; ?>;
	var scoringVersion = <?php echo $scoring_version; ?>;
	var scoringOptions = '<?php echo $scoring_options; ?>';
	function onScoringChange(s)
	{
		console.log(s);
		scoringId = s.sId;
		scoringVersion = s.sVer;
		scoringOptions = JSON.stringify(s.ops);
	}
	
	function longTermClicked()
	{
		var c = $("#form-long_term").attr('checked') ? true : false;
		$("#form-single_game").prop('checked', c);
		$("#form-use_rounds_scoring").prop('checked', !c);
		$("#form-single_game").prop('disabled', !c);
		$("#form-use_rounds_scoring").prop('disabled', c);
		mr.onChangeNormalizer('form-scoring', 0);
	}
	
	function singleGameClicked()
	{
		var c = $("#form-single_game").attr('checked') ? true : false;
		$("#form-use_rounds_scoring").prop('checked', !c);
		$("#form-use_rounds_scoring").prop('disabled', c);
	}
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _flags = 0;
		if ($("#form-long_term").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_LONG_TERM; ?>;
		if ($("#form-single_game").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_SINGLE_GAME; ?>;
		if ($("#form-use_rounds_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_USE_ROUNDS_SCORING; ?>;
		if ($("#form-team").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_TEAM; ?>;
		if ($("#form-manual_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_MANUAL_SCORE; ?>;
		
		var series = [];
		for (const i in seriesList) 
		{
			var s = seriesList[i];
			if (s.selected)
			{
				var _s = { id: s.id, stars: s.stars };
				if (s.finals)
				{
					_s['finals'] = true;
				}
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
			series: series,
			name: $("#form-name").val(),
			price: $("#form-price").val(),
			address_id: $("#form-addr_id").val(),
			scoring_id: scoringId,
			scoring_version: scoringVersion,
			scoring_options: scoringOptions,
			normalizer_id: $("#form-scoring-norm-sel").val(),
			normalizer_version: $("#form-scoring-norm-ver").val(),
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags
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