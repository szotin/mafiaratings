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
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark">' . get_label('User name') . ':</td><td class="light">';
	Names::show_control(new Names(0, get_label('user name')));
	echo '</td></tr>';
	
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', '', 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', '', 'form-country');
	echo '</td></tr>';
	
	echo '<tr><td class="dark" width="140">' . get_label('Email') . ':</td><td class="light"><input id="form-email"></td></tr>';
	
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
		};
		nameControl.fillRequest(request);
		json.post("api/ops/account.php", request, onSuccess);
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