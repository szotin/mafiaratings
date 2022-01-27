<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/user.php';
require_once '../include/rand_str.php';

initiate_session();

try
{
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		list($club_id, $token, $langs) = Db::record(get_label('event'), 'SELECT club_id, security_token, languages FROM events WHERE id = ?', $event_id);
		if (is_null($token))
		{
			$token = rand_string(32);
			Db::exec(get_label('event'), 'UPDATE events SET security_token = ? WHERE id = ?', $token, $event_id);
		}
	}
	else if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
		list($club_id, $token, $langs) = Db::record(get_label('tournament'), 'SELECT club_id, security_token, langs FROM tournaments WHERE id = ?', $tournament_id);
		if (is_null($token))
		{
			$token = rand_string(32);
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET security_token = ? WHERE id = ?', $token, $tournament_id);
		}
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
	if (is_valid_lang($langs))
	{
		$lang = $langs;
	}
	else
	{
		$lang = $_profile->user_def_lang;
	}

	dialog_title(get_label('Generate OBS studio URL'));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td colspan="2"><h4>' . get_label('Please select video options for the OBS URL') . '</h4></td>';
	echo '<tr><td width="120">' . get_label('Language') . '</td><td>';
	echo '<select id="form-lang" onchange="showInstr()">';
	echo '<option value="ru">' . get_label('Russian') . '</option>';
	echo '<option value="en">' . get_label('English') . '</option>';
	echo '</select>';
	echo '</td></tr>';
	echo '<tr><td>' . get_label('User account') . '</td><td>';
	show_user_input('form-user', '', '', get_label('Please select user account that will be used to moderate games.'), 'changeUser');
	echo '</td></tr>';
	echo '<tr><td>' . get_label('Roles') . '</td><td><input id="form-roles" type="checkbox" checked onclick="showInstr()"> ' . get_label('Show roles') . '</td></tr>';
	echo '</table><p><table class="dialog_form" width = 100%';
	echo '<tr><td><h4>' . get_label('Instructions') . '</h4></td></tr>';
	echo '<tr><td id="form-instr"></td></tr>';
	echo '</table>';
?>
	<script>
	var userId = 0;
	function changeUser(data)
	{
		userId = data ? data.id : 0;
		showInstr();
	}
	
	function showInstr()
	{
		var html;
		if (userId > 0)
		{
			var params = '?user_id=' + userId + '&token=<?php echo $token; ?>&locale=' + $('#form-lang').val();
			var site = '<?php echo get_server_url(); ?>';
			if (!$('#form-roles').attr('checked'))
				params += '&hide_roles';
			var url1 = site + '/obs_plugins/players-overlay-plugin/#/players' + params;
			var url2 = site + '/obs_plugins/players-overlay-plugin/#/gamestats' + params;
			var html =
				'<ol>' +
				'<li><?php echo get_label('Open OBS Studio and your scene in it.'); ?></li>' +
				'<li><?php echo get_label('Click + in Sources and select Browser from the menu. Click ok.'); ?></li>' +
				'<li><?php echo get_label('Enter the next URL in the URL field:'); ?></li>' +
				'<ol>' +
				'<li><?php echo get_label('For players photos'); ?>:<br><a href="' + url1 + '" target="_blank">' + url1 + '</a></li>' +
				'<li><?php echo get_label('For game statistics - nominees/checks'); ?>:<br><a href="' + url2 + '" target="_blank">' + url2 + '</a></li>' +
				'</ol>' +
				'<li><?php echo get_label('Configure other parameters and click ok. Do not panic if it shows nothing. The information will appear once you start the game in this tournament/event using the specified user account.'); ?></li>' +
				'</ol>';
		}
		else
		{
			html = '<?php echo get_label('Please select user account that will be used to moderate games.'); ?>';
		}
		$('#form-instr').html(html);
	}
	changeUser();
	</script>
<?php
	
	// $lang_code = get_lang_code($lang);
	
	// $url_gamestats = PRODUCT_URL . '/obs_plugins/players-overlay-plugin/#/gamestats?locale=' . $lang_code;
	// $url_photos = PRODUCT_URL . '/obs_plugins/players-overlay-plugin/#/gamestats?locale=' . $lang_code;
	// if (isset($_REQUEST['hide_roles']))
	// {
		// $url_gamestats .= '&hide_roles';
		// $url_photos .= '&hide_roles';
	// }
	
	// dialog_title(get_label('OBS studio URL'));
	// echo '<p><h4>' . get_label('Instructions') . '</h4></p>';
	// echo '<p><ol>';
	// echo '<li>' . get_label('Please open OBS Studio and your scene in it.') . '</li>';
	// echo '<li>' . get_label('Click + in Sources and select Browser from the menu. Click ok.') . '</li>';
	// echo '<li>' . get_label('Enter the next URL in the URL field:') . '</li>';
	// echo '<ol>';
	// echo '<li><a href="' . $url_photos . '" target="_blank">' . $url_photos . '</a>' . get_label(' for players photos.') . ' ' . get_label('Click here [0] to copy it to clipboard.') . '</li>';
	// echo '<li><a href="' . $url_gamestats . '" target="_blank">' . $url_gamestats . '</a>' . get_label(' for game statistics - nominees/checks.') . ' ' . get_label('Click here [0] to copy it to clipboard.') . '</li>';
	// echo '</ol>';
	// echo '<li>' . get_label('Configure other parameters and click ok. Don\'t panic if it shows nothing. The information will appear once you start the game in this tournament/event using the specified user account.') . '</li>';
	// echo '</ol></p>';
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>