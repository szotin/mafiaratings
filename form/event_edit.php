<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/event.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';
require_once '../include/image.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('event')));

	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	list($club_id, $name, $start_time, $duration, $address_id, $price, $rules_code, $scoring_id, $scoring_weight, $planned_games, $langs, $notes, $flags, $timezone) = 
		Db::record(get_label('event'), 
			'SELECT e.club_id, e.name, e.start_time, e.duration, e.address_id, e.price, e.rules, e.scoring_id, e.scoring_weight, e.planned_games, e.languages, e.notes, e.flags, c.timezone ' .
			'FROM events e ' . 
			'JOIN addresses a ON a.id = e.address_id ' . 
			'JOIN cities c ON c.id = a.city_id ' . 
			'WHERE e.id = ?', $event_id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	$club = $_profile->clubs[$club_id];
	
	$start = get_datetime($start_time, $timezone);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">'.get_label('Event name').':</td><td><input id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="12">';
	show_event_pic($event_id, $name, $flags, $club_id, $club->name, $club->flags, ICONS_DIR);
	echo '<p>';
	show_upload_button();
	echo '</p></td>';
	
	echo '</tr>';
	
	echo '<tr><td>'.get_label('Date').':</td><td>';
	echo '<input type="text" id="form-date" value="' . datetime_to_string($start, false) . '">';
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Time').':</td><td>';
	echo '<input id="form-hour" value="' . $start->format('H') . '"> : <input id="form-minute" value="' . $start->format('i') . '">';
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Duration').':</td><td><input value="' . timespan_to_string($duration) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-duration" onkeyup="checkDuration()"></td></tr>';
		
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDR_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id" onChange="addressClick()">';
	echo '<option value="-1">' . get_label('New address') . '</option>';
	$selected_address = '';
	while ($row = $query->next())
	{
		if (show_option($row[0], $address_id, $row[1]))
		{
			$selected_address = $row[1];
		}
	}
	echo '</select><div id="form-new_addr_div">';
	echo '<input id="form-new_addr" onkeyup="newAddressChange()"> ';
	show_country_input('form-country', $club->country, 'form-city');
	echo ' ';
	show_city_input('form-city', $club->city, 'form-country');
	echo '</span></td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input id="form-price" value="' . $price . '"></td></tr>';
		
	$query = new DbQuery('SELECT rules, name FROM club_rules WHERE club_id = ? ORDER BY name', $club_id);
	if ($row = $query->next())
	{
		$custom_rules = true;
		echo '<tr><td>' . get_label('Game rules') . ':</td><td><select id="form-rules"><option value="' . $club->rules_code . '"';
		if ($club->rules_code == $rules_code)
		{
			echo ' selected';
			$custom_rules = false;
		}
		echo '>' . $club->name . '</option>';
		do
		{
			list ($rules_code, $rules_name) = $row;
			echo '<option value="' . $rules_code . '"';
			if ($custom_rules && $rules_code == $rules_code)
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
	
	echo '<tr><td>' . get_label('Scoring system').':</td><td>';
	show_scoring_select($club_id, $scoring_id, '', get_label('Scoring system for [0]', $name), 'form-scoring', false);
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Scoring weight').':</td><td><input id="form-scoring-weight" value="' . $scoring_weight . '"></td></tr>';
	
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
		
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="50" rows="4">' . htmlspecialchars($notes, ENT_QUOTES) . '</textarea></td></tr>';
		
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-reg_att"';
	if (($flags & EVENT_FLAG_REG_ON_ATTEND) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('allow users to register for the event when they click Attend button').'<br>';
		
	echo '<input type="checkbox" id="form-pwd_req"';
	if (($flags & EVENT_FLAG_PWD_REQUIRED) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('user password is required when moderator is registering him for this event.').'<br>';

	echo '<input type="checkbox" id="form-all_mod"';
	if (($flags & EVENT_FLAG_ALL_MODERATE) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('everyone can moderate games.').'</td></tr>';
	
	echo '</table>';
	
	show_upload_script(EVENT_PIC_CODE, $event_id);
?>	
	<script>
	var dateFormat = "<?php echo JS_DATETIME_FORMAT; ?>";
	var startDate = $('#form-date').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	$("#form-hour").spinner({ step:1, max:23, min:0 }).width(16);
	$("#form-minute").spinner({ step:10, max:50, min:0, numberFormat: "d2" }).width(16);
	$("#form-scoring-weight").spinner({ step:0.1, min:0.1 }).width(40);
	
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
	
	function eventGamesChange()
	{
		if ($('#form-planned_games').val() <= 0)
		{
			$('#form-planned_games').val('');
		}
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
		if ($("#form-reg_att").attr('checked')) _flags |= <?php echo EVENT_FLAG_REG_ON_ATTEND; ?>;
		if ($("#form-pwd_req").attr('checked')) _flags |= <?php echo EVENT_FLAG_PWD_REQUIRED; ?>;
		if ($("#form-all_mod").attr('checked')) _flags |= <?php echo EVENT_FLAG_ALL_MODERATE; ?>;
		
		var _start = $('#form-date').val() + ' ' + timeStr($('#form-hour').val()) + ':' + timeStr($('#form-minute').val());
		
		var params =
		{
			op: "change"
			, event_id: <?php echo $event_id; ?>
			, name: $("#form-name").val()
			, start: _start
			, duration: strToTimespan($("#form-duration").val())
			, price: $("#form-price").val()
			, address_id: _addr
			, rules_code: $("#form-rules").val()
			, scoring_id: $("#form-scoring").val()
			, scoring_weight: $("#form-scoring-weight").val()
			, planned_games: $("#form-planned_games").val()
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
		
		json.post("api/ops/event.php", params, onSuccess);
	}
	
	function checkDuration()
	{
		$("#dlg-ok").button("option", "disabled", strToTimespan($("#form-duration").val()) <= 0);
	}
	
	$('#form-scoring_weight').spinner({ step:0.1, max:100, min:0.1 }).width(30);
	$('#form-planned_games').spinner({ step:1, max:1000, min:0, change:eventGamesChange }).width(30);
	
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