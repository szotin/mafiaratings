<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/event.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	
	dialog_title(get_label('Create [0]', get_label('tournament round')));
	$tournament_id = (int)$_REQUEST['tournament_id'];
	list($club_id, $addr_id, $rules_code, $langs, $scoring_id, $scoring_version, $scoring_options, $country, $city) = 
		Db::record(get_label('tournament'), 'SELECT t.club_id, t.address_id, t.rules, t.langs, t.scoring_id, t.scoring_version, t.scoring_options, c.id, c.country_id' .
		' FROM tournaments t' .
		' JOIN addresses a ON a.id = t.address_id' .
		' JOIN cities c ON c.id = a.city_id' .
		' WHERE t.id = ?', $tournament_id);
	
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
	$start = new DateTime();
	$duration = 6 * 3600;

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">'.get_label('Round name').':</td><td><input id="form-name" value="' . get_label('main round') . '"></td></tr>';
	
	echo '<tr><td>'.get_label('Date').':</td><td>';
	echo '<input type="text" id="form-date" value="' . datetime_to_string($start, false) . '">';
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Time').':</td><td>';
	echo '<input id="form-hour" value="18"> : <input id="form-minute" value="00">';
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Duration').':</td><td><input value="' . timespan_to_string($duration) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-duration" onkeyup="checkDuration()"></td></tr>';
	
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td>';
	show_scoring_select($club_id, $scoring_id, $scoring_version, json_decode($scoring_options), 'onScoringChange', SCORING_SELECT_FLAG_NO_PREFIX, 'form-scoring');
	echo '</td></tr>';
		
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id" onChange="addressClick()">';
	echo '<option value="-1">' . get_label('New address') . '</option>';
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
	show_country_input('form-country', $country, 'form-city');
	echo ' ';
	show_city_input('form-city', $city, 'form-country');
	echo '</span></td></tr>';
	
	if (is_valid_lang($langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes($langs, $langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
		
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
		
	echo '<tr><td colspan="2">';
		
	echo '<input type="checkbox" id="form-all_mod" checked> '.get_label('everyone can moderate games.');
	echo '</td></tr>';
	
	echo '</table>';
?>	
	<script>
	var dateFormat = "<?php echo JS_DATETIME_FORMAT; ?>";
	var date = $('#form-date').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	var fromDate = $('#form-date-from').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { toDate.datepicker("option", "minDate", this.value); });
	var toDate = $('#form-date-to').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	$("#form-hour").spinner({ step:1, max:23, min:0 }).width(40);
	$("#form-minute").spinner({ step:10, max:50, min:0, numberFormat: "d2" }).width(40);
	
	$('#form-scoring-sel').prop("disabled", true);
	$('#form-scoring-ver').prop("disabled", true);
	var scoringOptions = '<?php echo $scoring_options; ?>';
	function onScoringChange(id, version, options)
	{
		scoringOptions = JSON.stringify(options);
	}
	
	var old_address_value = "<?php echo $selected_address; ?>";
	function newAddressChange()
	{
		var text = $("#form-new_addr").val();
		if ($("#form-name").val() == old_address_value)
		{
			$("#form-name").val(text);
		}
		old_address_value = text;
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
		
		if ($("#form-name").val() == old_address_value)
		{
			$("#form-name").val(text);
		}
		old_address_value = text;
	}
	addressClick();
	
	function timeStr(val)
	{
		if (val.length < 2)
		{
			return '0' + val;
		}
		return val;
	}
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		if ($("#form-all_mod").attr('checked')) _flags |= <?php echo EVENT_FLAG_ALL_MODERATE; ?>;
		
		var params =
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, tournament_id: <?php echo $tournament_id; ?>
			, name: $("#form-name").val()
			, duration: strToTimespan($("#form-duration").val())
			, address_id: _addr
			, scoring_weight: $("#form-scoring_weight").val()
			, scoring_options: scoringOptions
			, notes: $("#form-notes").val()
			, flags: _flags
			, langs: _langs
		};
		
		if (_addr <= 0)
		{
			params['address'] = $("#form-new_addr").val();
			params['country'] = $("#form-country").val();
			params['city'] = $("#form-city").val();
		}
		
		var _time = ' ' + timeStr($('#form-hour').val()) + ':' + timeStr($('#form-minute').val());
		if ($('#form-multiple').attr('checked'))
		{
			var weekdays = 0;
			if ($("#form-wd0").attr('checked')) weekdays |= <?php echo WEEK_FLAG_SUN; ?>;
			if ($("#form-wd1").attr('checked')) weekdays |= <?php echo WEEK_FLAG_MON; ?>;
			if ($("#form-wd2").attr('checked')) weekdays |= <?php echo WEEK_FLAG_TUE; ?>;
			if ($("#form-wd3").attr('checked')) weekdays |= <?php echo WEEK_FLAG_WED; ?>;
			if ($("#form-wd4").attr('checked')) weekdays |= <?php echo WEEK_FLAG_THU; ?>;
			if ($("#form-wd5").attr('checked')) weekdays |= <?php echo WEEK_FLAG_FRI; ?>;
			if ($("#form-wd6").attr('checked')) weekdays |= <?php echo WEEK_FLAG_SAT; ?>;
			
			params['weekdays'] = weekdays;
			params['start'] = fromDate.val() + _time;
			params['end'] = toDate.val() + _time;
		}
		else
		{
			params['start'] = date.val() + _time;
		}
		json.post("api/ops/event.php", params, onSuccess);
	}
	
	function checkDuration()
	{
		$("#dlg-ok").button("option", "disabled", strToTimespan($("#form-duration").val()) <= 0);
	}
	
	$('#form-scoring_weight').spinner({ step:0.1, max:100, min:0.1 }).width(30);
	
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