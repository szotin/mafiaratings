<?php

require_once 'include/session.php';
require_once 'include/location.php';

define('UNDEFINED_COUNTRY', -1);
define('ALL_COUNTRIES', 0);

function retrieve_country_id($country)
{
	global $_profile, $_lang_code;

	$country = trim($country);
	if ($country == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('country')));
	}

	$query = new DbQuery('SELECT id FROM countries WHERE name_en = ? OR name_ru = ?', $country, $country);
	if ($row = $query->next())
	{
		list($country_id) = $row;
	}
	else
	{
		Db::exec(get_label('country'), 'INSERT INTO countries (name_en, name_ru, flags, code) VALUES (?, ?, ' . COUNTRY_FLAG_NOT_CONFIRMED . ', \'\')', $country, $country);
		list($country_id) = Db::record(get_label('country'), 'SELECT LAST_INSERT_ID()');
		$log_details =
			'name_en=' . $country .
			"<br>name_ru=" . $country .
			"<br>code=" .
			"<br>flags=" . COUNTRY_FLAG_NOT_CONFIRMED;
		db_log('country', 'Created', $log_details, $country_id);
		
		$query = new DbQuery('SELECT id, name, email FROM users WHERE (flags & ' . U_PERM_ADMIN . ') <> 0 and email <> \'\'');
		while ($row = $query->next())
		{
			list($admin_id, $admin_name, $admin_email) = $row;
			$body =
				'<p>Hi, ' . $admin_name .
				'!</p><p>' . $_profile->user_name .
				' created new country <a href="' . get_server_url() . '/countries.php">' . $country .
				'</a>.</p><p>Please confirm!</p>';
			$text_body =
				'Hi, ' . $admin_name .
				'!\r\n\r\n' . $_profile->user_name .
				' created new country ' . $country . 
				' (' . get_server_url() . 
				'/countries.php).\r\n\r\nPlease confirm!\r\n';
			send_email($admin_email, $body, $text_body, 'New country');
		}
	}
	return $country_id;
}

function detect_country()
{
	global $_profile, $_lang_code;
	
	$loc = Location::get();
	$query = new DbQuery('SELECT name_' . $_lang_code . ' FROM countries WHERE code = ?', $loc->country_code);
	if ($row = $query->next())
	{
		list ($country) = $row;
	}
	else
	{
		$country = $loc->country;
	}
	return $country;
}

define('COUNTRY_FROM_PROFILE', 0);
define('COUNTRY_DETECT', 1);
function show_country_input($name, $value, $city_input = NULL, $on_select = NULL)
{
	global $_profile, $_lang_code;

	if ($value === COUNTRY_FROM_PROFILE)
	{
		if ($_profile != NULL)
		{
			list ($value) = Db::record(get_label('country'), 'SELECT name_' . $_lang_code . ' FROM countries WHERE id = ?', $_profile->country_id);
		}
		else
		{
			$value = detect_country();
		}
	}
	else if ($value === COUNTRY_DETECT)
	{
		$value = detect_country();
		if (($value == '' || $value == '-') && $_profile != NULL)
		{
			list ($value) = Db::record(get_label('country'), 'SELECT name_' . $_lang_code . ' FROM countries WHERE id = ?', $_profile->country_id);
		}
	}

	echo '<input type="text" id="' . $name . '" value="' . $value . '"/>';
	if ($city_input == NULL)
	{
?>
		<script>
		$("#<?php echo $name; ?>").autocomplete(
		{ 
			source: function( request, response )
			{
				$.getJSON("ws_country.php",
				{
					term: $("#<?php echo $name; ?>").val()
				}, response);
			}
			<?php if ($on_select != NULL) echo ', select: function(event, ui) { ' . $on_select . '(); }'; ?>
			, minLength: 0
		})
		.on("focus", function () { $(this).autocomplete("search", ''); });
		</script>
<?php
	}
	else
	{
?>
		<script>
		$("#<?php echo $name; ?>").autocomplete(
		{ 
			source: function( request, response )
			{
				$.getJSON("ws_country.php",
				{
					term: $("#<?php echo $name; ?>").val()
				}, response);
			}
			, select: function(event, ui) { $("#<?php echo $city_input; ?>").val(""); <?php if ($on_select != NULL) echo $on_select . '();'; ?> }
			, minLength: 0
		});
		</script>
<?php
	}
}

function show_country_buttons($id, $name, $flags)
{
	global $_profile;

	if ($_profile != NULL && $_profile->is_admin())
	{
		echo '<button class="icon" onclick="mr.deleteCountry(' . $id . ')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
		if (($flags & COUNTRY_FLAG_NOT_CONFIRMED) != 0)
		{
			echo '<button class="icon" onclick="mr.editCountry(' . $id . ')" title="' . get_label('Confirm [0]', $name) . '"><img src="images/accept.png" border="0"></button>';
		}
		else
		{
			echo '<button class="icon" onclick="mr.editCountry(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
		}
	}
}

?>