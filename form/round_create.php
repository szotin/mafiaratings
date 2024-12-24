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
	
	$now = isset($_REQUEST['now']) && $_REQUEST['now'];
	
	dialog_title(get_label('Create [0]', get_label('tournament round')));
	$tournament_id = (int)$_REQUEST['tournament_id'];
	list($club_id, $addr_id, $rules_code, $langs, $scoring_id, $scoring_version, $scoring_options, $country, $city) = 
		Db::record(get_label('tournament'), 'SELECT t.club_id, t.address_id, t.rules, t.langs, t.scoring_id, t.scoring_version, t.scoring_options, c.id, c.country_id' .
		' FROM tournaments t' .
		' JOIN addresses a ON a.id = t.address_id' .
		' JOIN cities c ON c.id = a.city_id' .
		' WHERE t.id = ?', $tournament_id);
	
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	
	$start = new DateTime();
	$duration = 6 * 3600;

	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="160">'.get_label('Round type').':</td><td><select id="form-round" onchange="roundChange()">';
	show_option(0, 0, get_label('main round'));
	show_option(1, 0, get_label('final'));
	show_option(2, 0, get_label('semi-final'));
	show_option(3, 0, get_label('quoter-final'));
	echo '</select></td></tr>';
	
	if (!$now)
	{
		echo '<tr><td>'.get_label('Date').':</td><td>';
		echo '<input type="date" id="form-date" value="' . datetime_to_string($start, false) . '">';
		echo '</td></tr>';
			
		echo '<tr><td>'.get_label('Time').':</td><td>';
		echo '<input type="time" id="form-time" value="18:00">';
		echo '</td></tr>';
	}
		
	echo '<tr><td>'.get_label('Duration').':</td><td><input value="' . timespan_to_string($duration) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-duration" onkeyup="checkDuration()"></td></tr>';
	
	echo '<tr><td>'.get_label('Round name').':</td><td><input id="form-name" value="' . get_label('main round') . '"></td></tr>';
	
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td>';
	show_scoring_select($club_id, $scoring_id, $scoring_version, 0, 0, json_decode($scoring_options), '<br>', 'onScoringChange', SCORING_SELECT_FLAG_NO_PREFIX | SCORING_SELECT_FLAG_NO_NORMALIZER, 'form-scoring');
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
		
	echo '<input type="checkbox" id="form-all_mod" checked> '.get_label('everyone can referee games.');
	echo '</td></tr>';
	
	echo '</table>';
?>	
	<script>
	$('#form-scoring-sel').prop("disabled", true);
	$('#form-scoring-ver').prop("disabled", true);
	var scoringOptions = '<?php echo $scoring_options; ?>';
	function onScoringChange(s)
	{
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
	
	var roundVal = 0;
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
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		if ($("#form-all_mod").attr('checked')) _flags |= <?php echo EVENT_FLAG_ALL_CAN_REFEREE; ?>;
		
		var params =
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, tournament_id: <?php echo $tournament_id; ?>
			, round_num: $("#form-round").val()
			, name: $("#form-name").val()
			, duration: strToTimespan($("#form-duration").val())
			, address_id: _addr
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
		
		if ($('#form-date').length)
			params['start'] = $('#form-date').val() + 'T' + timeStr($('#form-time').val());
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