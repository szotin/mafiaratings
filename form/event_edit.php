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
	
	list($club_id, $name, $start_time, $duration, $address_id, $fee, $currency_id, $rules_code, $scoring_id, $scoring_version, $scoring_options, $langs, $notes, $flags, $timezone, $tour_id, $tour_name, $tour_flags, $round_num) = 
		Db::record(get_label('event'), 
			'SELECT e.club_id, e.name, e.start_time, e.duration, e.address_id, e.fee, e.currency_id, e.rules, e.scoring_id, e.scoring_version, e.scoring_options, e.languages, e.notes, e.flags, c.timezone, t.id, t.name, t.flags, e.round ' .
			'FROM events e ' . 
			'JOIN addresses a ON a.id = e.address_id ' . 
			'JOIN cities c ON c.id = a.city_id ' . 
			'LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id ' . 
			'WHERE e.id = ?', $event_id);
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
				'SELECT ncr.name, nct.name, c.rules, c.name, c.langs FROM clubs c ' .
				'JOIN cities ct ON ct.id = c.city_id ' .
				'JOIN countries cr ON cr.id = ct.country_id ' .
				'JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0 ' .
				'JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0 ' .
				'WHERE c.id = ?', $club_id);
	}
	
	$start = get_datetime($start_time, $timezone);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">'.get_label('Event name').':</td><td><input id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="13">';
	start_upload_logo_button($event_id);
	echo get_label('Change logo') . '<br>';
	$event_pic = new Picture(EVENT_PICTURE);
	$event_pic->set($event_id, $name, $flags);
	$event_pic->show(ICONS_DIR, false, 50);
	end_upload_logo_button(EVENT_PIC_CODE, $event_id);
	echo '</td>';
	
	echo '</tr>';
	
	$query = new DbQuery('SELECT id, name FROM tournaments WHERE club_id = ? AND start_time <= ? AND start_time + duration >= ? ORDER BY name', $club_id, $start_time, $start_time);
	echo '<tr><td>' . get_label('Tournament') . ':</td><td><select id="form-tournament" onchange="tournamentChange()">';
	show_option(0, $tour_id, '');
	$tournament_found = is_null($tour_id);
	while ($row = $query->next())
	{
		list($tid, $tname) = $row;
		show_option($tid, $tour_id, $tname);
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
		
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
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
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="'.(is_null($fee)?'':$fee).'" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()"'.(is_null($fee)?' checked':'').'> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option($cid, $currency_id, $cname);
	}
	echo '</select></td></tr>';
		
	$rules_code = upgrade_rules_code($rules_code);
	echo '<tr><td>' . get_label('Rules') . ':</td><td>';
	echo '<select id="form-rules">';
	if (show_option(upgrade_rules_code($club->rules_code), $rules_code, $club->name))
	{
		$rules_code = '';
	}
	$query = new DbQuery('SELECT l.name, c.rules FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER BY l.name', $club_id);
	while ($row = $query->next())
	{
		list ($league_name, $rules) = $row;
		if (show_option(upgrade_rules_code($rules), $rules_code, $league_name))
		{
			$rules_code = '';
		}
	}
	$query = new DbQuery('SELECT name, rules FROM club_rules WHERE club_id = ? ORDER BY name', $club_id);
	while ($row = $query->next())
	{
		list ($rules_name, $rules) = $row;
		if (show_option(upgrade_rules_code($rules), $rules_code, $rules_name))
		{
			$rules_code = '';
		}
	}
	if (!empty($rules_code))
	{
		show_option($rules_code, $rules_code, get_label('Custom...'));
	}
	echo '</select></td></tr>';
	
	echo '<tr><td valign="top">' . get_label('Scoring system').':</td><td>';
	show_scoring_select($club_id, $scoring_id, $scoring_version, 0, 0, json_decode($scoring_options), '<br>', 'onScoringChange', SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_NORMALIZER, 'form-scoring');
	echo '</td></tr>';
	
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
	
	echo '<br><input type="checkbox" id="form-pin"';
	if (($flags & EVENT_FLAG_PINNED) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('pin to the main page.');
	
	echo '<br><input type="checkbox" id="form-streaming"';
	if (($flags & EVENT_FLAG_STREAMING) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('video streaming games.');
	
	echo '</td></tr>';	
	echo '</table>';
	
?>	
	<script>
	var scoringId = <?php echo $scoring_id; ?>;
	var scoringVersion = <?php echo $scoring_version; ?>;
	var scoringOptions = '<?php echo $scoring_options; ?>';
	function onScoringChange(s)
	{
		scoringId = s.sId;
		scoringVersion = s.sVer;
		scoringOptions = JSON.stringify(s.ops);
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
			
			$("#form-scoring-group-div").show();
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
			roundHtml = '<input type="hidden" id="form-round">';
			roundVal = 0;
			
			$("#form-scoring-group").val('');
			mr.onChangeScoring('form-scoring', 0, onScoringChange);
			$("#form-scoring-group-div").hide();
			$("#form-rules").prop('disabled', false);
			$("#form-scoring-sel").prop('disabled', false);
			$("#form-scoring-ver").prop('disabled', false);
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
		
		var _flags = 0;
		if ($("#form-all_mod").attr('checked')) _flags |= <?php echo EVENT_FLAG_ALL_CAN_REFEREE; ?>;
		if ($("#form-fun").attr('checked')) _flags |= <?php echo EVENT_FLAG_FUN; ?>;
		if ($("#form-pin").attr('checked')) _flags |= <?php echo EVENT_FLAG_PINNED; ?>;
		if ($("#form-streaming").attr('checked')) _flags |= <?php echo EVENT_FLAG_STREAMING; ?>;
		
		var params =
		{
			op: "change"
			, event_id: <?php echo $event_id; ?>
			, tournament_id: $("#form-tournament").val()
			, round_num: $("#form-round").val()
			, name: $("#form-name").val()
			, start: $('#form-date').val() + 'T' + timeStr($('#form-time').val())
			, duration: strToTimespan($("#form-duration").val())
			, fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val())
			, currency_id: $('#form-currency').val()
			, address_id: _addr
			, rules_code: $("#form-rules").val()
			, scoring_id: scoringId
			, scoring_version: scoringVersion
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