<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/tournament.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';

initiate_session();

function show_hide_bonus_array()
{
	echo '[[0,"' . get_label('Show bonus points') . '"],';
	echo '[' . (1 << TOURNAMENT_HIDE_BONUS_MASK_OFFSET) . ',"' . get_label('Hide bonus points') . '"],';
	echo '[' . (3 << TOURNAMENT_HIDE_BONUS_MASK_OFFSET) . ',"' . get_label('Hide bonus points starting from the semi-finals') . '"],';
	echo '[' . (2 << TOURNAMENT_HIDE_BONUS_MASK_OFFSET) . ',"' . get_label('Hide bonus points in the finals') . '"]]';
}

try
{
	dialog_title(get_label('Edit [0]', get_label('tournament')));
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['id'];
	
	list ($club_id, $name, $start_time, $duration, $timezone, $address_id, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $fee, $currency_id, $players, $langs, $notes, $flags, $tournament_type, $mwt_id, $rules_code) = 
		Db::record(get_label('tournament'), 'SELECT t.club_id, t.name, t.start_time, t.duration, ct.timezone, t.address_id, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, t.fee, t.currency_id, t.expected_players_count, t.langs, t.notes, t.flags, t.type, t.mwt_id, t.rules FROM tournaments t' . 
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
	echo '<tr><td width="240">' . get_label('Tournament name') . ':</td><td><input id="form-name" value="' . $name . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="14" width="120">';
	start_upload_logo_button($tournament_id);
	echo get_label('Change logo') . '<br>';
	$tournament_pic = new Picture(TOURNAMENT_PICTURE);
	$tournament_pic->set($tournament_id, $name, $flags);
	$tournament_pic->show(ICONS_DIR, false);
	end_upload_logo_button(TOURNAMENT_PIC_CODE, $tournament_id);
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Tournament type') . '</td><td><select id="form-type" onchange="typeChanged()">';
	show_option(TOURNAMENT_TYPE_CUSTOM, $tournament_type, get_label('Custom tournament. I will set up everything manually.'));
	show_option(TOURNAMENT_TYPE_FIIM_ONE_ROUND, $tournament_type, get_label('FIIM style tournament with only one round. (Mini-tournament).'));
	show_option(TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3, $tournament_type, get_label('FIIM style tournament with two rounds - main, and final. The final round has less than 4 games.'));
	show_option(TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4, $tournament_type, get_label('FIIM style tournament with two rounds - main, and final. The final round has 4 games or more.'));
	show_option(TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3, $tournament_type, get_label('FIIM style tournament with three rounds - main, semi-final, and final. The final round has less than 4 games.'));
	show_option(TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4, $tournament_type, get_label('FIIM style tournament with three rounds - main, semi-final, and final. The final round has 4 games or more.'));
	show_option(TOURNAMENT_TYPE_CHAMPIONSHIP, $tournament_type, get_label('Seasonal championship.'));
	echo '</select></td></tr>';
	
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

	if ($players < 10)
	{
		$players = 10;
	}
	echo '<tr><td>' . get_label('Expected number of players') . ':</td><td><input type="number" style="width: 45px;" step="1" min="10" id="form-players" value="'.$players.'"></td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="'.(is_null($fee)?'':$fee).'" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()"' . (is_null($fee)?' checked':'') .'> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option($cid, $currency_id, $cname);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Rules') . ':</td><td>';
	echo '<select id="form-rules">';
	if (show_option($club->rules_code, $rules_code, get_label('[default]')))
	{
		$rules_code = '';
	}
	$query = new DbQuery('SELECT l.name, c.rules FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER BY l.name', $club_id);
	while ($row = $query->next())
	{
		list ($league_name, $rules) = $row;
		if (show_option($rules, $rules_code, $league_name))
		{
			$rules_code = '';
		}
	}
	$query = new DbQuery('SELECT name, rules FROM club_rules WHERE club_id = ? ORDER BY name', $club_id);
	while ($row = $query->next())
	{
		list ($rules_name, $rules) = $row;
		if (show_option($rules, $rules_code, $rules_name))
		{
			$rules_code = '';
		}
	}
	echo '</select></td></tr>';
	
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
	
	$hide_table = $flags & TOURNAMENT_HIDE_TABLE_MASK;
	echo '<tr><td>'.get_label('Scoring table').':</td><td><p>' . get_label('Before the tournament is finished') . ': ';
	echo '</p><p><select id="form-hide-table" onchange="onChangeHidenTable()">';
	show_option(0, $hide_table, get_label('Show the scoring table'));
	show_option(1 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET, $hide_table, get_label('Hide the scoring table'));
	show_option(2 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET, $hide_table, get_label('Hide the scoring table in the finals'));
	show_option(3 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET, $hide_table, get_label('Hide the scoring table starting from the semi-finals'));
	echo '</select></p><p><div id="form-hide-bonus-span"></div></p></td></tr>';
	
	echo '<tr><td>'.get_label('Special awards').':</td><td>';
	echo '<table class="transp" width="100%">';
	echo '<tr><td colspan="2"><input type="checkbox" id="form-award-mvp"' . (($flags & TOURNAMENT_FLAG_AWARD_MVP) ? ' checked' : '')  . '> ' . get_label('MVP') . '</td></tr>';
	echo '<tr><td><input type="checkbox" id="form-award-red"' . (($flags & TOURNAMENT_FLAG_AWARD_RED) ? ' checked' : '')  . '> ' . get_label('best red player') . '</td>';
	echo '<td><input type="checkbox" id="form-award-black"' . (($flags & TOURNAMENT_FLAG_AWARD_BLACK) ? ' checked' : '')  . '> ' . get_label('best black player') . '</td></tr>';
	echo '<tr><td><input type="checkbox" id="form-award-sheriff"' . (($flags & TOURNAMENT_FLAG_AWARD_SHERIFF) ? ' checked' : '')  . '> ' . get_label('best sheriff') . '</td>';
	echo '<td><input type="checkbox" id="form-award-don"' . (($flags & TOURNAMENT_FLAG_AWARD_DON) ? ' checked' : '')  . '> ' . get_label('best don') . '</td></tr>';
	echo '</table>';
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('MWT tournament id') . ':</td><td><input id="form-mwt" value="' . $mwt_id . '"></td></tr>';
		
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
	
	echo '<input type="checkbox" id="form-manual_scoring"';
	if ($flags & TOURNAMENT_FLAG_MANUAL_SCORE)
	{
		echo ' checked';
	}
	echo  '> ' . get_label('scoring is entered manually instead of calculating it from games results.') . '<br>';
	
	echo '<input type="checkbox" id="form-pin"';
	if ($flags & SERIES_FLAG_PINNED)
	{
		echo ' checked';
	}
	echo  '> ' . get_label('pin to the main page.') . '<br>';
	
	echo '</td></tr>';
	echo '</table>';
	
	$fiim_id = 0;
	$query = new DbQuery('SELECT id FROM scorings where club_id IS NULL AND name="ФИИМ"');
	if ($row = $query->next())
	{
		list($fiim_id) = $row;
	}
	
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
			ended_after: _end
			, started_before: '+' + strToDate($('#form-start').val())
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
	
	var hideBonusVal = <?php echo $flags & TOURNAMENT_HIDE_BONUS_MASK; ?>;
	function onChangeHidenTable()
	{
		var v = $("#form-hide-table").val();
		var count = 0;
		var html = "";
		if (v == 0)
			count = 4;
		else if (v == <?php echo 2 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET; ?>)
			count = 3;
		else if (v == <?php echo 3 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET; ?>)
			count = 2;
		if (count > 0)
		{
			var l = <?php show_hide_bonus_array(); ?>;
			html += '<select id="form-hide-bonus" onchange="onChangeHiddenBonus()">';
			for (var i = 0; i < count; ++i)
			{
				html += '<option value="' + l[i][0] + '"';
				if (l[i][0] == hideBonusVal)
				{
					html += ' selected';
				}
				html += '>' +  l[i][1] + '</option>';
			}
			html += '</select>';
		}
		else
		{
			html = '<input type="hidden" id="form-hide-bonus" value="0">';
		}
		$('#form-hide-bonus-span').html(html);
	}
	onChangeHidenTable();
	
	function onChangeHiddenBonus()
	{
		hideBonusVal = $("#form-hide-bonus").val();
	}
	
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
		scoringId = s.sId;
		scoringVersion = s.sVer;
		scoringOptions = JSON.stringify(s.ops);
	}
	
	function longTermClicked()
	{
		var c = $("#form-long_term").attr('checked') ? true : false;
		$("#form-single_game").prop('checked', c);
		$("#form-single_game").prop('disabled', !c);
		mr.onChangeNormalizer('form-scoring', 0);
	}
	
	function singleGameClicked()
	{
		var c = $("#form-single_game").attr('checked') ? true : false;
	}
	
	function typeChanged()
	{
		var l = false;
		var s = false;
		var r = false;
		var scoringId = 0;
		var type = parseInt($('#form-type').val());
		switch(type)
		{
			case <?php echo TOURNAMENT_TYPE_CUSTOM; ?>:
				break;
			case <?php echo TOURNAMENT_TYPE_FIIM_ONE_ROUND; ?>:
				scoringId = <?php echo $fiim_id; ?>;
				break;
			case <?php echo TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3; ?>:
			case <?php echo TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4; ?>:
			case <?php echo TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3; ?>:
			case <?php echo TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4; ?>:
				scoringId = <?php echo $fiim_id; ?>;
				r = true;
				break;
			case <?php echo TOURNAMENT_TYPE_CHAMPIONSHIP; ?>:
				s = l = true;
				break;
		}
		
		$("#form-long_term").prop('checked', l).prop('disabled', type != 0);
		$("#form-single_game").prop('checked', s).prop('disabled', !l || type != 0);
		
		if (scoringId > 0)
		{
			$('#form-scoring-sel').val(scoringId);
			$('#form-scoring-difficulty').prop('checked', false);
			mr.onChangeScoring('form-scoring', 0, onScoringChange);
		}
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
		if ($("#form-long_term").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_LONG_TERM; ?>;
		if ($("#form-single_game").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_SINGLE_GAME; ?>;
		if ($("#form-team").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_TEAM; ?>;
		if ($("#form-manual_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_MANUAL_SCORE; ?>;
		if ($("#form-award-mvp").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_MVP; ?>;
		if ($("#form-award-red").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_RED; ?>;
		if ($("#form-award-black").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_BLACK; ?>;
		if ($("#form-award-sheriff").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_SHERIFF; ?>;
		if ($("#form-award-don").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_DON; ?>;
		if ($("#form-pin").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_PINNED; ?>;
		_flags |= $("#form-hide-table").val();
		_flags |= $("#form-hide-bonus").val();
		
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
			parent_series: series,
			name: $("#form-name").val(),
			type: $('#form-type').val(),
			address_id: $("#form-addr_id").val(),
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			mwt_id: $("#form-mwt").val(),
			scoring_id: scoringId,
			scoring_version: scoringVersion,
			scoring_options: scoringOptions,
			normalizer_id: $("#form-scoring-norm-sel").val(),
			normalizer_version: $("#form-scoring-norm-ver").val(),
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags,
			players: $("#form-players").val(),
			rules_code: $("#form-rules").val(),
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