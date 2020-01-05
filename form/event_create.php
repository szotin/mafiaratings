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
	if (isset($_REQUEST['club_id']))
	{
		dialog_title(get_label('Create [0]', get_label('event')));
		$club_id = (int)$_REQUEST['club_id'];
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	
	check_permissions(PERMISSION_CLUB_MEMBER, $club_id);
	$club = $_profile->clubs[$club_id];
	
	$event = new Event();
	$event->set_club($club);
	
	$start = new DateTime();
	$end = new DateTime();
	$end->add(new DateInterval('P2M'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">'.get_label('Event name').':</td><td><input id="form-name" value="' . htmlspecialchars($event->name, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td>' . get_label('Tournament') . ':</td><td><select id="form-tournament" onchange="tournamentChange()">';
	show_option(0, $event->tournament_id, '');
	$query = new DbQuery('SELECT id, name FROM tournaments WHERE club_id = ? AND start_time + duration > UNIX_TIMESTAMP() ORDER BY name', $club_id);
	if ($row = $query->next())
	{
		list($tid, $tname) = $row;
		show_option($tid, $event->tournament_id, $tname);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>'.get_label('Date').':</td><td>';
	echo '<input type="checkbox" id="form-multiple" onclick="multipleChange()"> ' . get_label('multiple events');
	echo '<div id="form-single_date">';
	echo '<input type="text" id="form-date" value="' . datetime_to_string($start, false) . '">';
	echo '</div><div id="form-multiple_date" style="display:none;">';
	echo '<p>' . get_label('Every') . ': ';
	$weekday_names = array(get_label('sun'), get_label('mon'), get_label('tue'), get_label('wed'), get_label('thu'), get_label('fri'), get_label('sat'));
	for ($i = 0; $i < 7; ++$i)
	{
		echo '<input type="checkbox" id="form-wd' . $i . '"> ' . $weekday_names[$i] . ' ';
	}
	echo '</p>';
	echo '<p>' . get_label('From') . ' ';
	echo '<input type="text" id="form-date-from" value="' . datetime_to_string($start, false) . '">';
	echo ' ' . get_label('to') . ' ';
	echo '<input type="text" id="form-date-to" value="' . datetime_to_string($end, false) . '">';
	echo '</td></tr>';
	echo '</div></td></tr>';
		
	echo '<tr><td>'.get_label('Time').':</td><td>';
	echo '<input id="form-hour" value="18"> : <input id="form-minute" value="00">';
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Duration').':</td><td><input value="' . timespan_to_string($event->duration) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-duration" onkeyup="checkDuration()"></td></tr>';
		
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY name', $event->club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id" onChange="addressClick()">';
	echo '<option value="-1">' . get_label('New address') . '</option>';
	$selected_address = '';
	while ($row = $query->next())
	{
		if (show_option($row[0], $event->addr_id, $row[1]))
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
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input id="form-price" value="' . $event->price . '"></td></tr>';
		
	$query = new DbQuery('SELECT rules, name FROM club_rules WHERE club_id = ? ORDER BY name', $event->club_id);
	if ($row = $query->next())
	{
		$custom_rules = true;
		echo '<tr><td>' . get_label('Game rules') . ':</td><td><select id="form-rules"><option value="' . $club->rules_code . '"';
		if ($club->rules_code == $event->rules_code)
		{
			echo ' selected';
			$custom_rules = false;
		}
		echo '>' . $club->name . '</option>';
		do
		{
			list ($rules_code, $rules_name) = $row;
			echo '<option value="' . $rules_code . '"';
			if ($custom_rules && $rules_code == $event->rules_code)
			{
				echo ' selected';
			}
			echo '>' . $rules_name . '</option>';
		} while ($row = $query->next());
		echo '</select>';
		echo '</td></tr>';
	}
	else
	{
		echo '<input type="hidden" id="form-rules" value="' . $club->rules_code . '">';
	}
	
	echo '<tr><td>' . get_label('Scoring system') . '</td><td>';
	show_scoring_select($event->club_id, $event->scoring_id, $event->scoring_version, '', 'form-scoring', false);
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Scoring weight').':</td><td><input id="form-scoring-weight" value="' . $event->scoring_weight . '"></td></tr>';
	
	if (is_valid_lang($club->langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $club->langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes($event->langs, $club->langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
		
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4">' . htmlspecialchars($event->notes, ENT_QUOTES) . '</textarea></td></tr>';
		
	echo '<tr><td colspan="2">';
		
	echo '<input type="checkbox" id="form-all_mod"';
	if (($event->flags & EVENT_FLAG_ALL_MODERATE) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('everyone can moderate games.');
	
	echo '<br><input type="checkbox" id="form-fun"';
	if (($event->flags & EVENT_FLAG_FUN) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('non-rating event.');
	echo '</td></tr>';
	
	echo '</table>';
	
	echo '<table class="transp" width="100%"><tr>';
	echo '<td align="right">';
	$query = new DbQuery(
		'SELECT e.id, e.name, e.start_time, c.timezone FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' . 
			' JOIN cities c ON a.city_id = c.id' . 
			' WHERE e.club_id = ?' .
			' AND e.start_time < UNIX_TIMESTAMP() AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_HIDDEN_AFTER) . ') = 0 ORDER BY e.start_time DESC LIMIT 30',
		$event->club_id);
	echo get_label('Copy event data from') . ': <select id="form-copy" onChange="copyEvent()"><option value="0"></option>';
	while ($row = $query->next())
	{
		echo '<option value="' . $row[0] . '">';
		echo $row[1] . format_date(' D F d H:i', $row[2], $row[3]);
		echo '</option>';
	}
	echo '</select>';
	echo '</td></tr></table>';
?>	
	<script>
	var dateFormat = "<?php echo JS_DATETIME_FORMAT; ?>";
	var date = $('#form-date').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	var fromDate = $('#form-date-from').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { toDate.datepicker("option", "minDate", this.value); });
	var toDate = $('#form-date-to').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	$("#form-hour").spinner({ step:1, max:23, min:0 }).width(40);
	$("#form-minute").spinner({ step:10, max:50, min:0, numberFormat: "d2" }).width(40);
	$('#form-scoring-weight').spinner({ step:0.1, min:0.1 }).width(40);
	
	function multipleChange()
	{
		if ($('#form-multiple').attr('checked'))
		{
			$('#form-multiple_date').show();
			$('#form-single_date').hide();
		}
		else
		{
			$('#form-multiple_date').hide();
			$('#form-single_date').show();
		}
	}
	
	function tournamentChange()
	{
		var tid = $("#form-tournament").val();
		if (tid > 0)
		{
			json.get("api/get/tournaments.php?tournament_id=" + tid, function(obj)
			{
				var t = obj.tournaments[0];
				if (typeof t != "object")
					return;
				$("#form-rules").val(t.rules.code).prop('disabled', true);
				$("#form-scoring-sel").val(t.scoring_id).prop('disabled', true);
				$("#form-scoring-ver").val(t.scoring_version).prop('disabled', true);
				//console.log(t);
			});
		}
		else
		{
			$("#form-rules").prop('disabled', false);
			$("#form-scoring-sel").prop('disabled', false);
			$("#form-scoring-ver").prop('disabled', false);
		}
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
	
	function copyEvent()
	{
		json.get("api/ops/event.php?op=get&event_id=" + $("#form-copy").val(), function(e)
		{
			$("#form-name").val(e.name);
			$("#form-hour").val(e.hour);
			$("#form-minute").val(e.minute);
			$("#form-tournament").val(e.tournament_id);
			$("#form-duration").val(timespanToStr(e.duration));
			$("#form-addr_id").val(e.addr_id);
			$("#form-price").val(e.price);
			$("#form-rules").val(e.rules_code);
			$("#form-scoring-sel").val(e.scoring_id);
			$("#form-scoring-ver").val(e.scoring_version);
			$('#form-scoring-weight').val(e.scoring_weight);
			$("#form-notes").val(e.notes);
			$("#form-all_mod").prop('checked', (e.flags & <?php echo EVENT_FLAG_ALL_MODERATE; ?>) != 0);
			$("#form-fun").prop('checked', (e.flags & <?php echo EVENT_FLAG_FUN; ?>) != 0);
			mr.setLangs(e.langs, "form-");
			addressClick();
		});
		$("#form-copy").val(0);
	}
	
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
		if ($("#form-fun").attr('checked')) _flags |= <?php echo EVENT_FLAG_FUN; ?>;
		
		var params =
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, tournament_id: $("#form-tournament").val()
			, name: $("#form-name").val()
			, duration: strToTimespan($("#form-duration").val())
			, price: $("#form-price").val()
			, address_id: _addr
			, rules_code: $("#form-rules").val()
			, scoring_id: $("#form-scoring-sel").val()
			, scoring_version: $("#form-scoring-ver").val()
			, scoring_weight: $("#form-scoring-weight").val()
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