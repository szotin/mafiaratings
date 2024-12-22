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
	dialog_title(get_label('Create [0]', get_label('tournament')));
	
	if (!isset($_REQUEST['club_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	
	$club_id = (int)$_REQUEST['club_id'];
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	$club = $_profile->clubs[$club_id];
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value=""></td></tr>';
	
	$tournament_type = TOURNAMENT_TYPE_CUSTOM;
	echo '<tr><td>' . get_label('Tournament type') . '</td><td><select id="form-type" onchange="typeChanged()">';
	show_option(TOURNAMENT_TYPE_CUSTOM, $tournament_type, get_label('Custom tournament. I will set up everything manually.'));
	show_option(TOURNAMENT_TYPE_FIIM_ONE_ROUND, $tournament_type, get_label('FIIM style tournament with only one round. (Mini-tournament).'));
	show_option(TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3, $tournament_type, get_label('FIIM style tournament with two rounds - main, and final. The final round has less than 4 games.'));
	show_option(TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4, $tournament_type, get_label('FIIM style tournament with two rounds - main, and final. The final round has 4 games or more.'));
	show_option(TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3, $tournament_type, get_label('FIIM style tournament with three rounds - main, semi-final, and final. The final round has less than 4 games.'));
	show_option(TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4, $tournament_type, get_label('FIIM style tournament with three rounds - main, semi-final, and final. The final round has 4 games or more.'));
	show_option(TOURNAMENT_TYPE_CHAMPIONSHIP, $tournament_type, get_label('Seasonal championship.'));
	echo '</select></td></tr>';

	$scoring_id = $club->scoring_id;
	$normalizer_id = $club->normalizer_id;
	if (is_null($normalizer_id))
	{
		$normalizer_id = 0;
	}
	
	echo '<tr><td>' . get_label('Series') . ':</td><td><div id="form-series"></div></td></tr>';
	
	$normalizer_id = 0; // set it to null because long term is not checked
	$normalizer_version = 0;
	list($scoring_version) = Db::record(get_label('scoring system'), 'SELECT version FROM scorings WHERE id = ?', $scoring_id);
	
	$datetime = get_datetime(time(), $club->timezone);
	$date = datetime_to_string($datetime, false);
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . $date . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . $date . '" onchange="setSeries()">';
	echo '</td></tr>';
	
	$addr_id = -1;
	$scoring_options = '{}';
	$query = new DbQuery('SELECT address_id, scoring_options FROM tournaments WHERE club_id = ? ORDER BY start_time DESC LIMIT 1', $club_id);
	$row = $query->next();
	if ($row = $query->next())
	{
		list($addr_id, $scoring_options) = $row;
	}
	else
	{
		$query = new DbQuery('SELECT address_id, scoring_options FROM events WHERE club_id = ? ORDER BY start_time DESC LIMIT 1', $club_id);
		if ($row = $query->next())
		{
			list($addr_id, $scoring_options) = $row;
		}
	}
	
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id" onChange="addressClick()">';
	show_option(-1, $addr_id, get_label('New address'));
	$selected_address = '';
	while ($row = $query->next())
	{
		if (show_option($row[0], $addr_id, $row[1]))
		{
			$selected_address = $row[1];
		}
	}
	echo '</select><div id="form-new_addr_div">';
//	echo '<button class="icon" onclick="mr.createAddr(' . $club_id . ')" title="' . get_label('Create [0]', get_label('address')) . '"><img src="images/create.png" border="0"></button>';
	echo '<input id="form-new_addr" onkeyup="newAddressChange()"> ';
	show_country_input('form-country', $club->country, 'form-city');
	echo ' ';
	show_city_input('form-city', $club->city, 'form-country');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Expected number of players') . ':</td><td><input type="number" style="width: 45px;" step="1" min="10" id="form-players" value="10"> ';
	echo '<input type="checkbox" id="form-correct-players" checked> ';
	echo get_label('correct number of players after the tournament is complete');
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()" checked> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $club->currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option($cid, $club->currency_id, $cname);
	}
	echo '</select></td></tr>';
	
	$rules_code = $club->rules_code;
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
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Scoring table').':</td><td><p>' . get_label('Before the tournament is finished') . ': ';
	echo '</p><p><select id="form-hide-table" onchange="onChangeHidenTable()">';
	show_option(0, 0, get_label('Show the scoring table'));
	show_option(1 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET, 0, get_label('Hide the scoring table'));
	show_option(2 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET, 0, get_label('Hide the scoring table in the finals'));
	show_option(3 << TOURNAMENT_HIDE_TABLE_MASK_OFFSET, 0, get_label('Hide the scoring table starting from the semi-finals'));
	echo '</select></p><p><div id="form-hide-bonus-span"></div></p></td></tr>';
	
	echo '<tr><td>'.get_label('Special awards').':</td><td>';
	echo '<table class="transp" width="100%">';
	echo '<tr><td colspan="2"><input type="checkbox" id="form-award-mvp" checked> ' . get_label('MVP') . '</td></tr>';
	echo '<tr><td><input type="checkbox" id="form-award-red" checked> ' . get_label('best red player') . '</td>';
	echo '<td><input type="checkbox" id="form-award-black" checked> ' . get_label('best black player') . '</td></tr>';
	echo '<tr><td><input type="checkbox" id="form-award-sheriff"> ' . get_label('best sheriff') . '</td>';
	echo '<td><input type="checkbox" id="form-award-don"> ' . get_label('best don') . '</td></tr>';
	echo '</table>';
	echo '</td></tr>';
	
	if (is_valid_lang($club->langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $club->langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes(LANG_ALL, $club->langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
	
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
	
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-reg"> ' . get_label('registration is closed') . '<br>';
	echo '<input type="checkbox" id="form-team"> ' . get_label('team tournament') . '<br>';
	echo '<input type="checkbox" id="form-long_term" onclick="longTermClicked()"> ' . get_label('long term tournament. Like a seasonal club championship.') . '<br>';
	echo '<input type="checkbox" id="form-single_game" onclick="singleGameClicked()"> ' . get_label('single games from non-tournament events can be assigned to the tournament.') . '<br>';
	echo '<input type="checkbox" id="form-manual_scoring"> ' . get_label('scoring is entered manually instead of calculating it from games results.') . '<br>';
	echo '<input type="checkbox" id="form-pin"> ' . get_label('pin to the main page.') . '<br>';
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
	
	var seriesList = new Object();
	function setSeries()
	{
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		json.post("api/get/series.php",
		{
			ended_after: '+' + strToDate($('#form-start').val())
			, started_before: _end
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
	
	var hideBonusVal = 0;
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
		var type = parseInt($('#form-type').val());
		var c = $("#form-long_term").attr('checked') ? true : false;
		$("#form-single_game").prop('checked', c);
		$("#form-single_game").prop('disabled', !c || type != 0);
		mr.onChangeNormalizer('form-scoring', 0);
	}
	
	function singleGameClicked()
	{
		var type = parseInt($('#form-type').val());
		var c = $("#form-single_game").attr('checked') ? true : false;
	}
	longTermClicked();
	
	var oldAddressValue = "<?php echo $selected_address; ?>";
	function newAddressChange()
	{
		var text = $("#form-new_addr").val();
		if ($("#form-name").val() == oldAddressValue)
		{
			$("#form-name").val(text);
		}
		oldAddressValue = text;
	}
	
	function addressClick()
	{
		var text = '';
		if ($("#form-addr_id").val() <= 0)
		{
			$("#form-new_addr_div").show();
		}
		else
		{
			$("#form-new_addr_div").hide();
			text = $("#form-addr_id option:selected").text();
		}
		
		if ($("#form-name").val() == oldAddressValue)
		{
			$("#form-name").val(text);
		}
		oldAddressValue = text;
	}
	addressClick();
	
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
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		if ($("#form-reg").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_REGISTRATION_CLOSED; ?>;
		if ($("#form-long_term").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_LONG_TERM; ?>;
		if ($("#form-single_game").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_SINGLE_GAME; ?>;
		if ($("#form-manual_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_MANUAL_SCORE; ?>;
		if ($("#form-team").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_TEAM; ?>;
		if ($("#form-award-mvp").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_MVP; ?>;
		if ($("#form-award-red").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_RED; ?>;
		if ($("#form-award-black").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_BLACK; ?>;
		if ($("#form-award-sheriff").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_SHERIFF; ?>;
		if ($("#form-award-don").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_AWARD_DON; ?>;
		if ($("#form-pin").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_PINNED; ?>;
		if (!$("#form-correct-players").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_FORCE_NUM_PLAYERS; ?>;
		_flags |= $("#form-hide-table").val();
		_flags |= $("#form-hide-bonus").val();
		
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		
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
		
		var params =
		{
			op: "create",
			club_id: <?php echo $club_id; ?>,
			parent_series: series,
			name: $("#form-name").val(),
			type: $('#form-type').val(),
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			address_id: _addr,
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
		
		if (_addr > 0)
		{
			params['address_id'] = _addr;
		}
		else
		{
			params['address'] = $("#form-new_addr").val();
			params['country'] = $("#form-country").val();
			params['city'] = $("#form-city").val();
		}
	
		json.post("api/ops/tournament.php", params, onSuccess);
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