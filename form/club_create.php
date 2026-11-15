<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/club.php';
require_once '../include/names.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('club')));
	
	$langs = $_profile->user_langs;
	$email = $_profile->user_email;

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140" valign="top">'.get_label('Club name').':</td><td>';
	Names::show_control(new Names(0, get_label('club name')));
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', COUNTRY_DETECT, 'form-city');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', CITY_DETECT, 'form-country');
	echo '</td></tr>';

	// The league whose defaults - rules and systems - the club starts from. Without one a sub-club
	// starts from its parent, any other club from the defaults of the site.
	echo '<tr><td>' . get_label('System') . ':</td><td>';
	show_system_league_select('form-system');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Parent club') . ':</td><td>';
	echo '<select id="form-parent">';
	show_option(0, $profile->user_club_id, '');
	$query = new DbQuery(
		'SELECT c.id, nc.name'.
		' FROM clubs c'.
		' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
		' WHERE c.parent_id IS NULL AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0'.
		' ORDER BY nc.name');
	while ($row = $query->next())
	{
		list($c_id, $c_name) = $row;
		show_option((int)$c_id, $profile->user_club_id, $c_name);
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Languages').':</td><td>';
	langs_checkboxes($langs);
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Web site').':</td><td><input class="longest" id="form-url"> </td></tr>';

	echo '<tr><td>'.get_label('Contact email').':</td><td>';
	echo '<input class="longest" id="form-email" value="' . htmlspecialchars($email, ENT_QUOTES) . '">';
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td>';
	echo '<input class="longest" id="form-phone">';
	echo '</td></tr>';

	echo '</table>';
?>	
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		var request =
		{
			op: 'create'
			, parent_id: $("#form-parent").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, country: $("#form-country").val()
			, city: $("#form-city").val()
			, langs: languages
			, system_league_id: $("#form-system").val()
		};
		nameControl.fillRequest(request);
		json.post("api/ops/club.php", request, onSuccess);
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