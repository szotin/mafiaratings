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
	dialog_title(get_label('Settings'));
	
	if ($_profile == NULL)
	{
		throw new Exc(get_label('No permissions'));
	}
	$owner_id = $_profile->user_id;
	
	if (isset($_REQUEST['user_id']))
	{
		$user_id = (int)$_REQUEST['user_id'];
	}
	else
	{
		$user_id = $owner_id;
	}
	
	list($user_club_id, $user_name_id, $user_name, $user_flags, $user_city_id, $user_email, $user_langs, $user_phone) = Db::record(get_label('user'), 
		'SELECT u.club_id, u.name_id, nu.name, u.flags, u.city_id, u.email, u.languages, u.phone'.
		' FROM users u'.
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' WHERE u.id = ?', $user_id);
	check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $user_club_id);

	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark">' . get_label('User name') . ':</td><td class="light">';
	Names::show_control(new Names($user_name_id, get_label('user name')));
	echo '</td>';
	
	
	echo '</td><td width="140" align="center" valign="top" rowspan="8">';
	start_upload_logo_button($user_id);
	echo get_label('Change picture') . '<br>';
	$user_pic = new Picture(USER_PICTURE);
	$user_pic->set($user_id, $user_name, $user_flags);
	$user_pic->show(ICONS_DIR, false);
	end_upload_logo_button(USER_PIC_CODE, $user_id);
	echo '</td></tr>';
	
	$club_id = $user_club_id;
	if ($club_id == NULL)
	{
		$club_id = 0;
	}
	
	list ($city_name, $country_name) = Db::record(get_label('city'), 
		'SELECT nct.name, ncr.name FROM cities ct' .
		' JOIN countries cr ON cr.id = ct.country_id' .
		' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
		' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0' .
		' WHERE ct.id = ?', $user_city_id);
	
	echo '<tr><td class="dark" width="140">' . get_label('Email') . ':</td><td class="light"><input id="form-email" value="' . $user_email . '"></td></tr>';
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $country_name, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $city_name, 'form-country');
	echo '</td></tr>';
	
	echo '<tr><td class="dark" valign="top">' . get_label('Clubs') . ':</td><td class="light"><div id="form-clubs"></div></td></tr>';
	
	echo '<tr><td class="dark" valign="top">' . get_label('Gender') . ':</td><td class="light">';
	if ($user_flags & USER_FLAG_MALE)
	{
		if ($user_flags & USER_ICON_MASK)
		{
			echo '<input type="radio" id="form-male" name="is_male" value="1" checked/>'.get_label('male').'<br>';
			echo '<input type="radio" name="is_male" value="0"/>'.get_label('female');
		}
		else
		{
			echo '<input type="radio" id="form-male" name="is_male" value="1" onClick="document.profileForm.submit()" checked/>'.get_label('male').'<br>';
			echo '<input type="radio" name="is_male" value="0" onClick="document.profileForm.submit()"/>'.get_label('female');
		}
	}
	else if ($user_flags & USER_ICON_MASK)
	{
		echo '<input type="radio" id="form-male" name="is_male" value="1"/>'.get_label('male').'<br>';
		echo '<input type="radio" name="is_male" value="0" checked/>'.get_label('female');
	}
	else
	{
		echo '<input type="radio" id="form-male" name="is_male" value="1" onClick="document.profileForm.submit()"/>'.get_label('male').'<br>';
		echo '<input type="radio" name="is_male" value="0" onClick="document.profileForm.submit()" checked/>'.get_label('female');
	}
	echo '</td></tr>';
	
	echo '<tr><td class="dark" valign="top">'.get_label('Languages').':</td><td class="light">';
	langs_checkboxes($user_langs);
	echo '</td></tr>';
	
	echo '<tr><td class="dark">' . get_label('Phone') . ':</td><td class="light"><input id="form-phone" value="' . $user_phone . '"></td></tr>';
	echo '</table>';
	
	echo '<p><input type="checkbox" id="form-message_notify"';
	if (($user_flags & USER_FLAG_MESSAGE_NOTIFY) != 0)
	{
		echo ' checked';
	}
	echo '>'.get_label('I would like to receive emails when someone replies to me or sends me a private message.');
	echo '<br><input type="checkbox" id="form-photo_notify"';
	if (($user_flags & USER_FLAG_PHOTO_NOTIFY) != 0)
	{
		echo ' checked';
	}
	echo '>'.get_label('I would like to receive emails when someone tags me on a photo.').'</p>';
	
?>
	<script>
	
	function refreshClubMembership()
	{
		http.get("form/club_membership.php?user_id=<?php echo $user_id; ?>", function(html) { $("#form-clubs").html(html); });
	}

	refreshClubMembership();
	
	function joinClub()
	{
		json.post("api/ops/user.php",
		{
			op: "join_club"
			, user_id: <?php echo $user_id; ?>
			, club_id: $('#form-join-club').val()
		},
		refreshClubMembership);
	}
	
	function quitClub(clubId)
	{
		dlg.yesNo("<?php echo get_label("Are you sure you want to quit club?"); ?>", null, null, function()
		{
			json.post("api/ops/user.php",
			{
				op: "quit_club"
				, user_id: <?php echo $user_id; ?>
				, club_id: clubId
			},
			refreshClubMembership);
		});
	}
	
	function subscribe(clubId, subs)
	{
		var o = subs ? "subscribe" : "unsubscribe";
		json.post("api/ops/user.php",
		{
			op: o
			, user_id: <?php echo $user_id; ?>
			, club_id: clubId
		},
		refreshClubMembership);
	}
	
	function mainClub(clubId)
	{
		json.post("api/ops/user.php", { op: 'edit', user_id: <?php echo $user_id; ?>, club_id: clubId }, refreshClubMembership);
	}
	
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		var request =
		{
			op: 'edit'
			, user_id: <?php echo $user_id; ?>
			, email: $("#form-email").val()
			, male: ($("#form-male").attr("checked") ? 1 : 0)
			, country: $("#form-country").val()
			, city: $("#form-city").val()
			, phone: $("#form-phone").val()
			, langs: languages
			, message_notify: ($("#form-message_notify").attr("checked") ? 1 : 0)
			, photo_notify: ($("#form-photo_notify").attr('checked') ? 1 : 0)
		};
		nameControl.fillRequest(request);
		json.post("api/ops/user.php", request, onSuccess);
	}
	
	function uploadLogo(userId, onSuccess)
	{
		json.upload('api/ops/user.php', 
		{
			op: "edit"
			, user_id: userId
			, picture: document.getElementById("upload").files[0]
		}, 
		<?php echo UPLOAD_LOGO_MAX_SIZE; ?>, 
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