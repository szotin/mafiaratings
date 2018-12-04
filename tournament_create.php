<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/tournament.php';
require_once 'include/timespan.php';
require_once 'include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('tournament')));

	$club_id = 0;
	$league_id = 0;
	if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		$club = $_profile->clubs[$club_id];
		$def_rules_id = $club->rules_id;
		$def_country = $club->country;
		$def_city = $club->country;
		$def_langs = $club->langs;
	}
	else if (isset($_REQUEST['league_id']))
	{
		$league_id = (int)$_REQUEST['league_id'];
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value=""></td></tr>';
	
	if ($club_id > 0)
	{
	}
	else
	{
		echo '<tr><td>' . get_label('Club') . ':</td><td><select id="form-club"></select></td></tr>';
	}
	
	if ($league_id > 0)
	{
		echo '<input type="hidden" id="form-league" value="' . $league_id . '">';
	}
	else
	{
		echo '<tr><td>' . get_label('League') . ':</td><td><select id="form-league"></select></td></tr>';
	}
	
	// $query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDR_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
	// echo '<tr><td>'.get_label('Address').':</td><td>';
	// echo '<select id="form-addr_id" onChange="addressClick()">';
	// echo '<option value="-1">' . get_label('New address') . '</option>';
	// $selected_address = '';
	// while ($row = $query->next())
	// {
		// if (show_option($row[0], $def_addr_id, $row[1]))
		// {
			// $selected_address = $row[1];
		// }
	// }
	// echo '</select><div id="form-new_addr_div">';
// //	echo '<button class="icon" onclick="mr.createAddr(' . $club_id . ')" title="' . get_label('Create [0]', get_label('address')) . '"><img src="images/create.png" border="0"></button>';
	// echo '<input id="form-new_addr" onkeyup="newAddressChange()"> ';
	// show_country_input('form-country', $def_country, 'form-city');
	// echo ' ';
	// show_city_input('form-city', $def_city, 'form-country');
	// echo '</div></td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input id="form-price" value=""></td></tr>';
	
	if ($club_id > 0)
	{
		$query = new DbQuery('SELECT rules_id, name FROM club_rules WHERE club_id = ? ORDER BY name', $club_id);
		if ($row = $query->next())
		{
			$custom_rules = true;
			echo '<tr><td>' . get_label('Game rules') . ':</td><td><select id="form-rules"><option value="' . $def_rules_id . '"';
			if ($def_rules_id == $def_rules_id)
			{
				echo ' selected';
				$custom_rules = false;
			}
			echo '>' . get_label('[default]') . '</option>';
			do
			{
				list ($rules_id, $rules_name) = $row;
				echo '<option value="' . $rules_id . '"';
				if ($custom_rules && $rules_id == $def_rules_id)
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
			echo '<input type="hidden" id="form-rules" value="' . $def_rules_id . '">';
		}
	}
	
	if (is_valid_lang($def_langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $def_langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes($def_langs, $def_langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
		
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
		
	// echo '<tr><td colspan="2">';
	// echo '<input type="checkbox" id="form-reg_att"';
	// if (($tournament->flags & tournament_FLAG_REG_ON_ATTEND) != 0)
	// {
		// echo ' checked';
	// }
	// echo '> '.get_label('allow users to register for the tournament when they click Attend button').'<br>';
		
	// echo '<input type="checkbox" id="form-pwd_req"';
	// if (($tournament->flags & tournament_FLAG_PWD_REQUIRED) != 0)
	// {
		// echo ' checked';
	// }
	// echo '> '.get_label('user password is required when moderator is registering him for this tournament.').'<br>';

	// echo '<input type="checkbox" id="form-all_mod"';
	// if (($tournament->flags & tournament_FLAG_ALL_MODERATE) != 0)
	// {
		// echo ' checked';
	// }
	// echo '> '.get_label('everyone can moderate games.').'</td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		
		var params =
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, name: $("#form-name").val()
			, price: $("#form-price").val()
			, address_id: _addr
			, rules_id: $("#form-rules").val()
			, scoring_id: $("#form-scoring").val()
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