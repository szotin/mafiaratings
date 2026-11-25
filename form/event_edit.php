<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/event.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';
require_once '../include/names.php';
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
	
	list($club_id, $name_id, $name, $start_time, $duration, $address_id, $fee, $currency_id, $rules_code, $scoring_id, $scoring_version, $scoring_options, $langs, $notes, $flags, $timezone, $tour_id, $tour_name, $tour_flags, $round_num) = 
		Db::record(get_label('event'), 
			'SELECT e.club_id, e.name_id, ne.name, e.start_time, e.duration, e.address_id, e.fee, e.currency_id, e.rules, e.scoring_id, e.scoring_version, e.scoring_options, e.languages, e.notes, e.flags, c.timezone, t.id, nt.name, t.flags, e.round ' .
			'FROM events e ' . 
			'JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0 ' . 
			'JOIN addresses a ON a.id = e.address_id ' . 
			'JOIN cities c ON c.id = a.city_id ' . 
			'LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id ' . 
			'LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0 ' .
			'WHERE e.id = ?', $event_id);
	$tour_id = (int)$tour_id;
	$address_id = (int)$address_id;
	$currency_id = (int)$currency_id;
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tour_id);
	if (isset($_profile->clubs[$club_id]))
	{
		$club = $_profile->clubs[$club_id];
	}
	else
	{
		$club = new stdClass();
		list ($club->country, $club->city, $club->rules_code, $club->name, $club->langs) = 
			Db::record(get_label('club'), 
				'SELECT ncr.name, nct.name, c.rules, nc.name, c.langs FROM clubs c ' .
				'JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0 ' .
				'JOIN cities ct ON ct.id = c.city_id ' .
				'JOIN countries cr ON cr.id = ct.country_id ' .
				'JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0 ' .
				'JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0 ' .
				'WHERE c.id = ?', $club_id);
	}
	
	$start = get_datetime($start_time, $timezone);
	
	echo '<table class="dialog_form" width="100%">';
	$names = new Names($name_id, get_label('event name'));
	echo '<tr><td width="160" valign="top">'.get_label('Event name').':</td><td>';
	Names::show_control($names);
	echo '</td>';
	
	echo '<td align="center" valign="top" rowspan="13">';
	start_upload_logo_button($event_id);
	echo get_label('Change logo') . '<br>';
	$event_pic = new Picture(EVENT_PICTURE);
	$event_pic->set($event_id, $name, $flags);
	$event_pic->show(ICONS_DIR, false, 50);
	end_upload_logo_button(EVENT_PIC_CODE, $event_id);
	echo '</td>';
	
	echo '</tr>';
	
	$query = new DbQuery('SELECT t.id, n.name FROM tournaments t JOIN names n ON n.id = t.name_id AND (n.langs & '.$_lang.') <> 0 WHERE t.club_id = ? AND t.start_time <= ? AND t.start_time + t.duration >= ? ORDER BY n.name', $club_id, $start_time, $start_time);
	echo '<tr><td>' . get_label('Tournament') . ':</td><td><select id="form-tournament" onchange="tournamentChange()">';
	show_option(0, $tour_id, '');
	$tournament_found = is_null($tour_id);
	while ($row = $query->next())
	{
		list($tid, $tname) = $row;
		show_option((int)$tid, $tour_id, $tname);
		if ($tour_id == $tid)
		{
			$tournament_found = true;
		}
	}
	if (!$tournament_found)
	{
		show_option($tour_id, $tour_id, $tour_name);
	}
	echo '</select> <span id="form-round-span"></span></td></tr>';
	
	echo '<tr><td>'.get_label('Date').':</td><td>';
	echo '<input type="date" id="form-date" value="' . datetime_to_string($start, false) . '">';
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Time').':</td><td>';
	echo '<input type="time" id="form-time" value="' . $start->format('H:i') . '">';
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Duration').':</td><td><input value="' . timespan_to_string($duration) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-duration" onkeyup="checkDuration()"></td></tr>';
		
	$query = new DbQuery('SELECT a.id, n.name FROM addresses a JOIN names n ON n.id = a.name_id AND (n.langs & '.$_lang.') <> 0 WHERE a.club_id = ? AND (a.flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY n.name', $club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id" onChange="addressClick()">';
	echo '<option value="-1">' . get_label('New address') . '</option>';
	$selected_address = '';
	while ($row = $query->next())
	{
		if (show_option((int)$row[0], $address_id, $row[1]))
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
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="'.(is_null($fee)?'':$fee).'" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()"'.(is_null($fee)?' checked':'').'> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option((int)$cid, $currency_id, $cname);
	}
	echo '</select></td></tr>';
		
	// The rules, the scoring and the special awards are set on the Rules tab of the preparation
	// page, each saved the moment it is changed. They are not here any more - see
	// event_preparation.php.
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
		
	echo '<input type="checkbox" id="form-all_mod"';
	if (($flags & EVENT_FLAG_ALL_CAN_REFEREE) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('everyone can referee games.');
	
	echo '<br><input type="checkbox" id="form-fun"';
	if (($flags & EVENT_FLAG_FUN) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('non-rating event.');
	
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<br><input type="checkbox" id="form-pin"';
		if (($flags & EVENT_FLAG_PINNED) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('pin to the main page.');
	}
	
	// Whether the games are streamed is set on the streams page of the event, in the broadcast
	// section, where it stands next to the roles - see streams.php.
	echo '<br><input type="checkbox" id="form-show-roles"';
	if (($flags & EVENT_FLAG_HIDE_ROLES) == 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('show the roles in the stream.');

	echo '</td></tr>';
	echo '</table>';
	
?>	
	<script>
	var eventNames = <?php echo $names->to_js(); ?>;
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
	
	var roundVal = <?php echo $round_num; ?>;
	function tournamentChange()
	{
		var tid = $("#form-tournament").val();
		var roundHtml = "";
		if (tid > 0)
		{
			roundHtml = '<select id="form-round" onchange="roundChange()">';
			roundHtml += '<option value="0"' + (roundVal == 0 ? ' selected' : '') + '><?php echo get_label('main round'); ?></option>';
			roundHtml += '<option value="1"' + (roundVal == 1 ? ' selected' : '') + '><?php echo get_label('final'); ?></option>';
			roundHtml += '<option value="2"' + (roundVal == 2 ? ' selected' : '') + '><?php echo get_label('semi-final'); ?></option>';
			roundHtml += '<option value="3"' + (roundVal == 3 ? ' selected' : '') + '><?php echo get_label('quoter-final'); ?></option>';
			roundHtml += '</select>';
		}
		else
		{
			roundHtml = '<input type="hidden" id="form-round">';
			roundVal = 0;
		}
		$("#form-round-span").html(roundHtml);
	}
	tournamentChange();
	
	function roundName()
	{
		if (roundVal == 0)
			return "<?php echo get_label('main round'); ?>";
		else if (roundVal == 1)
			return "<?php echo get_label('final'); ?>";
		else if (roundVal == 2)
			return "<?php echo get_label('semi-final'); ?>";
		else if (roundVal == 3)
			return "<?php echo get_label('quoter-final'); ?>";
		return "";
	}
	
	function roundChange()
	{
		var n = roundName();
		var n1 = $("#form-name").val();
		roundVal = $("#form-round").val();
		if (n == n1 || n1 == "")
			$("#form-name").val(roundName());
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

		// An event is one sitting. An event that is already longer - a few old ones are, and a
		// round of a tournament played over a week is - can be kept as it is or shortened, just
		// never made longer still.
		var _duration = strToTimespan($("#form-duration").val());
		if (_duration > <?php echo EVENT_MAX_DURATION; ?> && _duration > <?php echo (int)$duration; ?>)
		{
			dlg.error(<?php echo json_encode(event_too_long_message($tour_id > 0 && ($tour_flags & TOURNAMENT_FLAG_LONG_TERM) == 0)); ?>);
			return;
		}

		// Only the flags this form still has a box for. The awards are set on the Rules tab of
		// the preparation page and the streaming on the streams page, and flags_mask keeps this
		// form from clearing what either of them has set.
		var _flags = 0;
		if ($("#form-all_mod").attr('checked')) _flags |= <?php echo EVENT_FLAG_ALL_CAN_REFEREE; ?>;
		if ($("#form-fun").attr('checked')) _flags |= <?php echo EVENT_FLAG_FUN; ?>;
		if ($("#form-pin").attr('checked')) _flags |= <?php echo EVENT_FLAG_PINNED; ?>;
		if (!$("#form-show-roles").attr('checked')) _flags |= <?php echo EVENT_FLAG_HIDE_ROLES; ?>;

		var params =
		{
			op: "change"
			, event_id: <?php echo $event_id; ?>
			, tournament_id: $("#form-tournament").val()
			, round_num: $("#form-round").val()
			, start: $('#form-date').val() + 'T' + timeStr($('#form-time').val())
			, duration: _duration
			, fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val())
			, currency_id: $('#form-currency').val()
			, address_id: _addr
			, notes: $("#form-notes").val()
			, flags: _flags
			, flags_mask: <?php echo EVENT_FLAG_ALL_CAN_REFEREE | EVENT_FLAG_FUN | EVENT_FLAG_PINNED | EVENT_FLAG_HIDE_ROLES; ?>
			, langs: _langs
		};
		
		if (_addr <= 0)
		{
			params['address'] = $("#form-new_addr").val();
			params['country'] = $("#form-country").val();
			params['city'] = $("#form-city").val();
		}
		
		// Unchanged names stay the set the event has - one it may share with other events.
		if (nameControl.hasValues(eventNames))
		{
			params['name_id'] = <?php echo (int)$name_id; ?>;
		}
		else
		{
			nameControl.fillRequest(params);
		}
		
		json.post("api/ops/event.php", params, onSuccess);
	}
	
	function checkDuration()
	{
		$("#dlg-ok").button("option", "disabled", strToTimespan($("#form-duration").val()) <= 0);
	}
	
	function uploadLogo(eventId, onSuccess)
	{
		json.upload('api/ops/event.php', 
		{
			op: "change",
			event_id: eventId,
			logo: document.getElementById("upload").files[0]
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