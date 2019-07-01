<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('league')));
	
	$langs = $_profile->user_langs;
	$email = $_profile->user_email;

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">'.get_label('League name').':</td><td><input class="longest" id="form-name"> </td></tr>';
	echo '<tr><td>'.get_label('Web site').':</td><td><input class="longest" id="form-url"> </td></tr>';
				
	echo '<tr><td>'.get_label('Languages').':</td><td>';
	langs_checkboxes($langs);
	echo '</td></tr>';
				
	echo '<tr><td>'.get_label('Contact email').':</td><td>';
	echo '<input class="longest" id="form-email" value="' . htmlspecialchars($email, ENT_QUOTES) . '">';
	echo '</td></tr>';
				
	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td>';
	echo '<input class="longest" id="form-phone">';
	echo '</td></tr>';
				
?>	
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("api/ops/league.php",
		{
			op: 'create'
			, name: $("#form-name").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, langs: languages
		},
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