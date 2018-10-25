<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/email.php';

define('UNDEFINED_CITY', -1);
define('ALL_CITIES', 0);

function retrieve_city_id($city, $country_id, $timezone = NULL)
{
	global $_profile, $_lang_code;
	
	if ($timezone == NULL)
	{
		$timezone = get_timezone();
	}
	
	$city = trim($city);
	if (empty($city))
	{
		throw new Exc(get_label('Please enter [0].', get_label('city')));
	}

	$query = new DbQuery('SELECT id FROM cities WHERE country_id = ? AND (name_en = ? OR name_ru = ?)', $country_id, $city, $city);
	if ($row = $query->next())
	{
		list($city_id) = $row;
	}
	else
	{
		Db::exec(get_label('city'),
			'INSERT INTO cities (country_id, name_en, name_ru, flags, timezone) VALUES (?, ?, ?, ' . CITY_FLAG_NOT_CONFIRMED . ', ?)',
			$country_id, $city, $city, $timezone);
		list($city_id) = Db::record(get_label('city'), 'SELECT LAST_INSERT_ID()');
		Db::exec(get_label('city'), 'INSERT INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $city);
		list ($country_name) = Db::record(get_label('country'), 'SELECT id, name_' . $_lang_code . ' FROM countries WHERE id = ?', $country_id);
		$log_details = 
			'country=' . $country_name . ' (' . $country_id . ')' .
			"<br>name_en=" . $city . 
			"<br>name_ru=" . $city . 
			"<br>timezone=" . $timezone . 
			"<br>flags=" . CITY_FLAG_NOT_CONFIRMED;
		db_log('city', 'Created', $log_details, $city_id);
		
		$query = new DbQuery('SELECT id, name, email FROM users WHERE (flags & ' . USER_PERM_ADMIN . ') <> 0 and email <> \'\'');
		while ($row = $query->next())
		{
			list($admin_id, $admin_name, $admin_email) = $row;
			$body =
				'<p>Hi, ' . $admin_name .
				'!</p><p>' . $_profile->user_name .
				' created new city <a href="' . get_server_url() . '/cities.php">' . $city .
				'</a>.</p><p>Please confirm!</p>';
			$text_body =
				'Hi, ' . $admin_name .
				"!\r\n\r\n" . $_profile->user_name .
				' created new city ' . $city . 
				' (' . get_server_url() . 
				'/cities.php).\r\nPlease confirm!\r\n';
			send_email($admin_email, $body, $text_body, 'New city');
		}
	}
	return $city_id;
}

function detect_city()
{
	global $_profile, $_lang_code;
	
	$city = Location::get()->city;
	$query = new DbQuery('SELECT name_' . $_lang_code . ' FROM cities WHERE name_en = ? OR name_ru = ?', $city, $city);
	if ($row = $query->next())
	{
		list ($city) = $row;
	}
	return $city;
}

define('CITY_FROM_PROFILE', 0);
define('CITY_DETECT', 1);
function show_city_input($name, $value, $country_id = -1, $on_select = NULL)
{
	global $_profile, $_lang_code;

	if ($value === CITY_FROM_PROFILE)
	{
		if ($_profile != NULL)
		{
			list ($value) = Db::record(get_label('city'), 'SELECT name_' . $_lang_code . ' FROM cities WHERE id = ?', $_profile->city_id);
		}
		else
		{
			$value = detect_city();
		}
	}
	else if ($value === CITY_DETECT)
	{
		$value = detect_city();
		if (($value == '' || $value == '-') && $_profile != NULL)
		{
			list ($value) = Db::record(get_label('city'), 'SELECT name_' . $_lang_code . ' FROM cities WHERE id = ?', $_profile->city_id);
		}
	}

	echo '<input type="text" id="' . $name . '" value="' . $value . '"/>';
	if (is_numeric($country_id))
	{
?>
		<script>
		$("#<?php echo $name; ?>").autocomplete(
		{ 
			source: function(request, response)
			{
				$.getJSON("api/control/city.php",
				{
					term: $("#<?php echo $name; ?>").val(),
					cid: <?php echo $country_id; ?>
				}, response);
			}
			, minLength: 0
		});
		</script>
<?php
	}
	else
	{
?>
		<script>
		$("#<?php echo $name; ?>").autocomplete(
		{ 
			source: function(request, response)
			{
				$.getJSON("api/control/city.php",
				{
					term: $("#<?php echo $name; ?>").val(),
					cname: $("#<?php echo $country_id; ?>").val()
				}, response);
			}
			, select: function(event, ui) { $("#<?php echo $country_id; ?>").val(ui.item.country); <?php if ($on_select != NULL) echo $on_select . '();'; ?> }
			, minLength: 0
		});
		</script>
<?php
	}
}

function show_city_buttons($id, $name, $flags)
{
	global $_profile;

	if ($_profile != NULL && $_profile->is_admin())
	{
		echo '<button class="icon" onclick="mr.deleteCity(' . $id . ')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
		echo '<button class="icon" onclick="mr.editCity(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
	}
}

?>