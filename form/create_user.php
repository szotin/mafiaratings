<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/image.php';
require_once '../include/languages.php';

initiate_session();

try
{
	dialog_title(get_label('Create user account'));
	
	$club_id = 0;
	if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
	}
	
	$city = '';
	$country = '';
	if (isset($_REQUEST['city_id']))
	{
		list ($city, $country) = db::record(get_label('city'), 
			'SELECT ni.name, no.name FROM cities i' .
			' JOIN countries o ON o.id = i.country_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
			' WHERE i.id = ?', $_REQUEST['city_id']);
	}
	else if (isset($_REQUEST['country_id']))
	{
		list ($country) = db::record(get_label('country'), 
			'SELECT no.name FROM countries o' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
			' WHERE o.id = ?', $_REQUEST['country_id']);
	}
	else if ($club_id > 0)
	{
		list ($city, $country) = db::record(get_label('city'), 
			'SELECT ni.name, no.name FROM clubs c'.
			' JOIN cities i ON i.id = c.city_id' .
			' JOIN countries o ON o.id = i.country_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
			' WHERE c.id = ?', $club_id);
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark">' . get_label('User name') . ':</td><td class="light">';
	Names::show_control(new Names(0, get_label('user name')));
	echo '</td></tr>';
	
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $country, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $city, 'form-country');
	echo '</td></tr>';
	
	echo '<tr><td class="dark" width="140">' . get_label('Email') . ':</td><td class="light"><input type="email" id="form-email" placeholder="'.get_label('Leave it empty if unknown').'"></td></tr>';
	
?>
	<script>
	
	function commit(onSuccess)
	{
		var request =
		{
			op: 'create'
			, email: $("#form-email").val()
			, country: $("#form-country").val()
			, city: $("#form-city").val()
			, club_id: <?php echo $club_id ?>
		};
		nameControl.fillRequest(request);
		json.post("api/ops/user.php", request, onSuccess);
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