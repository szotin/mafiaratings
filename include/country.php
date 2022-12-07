<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/location.php';

define('UNDEFINED_COUNTRY', -1);
define('ALL_COUNTRIES', 0);

function retrieve_country_id($country)
{
	global $_profile;

	$country = trim($country);
	if (empty($country))
	{
		throw new Exc(get_label('Please enter [0].', get_label('country')));
	}

	Db::begin();
	$query = new DbQuery('SELECT country_id FROM country_names WHERE name = ?', $country);
	if ($row = $query->next())
	{
		list($country_id) = $row;
	}
	else
	{
		Db::exec(get_label('name'), 'INSERT INTO names (langs, name) VALUES (?, ?)', DB_ALL_LANGS, $country);
		list ($name_id) = Db::record(get_label('name'), 'SELECT LAST_INSERT_ID()');
		Db::exec(get_label('country'), 'INSERT INTO countries (name_id, flags, code) VALUES (?, ' . COUNTRY_FLAG_NOT_CONFIRMED . ', \'\')', $name_id);
		list ($country_id) = Db::record(get_label('country'), 'SELECT LAST_INSERT_ID()');
		
		Db::exec(get_label('country'), 'INSERT INTO country_names (country_id, name) VALUES (?, ?)', $country_id, $country);
		
		$log_details = new stdClass();
		$log_details->name = $country;
		$log_details->code = '';
		$log_details->flags = COUNTRY_FLAG_NOT_CONFIRMED;
		db_log(LOG_OBJECT_COUNTRY, 'created', $log_details, $country_id);
		
		$query = new DbQuery('SELECT id, name, email FROM users WHERE (flags & ' . USER_PERM_ADMIN . ') <> 0 and email <> \'\'');
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
	Db::commit();
	return $country_id;
}

function detect_country()
{
	global $_profile, $_lang;
	
	$loc = Location::get();
	$query = new DbQuery('SELECT n.name FROM countries c JOIN names n ON n.id = c.name_id AND (n.langs & ?) <> 0 WHERE c.code = ?', $_lang, $loc->country_code);
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
	global $_profile, $_lang;

	if ($value === COUNTRY_FROM_PROFILE)
	{
		if ($_profile != NULL)
		{
			list ($value) = Db::record(get_label('country'), 'SELECT n.name FROM countries c JOIN names n ON n.id = c.name_id AND (n.langs & ?) <> 0 WHERE c.id = ?', $_lang, $_profile->country_id);
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
			list ($value) = Db::record(get_label('country'), 'SELECT n.name FROM countries c JOIN names n ON n.id = c.name_id AND (n.langs & ?) <> 0 WHERE c.id = ?', $_lang, $_profile->country_id);
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
				$.getJSON("api/control/country.php",
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
				$.getJSON("api/control/country.php",
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
		echo '<button class="icon" onclick="mr.editCountry(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
	}
}

?>