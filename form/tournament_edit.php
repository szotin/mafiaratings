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
	
	list ($club_id, $request_league_id, $league_id, $name, $start_time, $duration, $timezone, $stars, $address_id, $scoring_id, $scoring_version, $scoring_options, $price, $langs, $notes, $flags) = 
		Db::record(get_label('tournament'), 'SELECT t.club_id, t.request_league_id, t.league_id, t.name, t.start_time, t.duration, ct.timezone, t.stars, t.address_id, t.scoring_id, t.scoring_version, t.scoring_options, t.price, t.langs, t.notes, t.flags FROM tournaments t' . 
		' JOIN addresses a ON a.id = t.address_id' .
		' JOIN cities ct ON ct.id = a.city_id' .
		' WHERE t.id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	$club = $_profile->clubs[$club_id];
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value="' . $name . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="12" width="120">';
	$tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE));
	$tournament_pic->
		set($tournament_id, $name, $flags)->
		set($club_id, $club->name, $club->flags);
	$tournament_pic->show(ICONS_DIR, 50);
	echo '<p>';
	show_upload_button();
	echo '</p></td></tr>';
	
	echo '<tr><td>' . get_label('League') . ':</td><td><select id="form-league">';
	show_option(0, $request_league_id, '');
	$query = new DbQuery('SELECT l.id, l.name FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER by l.name', $club_id);
	while ($row = $query->next())
	{
		list($lid, $lname) = $row;
		show_option($lid, $request_league_id, $lname);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Stars') . ':</td><td><div id="form-stars" class="stars"></div></td></tr>';
	
	
	$end_time = $start_time + $duration - 24*60*60;
	if ($end_time < $start_time)
	{
		$end_time = $start_time;
	}
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="text" id="form-start" value="' . timestamp_to_string($start_time, $timezone, false) . '">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="text" id="form-end" value="' . timestamp_to_string($end_time, $timezone, false) . '">';
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
	show_scoring_select($club_id, $scoring_id, $scoring_version, json_decode($scoring_options), '<br>', 'onScoringChange', SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION, 'form-scoring');
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
	echo '<input type="checkbox" id="form-long_term" onclick="longTermClicked()"';
	if ($flags & TOURNAMENT_FLAG_LONG_TERM)
	{
		echo ' checked';
	}
	echo '> '.get_label('long term tournament. Like a seasonal club championship.').'<br>';
	
	echo '<input type="checkbox" id="form-single_game" onclick="singleGameClicked()"';
	if (($flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
	{
		echo ' disabled';
	}
	if ($flags & TOURNAMENT_FLAG_SINGLE_GAME)
	{
		echo ' checked';
	}
	echo '> '.get_label('single games from non-tournament events can be assigned to the tournament.').'<br>';
	
	echo '<input type="checkbox" id="form-use_rounds_scoring"';
	if ($flags & TOURNAMENT_FLAG_SINGLE_GAME)
	{
		echo ' disabled';
	}
	if ($flags & TOURNAMENT_FLAG_USE_ROUNDS_SCORING)
	{
		echo ' checked';
	}
	echo '> '.get_label('scoring rules can be custom in tournament rounds.').'<br>';
	
	echo '</td></tr>';
	
	echo '</table>';
	
	show_upload_script(TOURNAMENT_PIC_CODE, $tournament_id);
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	var dateFormat = "<?php echo JS_DATETIME_FORMAT; ?>";
	var startDate = $('#form-start').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { endDate.datepicker("option", "minDate", this.value); });
	var endDate = $('#form-end').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	
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
		var c = $("#form-long_term").attr('checked') ? true : false;
		$("#form-single_game").prop('checked', c);
		$("#form-use_rounds_scoring").prop('checked', !c);
		$("#form-single_game").prop('disabled', !c);
		$("#form-use_rounds_scoring").prop('disabled', c);
	}
	
	function singleGameClicked()
	{
		var c = $("#form-single_game").attr('checked') ? true : false;
		$("#form-use_rounds_scoring").prop('checked', !c);
		$("#form-use_rounds_scoring").prop('disabled', c);
	}
	
	$("#form-stars").rate(
	{
		max_value: 5,
		step_size: 0.5,
		initial_value: <?php echo $stars; ?>,
	});
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _flags = 0;
		if ($("#form-long_term").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_LONG_TERM; ?>;
		if ($("#form-single_game").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_SINGLE_GAME; ?>;
		if ($("#form-use_rounds_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_USE_ROUNDS_SCORING; ?>;
		
		var _end = strToDate(endDate.val());
		_end.setDate(_end.getDate() + 1); // inclusive
		
		var params =
		{
			op: "change",
			tournament_id: <?php echo $tournament_id; ?>,
			league_id: $("#form-league").val(),
			name: $("#form-name").val(),
			price: $("#form-price").val(),
			address_id: $("#form-addr_id").val(),
			scoring_id: scoringId,
			scoring_version: scoringVersion,
			scoring_options: scoringOptions,
			notes: $("#form-notes").val(),
			start: startDate.val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags,
			stars: $("#form-stars").rate("getValue"),
		};
		
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