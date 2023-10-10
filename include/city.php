<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/email.php';

define('UNDEFINED_CITY', -1);
define('ALL_CITIES', 0);

function retrieve_city_id($city, $country_id, $timezone = NULL)
{
	global $_profile, $_lang;
	
	if ($timezone == NULL)
	{
		$timezone = get_timezone();
	}
	
	$city = trim($city);
	if (empty($city))
	{
		throw new Exc(get_label('Please enter [0].', get_label('city')));
	}

	Db::begin();
	$query = new DbQuery('SELECT city_id FROM city_names WHERE name = ?', $city);
	if ($row = $query->next())
	{
		list($city_id) = $row;
	}
	else
	{
		Db::exec(get_label('name'), 'INSERT INTO names (langs, name) VALUES (?, ?)', DB_ALL_LANGS, $city);
		list ($name_id) = Db::record(get_label('name'), 'SELECT LAST_INSERT_ID()');
		Db::exec(get_label('city'),
			'INSERT INTO cities (country_id, name_id, flags, timezone) VALUES (?, ?, ' . CITY_FLAG_NOT_CONFIRMED . ', ?)',
			$country_id, $name_id, $timezone);
		list($city_id) = Db::record(get_label('city'), 'SELECT LAST_INSERT_ID()');
		Db::exec(get_label('city'), 'INSERT INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $city);
		
		$log_details = new stdClass();
		$log_details->name = $city;
		$log_details->timezone = $timezone;
		$log_details->flags = CITY_FLAG_NOT_CONFIRMED;
		db_log(LOG_OBJECT_CITY, 'created', $log_details, $city_id);
		
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email'.
			' FROM users u'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE (u.flags & ' . USER_PERM_ADMIN . ') <> 0 and u.email <> \'\'');
		while ($row = $query->next())
		{
			list($admin_id, $admin_name, $admin_email) = $row;
			$club_name = NULL;
			if ($_profile->user_club_id != NULL && isset($_profile->clubs[$_profile->user_club_id]))
			{
				$club_name = $_profile->clubs[$_profile->user_club_id]->name;
			}
			$body =
				'<p>Hi, ' . $admin_name .
				'!</p><p>' . $_profile->user_name;
			if (!is_null($club_name))
			{
				$body .=	' (' . $club_name . ')';
			}
			$body .=	
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
	Db::commit();
	return $city_id;
}

function detect_city()
{
	global $_profile, $_lang;
	
	$city = Location::get()->city;
	$query = new DbQuery('SELECT nc.name FROM cities c JOIN names n ON n.id = c.name_id JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0 WHERE n.name = ? LIMIT 1', $city);
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
	global $_profile, $_lang;

	if ($value === CITY_FROM_PROFILE)
	{
		if ($_profile != NULL)
		{
			list ($value) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 WHERE id = ?', $_profile->city_id);
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
			list ($value) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 WHERE c.id = ?', $_profile->city_id);
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
					country_id: <?php echo $country_id; ?>
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
					country_name: $("#<?php echo $country_id; ?>").val()
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