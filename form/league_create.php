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
	// Anyone creates a league at once; only an administrator can make it global.
	$is_admin = is_permitted(PERMISSION_ADMIN);
	echo '<tr><td width="140" valign="top">'.get_label('League name').':</td><td>';
	Names::show_control(new Names(0, get_label('league name')));
	echo '</td></tr>';

	// The logo, as in form/tournament_create.php: only chosen here - and shown - and uploaded by
	// commit() once the league is made.
	echo '<tr><td>' . get_label('Logo') . ':</td><td>';
	echo '<input type="file" id="form-logo" accept="image/*" style="display:none" onchange="logoChosen()">';
	echo '<button type="button" class="upload" onclick="$(\'#form-logo\').trigger(\'click\');">';
	echo '<img id="form-logo-img" src="images/' . ICONS_DIR . 'league.png" width="56" height="56"></button>';
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
		nameControl.fillRequest(request);
		json.post("api/ops/league.php", request, function(obj)
		{
			// The league is made whatever happens to its logo: a logo that fails to upload is said,
			// and can be changed on the first tab of its management.
			var logo = document.getElementById('form-logo').files[0];
			if (!logo)
			{
				onSuccess(obj);
				return;
			}
			json.upload('api/ops/league.php',
			{
				op: "change"
				, league_id: obj.league_id
				, logo: logo
			},
			<?php echo UPLOAD_LOGO_MAX_SIZE; ?>,
			function() { onSuccess(obj); },
			function(message) { dlg.error(message, null, null, function() { onSuccess(obj); }); return true; });
		});
	}

	function logoChosen()
	{
		var file = document.getElementById('form-logo').files[0];
		if (file && window.URL)
		{
			var url = URL.createObjectURL(file);
			$('#form-logo-img').attr('src', url);
			// The summary beside the form shows it too, where there is one - see create.php.
			$('#wz-sum-logo-img').attr('src', url);
		}
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