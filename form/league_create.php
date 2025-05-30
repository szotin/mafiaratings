<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';
require_once '../include/security.php';

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
	
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<tr><td colspan="2">';
		echo '<input type="checkbox" id="form-elite"> ' . get_label('elite league. Elite leagues can create elite series that bring more rating points.');
		echo '</td></tr>';
	}
	
	echo '</table>';
				
?>	
	<script>
	function commit(onSuccess)
	{
		let languages = mr.getLangs();
		
		let flags = 0;
		if ($("#form-elite").attr('checked')) flags |= <?php echo LEAGUE_FLAG_ELITE; ?>;
		
		json.post("api/ops/league.php",
		{
			op: 'create'
			, name: $("#form-name").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, langs: languages
			, flags: flags
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