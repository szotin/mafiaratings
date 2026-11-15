<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';
require_once '../include/security.php';
require_once '../include/names.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('league')));
	
	$langs = $_profile->user_langs;
	$email = $_profile->user_email;

	echo '<table class="dialog_form" width="100%">';
	// An admin creates the league at once and gives it its names in every language. Anyone else sends
	// a request, which keeps one name; the others are set when it is accepted or edited.
	$is_admin = is_permitted(PERMISSION_ADMIN);
	echo '<tr><td width="140" valign="top">'.get_label('League name').':</td><td>';
	if ($is_admin)
	{
		Names::show_control(new Names(0, get_label('league name')));
	}
	else
	{
		echo '<input class="longest" id="form-name">';
	}
	echo '</td></tr>';
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
	
	if ($is_admin)
	{
		echo '<tr><td colspan="2">';
		echo '<input type="checkbox" id="form-elite"> ' . get_label('global league. The default rules and scoring systems of a global league are offered to every club, and it can create elite series that bring more rating points.');
		echo '</td></tr>';
	}
	
	echo '</table>';
				
?>	
	<script>
	function commit(onSuccess)
	{
		let languages = mr.getLangs();
		
		let flags = 0;
		if ($("#form-elite").attr('checked')) flags |= <?php echo LEAGUE_FLAG_GLOBAL; ?>;
		
		let request =
		{
			op: 'create'
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, langs: languages
			, flags: flags
		};
<?php
	if ($is_admin)
	{
		echo "\t\tnameControl.fillRequest(request);\n";
	}
	else
	{
		echo "\t\trequest.name = $(\"#form-name\").val();\n";
	}
?>
		json.post("api/ops/league.php", request, onSuccess);
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