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
	dialog_title(get_label('Create [0]', get_label('tournament')));
	
	if (!isset($_REQUEST['club_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	
	$club_id = (int)$_REQUEST['club_id'];
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	$club = $_profile->clubs[$club_id];
	
	$league_id = 0;
	if (isset($_REQUEST['league_id']))
	{
		$league_id = (int)$_REQUEST['league_id'];
	}

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value=""></td></tr>';
	
	$tournament_type = TOURNAMENT_TYPE_CUSTOM;
	echo '<tr><td>' . get_label('Tournament type') . '</td><td><select id="form-type" onchange="typeChanged()">';
	show_option(TOURNAMENT_TYPE_CUSTOM, $tournament_type, get_label('Custom tournament. I will set up everything manually.'));
	show_option(TOURNAMENT_TYPE_FIGM_ONE_ROUND, $tournament_type, get_label('FIGM style tournament with only one round. (Mini-tournament).'));
	show_option(TOURNAMENT_TYPE_FIGM_TWO_ROUNDS_FINALS3, $tournament_type, get_label('FIGM style tournament with two rounds - main, and final. The final round has less than 4 games.'));
	show_option(TOURNAMENT_TYPE_FIGM_TWO_ROUNDS_FINALS4, $tournament_type, get_label('FIGM style tournament with two rounds - main, and final. The final round has 4 games or more.'));
	show_option(TOURNAMENT_TYPE_FIGM_THREE_ROUNDS_FINALS3, $tournament_type, get_label('FIGM style tournament with three rounds - main, semi-final, and final. The final round has less than 4 games.'));
	show_option(TOURNAMENT_TYPE_FIGM_THREE_ROUNDS_FINALS4, $tournament_type, get_label('FIGM style tournament with three rounds - main, semi-final, and final. The final round has 4 games or more.'));
	show_option(TOURNAMENT_TYPE_AML_ONE_ROUND, $tournament_type, get_label('AML style tournament with only one round. (Mini-tournament).'));
	show_option(TOURNAMENT_TYPE_AML_TWO_ROUNDS, $tournament_type, get_label('AML style tournament with two rounds - main, and final.'));
	show_option(TOURNAMENT_TYPE_AML_THREE_ROUNDS, $tournament_type, get_label('AML style tournament with three rounds - main, semi-final, and final.'));
	show_option(TOURNAMENT_TYPE_SERIES, $tournament_type, get_label('Mini-tournament series.'));
	show_option(TOURNAMENT_TYPE_CHAMPIONSHIP, $tournament_type, get_label('Seasonal championship.'));
	echo '</td></tr>';

	if ($league_id > 0)
	{
		list($league_name, $league_flags, $scoring_id, $normalizer_id) = Db::record(get_label('league'), 'SELECT name, flags, scoring_id, normalizer_id FROM leagues WHERE id = ?', $league_id);
		if (is_null($normalizer_id))
		{
			$normalizer_id = 0;
		}
		
		echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td width="' . ICON_WIDTH . '">';
		$league_pic = new Picture(LEAGUE_PICTURE);
		$league_pic->set($league_id, $league_name, $league_flags);
		$league_pic->show(ICONS_DIR, false);
		echo '</td><td align="center"><b>' . $league_name . '</b><input type="hidden" id="form-league" value="' . $league_id . ',' . $scoring_id . ',' . $normalizer_id . '"></td></tr></table></td></tr>';
	}
	else
	{
		$scoring_id = $club->scoring_id;
		$normalizer_id = $club->normalizer_id;
		if (is_null($normalizer_id))
		{
			$normalizer_id = 0;
		}
		
		echo '<tr><td>' . get_label('League') . ':</td><td><select id="form-league" onchange="onLeagueChange()">';
		echo '<option value="0,' . $scoring_id . ',' . $normalizer_id . '" selected></option>';
		$query = new DbQuery('SELECT l.id, l.name, l.scoring_id, l.normalizer_id FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER by l.name', $club_id);
		while ($row = $query->next())
		{
			list($lid, $lname, $lsid, $lnid) = $row;
			if (is_null($lnid))
			{
				$lnid = 0;
			}
			echo '<option value="' . $lid . ',' . $lsid . ',' . $lnid . '">' . $lname . '</option>';
		}
		echo '</select></td></tr>';
	}
	
	$normalizer_id = 0; // set it to null because long term is not checked
	$normalizer_version = 0;
	list($scoring_version) = Db::record(get_label('scoring system'), 'SELECT version FROM scorings WHERE id = ?', $scoring_id);
	
	echo '<tr><td>' . get_label('Stars') . ':</td><td><div id="form-stars" class="stars"></div></td></tr>';
	
	$datetime = get_datetime(time(), $club->timezone);
	$date = datetime_to_string($datetime, false);
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . $date . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . $date . '">';
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
	echo '</span></td></tr>';
	
	echo '<tr><td>' . get_label('Admission rate') . ':</td><td><input id="form-price" value=""></td></tr>';
	
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td>';
	show_scoring_select($club_id, $scoring_id, $scoring_version, json_decode($scoring_options), '<br>', 'onScoringChange', SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION, 'form-scoring');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Scoring normalizer') . ':</td><td>';
	show_normalizer_select($club_id, $normalizer_id, $normalizer_version, 'form-normalizer');
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
	echo '<input type="checkbox" id="form-long_term" onclick="longTermClicked()"> '.get_label('long term tournament. Like a seasonal club championship.').'<br>';
	echo '<input type="checkbox" id="form-single_game" onclick="singleGameClicked()"> '.get_label('single games from non-tournament events can be assigned to the tournament.').'<br>';
	echo '<input type="checkbox" id="form-use_rounds_scoring"> '.get_label('scoring rules can be custom in tournament rounds.').'<br>';
	echo '</table>';
	
	$figm_id = 0;
	$query = new DbQuery('SELECT id FROM scorings where club_id IS NULL AND name="ФИИМ"');
	if ($row = $query->next())
	{
		list($figm_id) = $row;
	}
	
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	function onMinDateChange()
	{
		$('#form-end').attr("min", $('#form-start').val());
		var f = new Date($('#form-start').val());
		var t = new Date($('#form-end').val());
		if (f > t)
		{
			$('#form-end').val($('#form-start').val());
		}
	}
	
	var scoringId = <?php echo $scoring_id; ?>;
	var scoringVersion = <?php echo $scoring_version; ?>;
	var scoringOptions = '<?php echo $scoring_options; ?>';
	function onScoringChange(id, version, options)
	{
		scoringId = id;
		scoringVersion = version;
		scoringOptions = JSON.stringify(options);
	}
	
	function longTermClicked()
	{
		var type = parseInt($('#form-type').val());
		var c = $("#form-long_term").attr('checked') ? true : false;
		$("#form-single_game").prop('checked', c);
		$("#form-use_rounds_scoring").prop('checked', !c);
		$("#form-single_game").prop('disabled', !c || type != 0);
		$("#form-use_rounds_scoring").prop('disabled', c || type != 0);
		$('#form-normalizer-sel').val(c ? $("#form-league").val().split(',')[2] : 0);
		mr.onChangeNormalizer('form-normalizer', 0);
	}
	
	function singleGameClicked()
	{
		var type = parseInt($('#form-type').val());
		var c = $("#form-single_game").attr('checked') ? true : false;
		$("#form-use_rounds_scoring").prop('checked', !c || type != 0);
		$("#form-use_rounds_scoring").prop('disabled', c || type != 0);
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
	
	$("#form-stars").rate(
	{
		max_value: 5,
		step_size: 0.5,
		initial_value: 0,
	});
	
	function onLeagueChange()
	{
		var league = $("#form-league").val().split(',');
		if (!$("#form-long_term").attr('checked'))
		{
			league[2] = 0;
		}
		$('#form-scoring-sel').val(league[1]);
		$('#form-normalizer-sel').val(league[2]);
		mr.onChangeScoring('form-scoring', 0, onScoringChange);
		mr.onChangeNormalizer('form-normalizer', 0);
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
				return;
			case <?php echo TOURNAMENT_TYPE_FIGM_ONE_ROUND; ?>:
				scoringId = <?php echo $figm_id; ?>;
				break;
			case <?php echo TOURNAMENT_TYPE_FIGM_TWO_ROUNDS_FINALS3; ?>:
			case <?php echo TOURNAMENT_TYPE_FIGM_TWO_ROUNDS_FINALS4; ?>:
			case <?php echo TOURNAMENT_TYPE_FIGM_THREE_ROUNDS_FINALS3; ?>:
			case <?php echo TOURNAMENT_TYPE_FIGM_THREE_ROUNDS_FINALS4; ?>:
				scoringId = <?php echo $figm_id; ?>;
				r = true;
				break;
			case <?php echo TOURNAMENT_TYPE_AML_ONE_ROUND; ?>:
				break;
			case <?php echo TOURNAMENT_TYPE_AML_TWO_ROUNDS; ?>:
			case <?php echo TOURNAMENT_TYPE_AML_THREE_ROUNDS; ?>:
				r = true;
				break;
			case <?php echo TOURNAMENT_TYPE_CHAMPIONSHIP; ?>:
				s = l = true;
				break;
			case <?php echo TOURNAMENT_TYPE_SERIES; ?>:
				l = true;
				break;
		}
		
		$("#form-long_term").prop('checked', l).prop('disabled', type != 0);
		$("#form-single_game").prop('checked', s).prop('disabled', !l || type != 0);
		$("#form-use_rounds_scoring").prop('checked', r).prop('disabled', (s && l) || type != 0);
		
		if (scoringId > 0)
		{
			$('#form-scoring-sel').val(scoringId);
			$('#form-scoring-difficulty').prop('checked', false);
			mr.onChangeScoring('form-scoring', 0, onScoringChange);
		}
	}
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		if ($("#form-long_term").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_LONG_TERM; ?>;
		if ($("#form-single_game").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_SINGLE_GAME; ?>;
		if ($("#form-use_rounds_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_USE_ROUNDS_SCORING; ?>;
		
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		
		var league = $("#form-league").val().split(',');
		
		var params =
		{
			op: "create",
			club_id: <?php echo $club_id; ?>,
			league_id: league[0],
			name: $("#form-name").val(),
			type: $('#form-type').val(),
			price: $("#form-price").val(),
			address_id: _addr,
			scoring_id: scoringId,
			scoring_version: scoringVersion,
			scoring_options: scoringOptions,
			normalizer_id: $("#form-normalizer-sel").val(),
			normalizer_version: $("#form-normalizer-ver").val(),
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags,
			stars: $("#form-stars").rate("getValue"),
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