<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/event.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('event')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];
	if ($_profile == NULL || !$_profile->is_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$club = $_profile->clubs[$club_id];
	$event = new Event();
	$event->set_club($club);

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">'.get_label('Event name').':</td><td><input id="form-name" value="' . htmlspecialchars($event->name, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td>'.get_label('Date').':</td><td>';
	echo '<input type="checkbox" id="form-multiple" onclick="multipleChange()"> ' . get_label('multiple events');
	echo '<div id="form-single_date">';
	show_date_controls($event->day, $event->month, $event->year, 'form-');
	echo '</div><div id="form-multiple_date" style="display:none;">';
	echo '<p>' . get_label('Every') . ': ';
	$weekday_names = array(get_label('sun'), get_label('mon'), get_label('tue'), get_label('wed'), get_label('thu'), get_label('fri'), get_label('sat'));
	for ($i = 0; $i < 7; ++$i)
	{
		echo '<input type="checkbox" id="form-wd' . $i . '"> ' . $weekday_names[$i] . ' ';
	}
	echo '</p>';
	echo '<p>' . get_label('From') . ' ';
	show_date_controls($event->day, $event->month, $event->year, 'form-from_');
	echo ' ' . get_label('to') . ' ';
	show_date_controls($event->day, $event->month, $event->year, 'form-to_');
	echo '</td></tr>';
	echo '</div></td></tr>';
		
	echo '<tr><td>'.get_label('Time').':</td><td>';
	show_time_controls($event->hour, $event->minute, 'form-');
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Duration').':</td><td><select id="form-duration">';
	for ($i = 1; $i <= 12; ++$i)
	{
		show_option($i * 3600, $event->duration, $i);
	}
	for ($i = 24; $i <= 120; $i += 24)
	{
		show_option($i * 3600, $event->duration, $i);
	}
	echo '</select> '.get_label('hours').'</td></tr>';
		
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDR_FLAG_NOT_USED . ') = 0 ORDER BY name', $event->club_id);
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
		
	$query = new DbQuery('SELECT rules_id, name FROM club_rules WHERE club_id = ? ORDER BY name', $event->club_id);
	if ($row = $query->next())
	{
		$custom_rules = true;
		echo '<tr><td>' . get_label('Game rules') . ':</td><td><select id="form-rules"><option value="' . $club->rules_id . '"';
		if ($club->rules_id == $event->rules_id)
		{
			echo ' selected';
			$custom_rules = false;
		}
		echo '>' . get_label('[default]') . '</option>';
		do
		{
			list ($rules_id, $rules_name) = $row;
			echo '<option value="' . $rules_id . '"';
			if ($custom_rules && $rules_id == $event->rules_id)
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
		echo '<input type="hidden" id="form-rules" value="' . $club->rules_id . '">';
	}
	
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $event->club_id);
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td><select id="form-scoring">';
	while ($row = $query->next())
	{
		list ($scoring_id, $scoring_name) = $row;
		echo '<option value="' . $scoring_id . '"';
		if ($scoring_id == $event->scoring_id)
		{
			echo ' selected';
		}
		echo '>' . $scoring_name . '</option>';
	} 
	echo '</select></td></tr>';
	
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
	echo '<input type="checkbox" id="form-reg_att"';
	if (($event->flags & EVENT_FLAG_REG_ON_ATTEND) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('allow users to register for the event when they click Attend button').'<br>';
		
	echo '<input type="checkbox" id="form-pwd_req"';
	if (($event->flags & EVENT_FLAG_PWD_REQUIRED) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('user password is required when moderator is registering him for this event.').'<br>';

	echo '<input type="checkbox" id="form-all_mod"';
	if (($event->flags & EVENT_FLAG_ALL_MODERATE) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('everyone can moderate games.').'</td></tr>';
	
	echo '</table>';
	
	echo '<table class="transp" width="100%"><tr>';
	echo '<td align="right">';
	$query = new DbQuery(
		'SELECT e.id, e.name, e.start_time, c.timezone FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' . 
			' JOIN cities c ON a.city_id = c.id' . 
			' WHERE e.club_id = ?' .
			' AND e.start_time < UNIX_TIMESTAMP() ORDER BY e.start_time DESC LIMIT 30',
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
		json.get("event_ops.php?get&id=" + $("#form-copy").val(), function(json)
		{
			$("#form-name").val(json.name);
			$("#form-hour").val(json.hour);
			$("#form-minute").val(json.minute);
			$("#form-duration").val(json.duration);
			$("#form-addr_id").val(json.addr_id);
			$("#form-price").val(json.price);
			$("#form-rules").val(json.rules_id);
			$("#form-scoring").val(json.scoring_id);
			$("#form-notes").val(json.notes);
			$("#form-reg_att").prop('checked', (json.flags & <?php echo EVENT_FLAG_REG_ON_ATTEND; ?>) != 0);
			$("#form-pwd_req").prop('checked', (json.flags & <?php echo EVENT_FLAG_PWD_REQUIRED; ?>) != 0);
			$("#form-all_mod").prop('checked', (json.flags & <?php echo EVENT_FLAG_ALL_MODERATE; ?>) != 0);
			mr.setLangs(json.langs, "form-");
			addressClick();
		});
		$("#form-copy").val(0);
	}
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		if ($("#form-reg_att").attr('checked')) _flags |= <?php echo EVENT_FLAG_REG_ON_ATTEND; ?>;
		if ($("#form-pwd_req").attr('checked')) _flags |= <?php echo EVENT_FLAG_PWD_REQUIRED; ?>;
		if ($("#form-all_mod").attr('checked')) _flags |= <?php echo EVENT_FLAG_ALL_MODERATE; ?>;
		
		var params =
		{
			id: <?php echo $club_id; ?>,
			name: $("#form-name").val(),
			hour: $("#form-hour").val(),
			minute: $("#form-minute").val(),
			duration: $("#form-duration").val(),
			price: $("#form-price").val(),
			addr: _addr,
			rules: $("#form-rules").val(),
			scoring: $("#form-scoring").val(),
			notes: $("#form-notes").val(),
			flags: _flags,
			langs: _langs,
			new_event: ''
		};
		
		if (_addr <= 0)
		{
			params['new_addr'] = $("#form-new_addr").val();
			params['country'] = $("#form-country").val();
			params['city'] = $("#form-city").val();
		}
		
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
			params['month'] = $("#form-from_month").val();
			params['day'] = $("#form-from_day").val();
			params['year'] = $("#form-from_year").val();
			params['to_month'] = $("#form-to_month").val();
			params['to_day'] = $("#form-to_day").val();
			params['to_year'] = $("#form-to_year").val();
		}
		else
		{
			params['month'] = $("#form-month").val();
			params['day'] = $("#form-day").val();
			params['year'] = $("#form-year").val();
		}
		
		json.post("club_ops.php", params, onSuccess);
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